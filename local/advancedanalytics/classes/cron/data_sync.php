<?php
// local/advancedanalytics/classes/cron/data_sync.php
// Cron-based data synchronization strategy - FIXED

namespace local_advancedanalytics\cron;

defined('MOODLE_INTERNAL') || die();

class data_sync {
    
    /**
     * Main sync function - orchestrates all data aggregation
     */
    public static function sync_all($verbose = false) {
        global $DB;
        
        $start_time = microtime(true);
        
        if ($verbose) {
            mtrace("Starting analytics data sync at " . date('Y-m-d H:i:s'));
        }
        
        $today = strtotime('today midnight');
        $last_sync = get_config('local_advancedanalytics', 'last_sync') ?: 0;
        
        // 1. Sync user summary
        $user_count = self::sync_user_summary($today, $verbose);
        
        // 2. Sync course analytics
        $course_count = self::sync_course_analytics($today, $verbose);
        
        // 3. Sync department stats
        $dept_count = self::sync_department_stats($today, $verbose);
        
        // 4. Update user performance cache
        $perf_count = self::update_user_performance_cache($today, $verbose);
        
        // 5. Sync compliance tracking data
        $comp_count = self::sync_compliance_data($today, $verbose);
        
        // 6. Clean old data
        $cleaned = self::clean_old_data($verbose);
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        // Update last sync time
        set_config('last_sync', time(), 'local_advancedanalytics');
        set_config('last_sync_duration', $duration, 'local_advancedanalytics');
        
        if ($verbose) {
            mtrace("Sync completed in {$duration} seconds");
            mtrace("Summary: {$user_count} users, {$course_count} courses, {$dept_count} departments, {$perf_count} performances, {$comp_count} compliance records, {$cleaned} cleaned");
        }
        
        return true;
    }
    
    /**
     * Sync user summary data - Batched for large datasets
     */
    private static function sync_user_summary($date, $verbose) {
        global $DB;
        
        $count = 0;
        $batchsize = 1000;
        $offset = 0;
        
        // Delete existing data for this date
        $DB->delete_records('local_aa_summary', ['date' => $date]);
        
        while (true) {
            $users = \local_advancedanalytics\data_extractor::extract_user_summary($date, $batchsize, $offset);
            
            if (empty($users)) {
                break;
            }
            
            $batch_records = [];
            foreach ($users as $user) {
                $record = new \stdClass();
                $record->date = $date;
                $record->department = $user->department ?: 'Unassigned';
                $record->courseid = 0;
                $record->total_users = 1;
                $record->active_users = $user->is_active ? 1 : 0;
                $record->new_users = ($user->user_created > ($date - 86400)) ? 1 : 0;
                $record->completions = (int)($user->completed_courses ?? 0);
                $record->avg_grade = (float)($user->avg_grade ?? 0);
                $record->total_time = 0;
                $record->engagement_score = 0;
                $record->logins_count = 0;
                $record->timecreated = time();
                $record->timemodified = time();
                
                $batch_records[] = $record;
                $count++;
            }
            
            // Batch insert for performance
            if (!empty($batch_records)) {
                $DB->insert_records('local_aa_summary', $batch_records);
            }
            
            $offset += $batchsize;
            
            if ($verbose) {
                mtrace("Processed {$count} users...");
            }
            
            unset($batch_records);
            unset($users);
        }
        
        return $count;
    }
    
    /**
     * Sync course analytics - FIXED: Ensure no NULL values
     */
    private static function sync_course_analytics($date, $verbose) {
        global $DB;
        
        $courses = \local_advancedanalytics\data_extractor::extract_course_analytics($date);
        
        // Delete existing data for this date
        $DB->delete_records('local_aa_course_cache', ['date' => $date]);
        
        $count = 0;
        $batch_records = [];
        
        foreach ($courses as $course) {
            $total_enrolled = (int)($course->total_enrolled ?? 0);
            $completed_count = (int)($course->completed_count ?? 0);
            
            // Calculate completion rate - ensure it's never NULL
            $completion_rate = 0;
            if ($total_enrolled > 0) {
                $completion_rate = round(($completed_count / $total_enrolled) * 100, 2);
            }
            
            $record = new \stdClass();
            $record->courseid = (int)$course->courseid;
            $record->date = $date;
            $record->total_enrolled = $total_enrolled;
            $record->completed_count = $completed_count;
            $record->completion_rate = $completion_rate;  // Now guaranteed not NULL
            $record->avg_grade = (float)($course->avg_grade ?? 0);
            $record->active_participants = (int)($course->active_participants ?? 0);
            $record->avg_time_spent = (int)($course->avg_time_spent ?? 0);
            $record->last_activity = (int)($course->last_activity ?? time());
            $record->timecreated = time();
            $record->timemodified = time();
            
            $batch_records[] = $record;
            $count++;
        }
        
        if (!empty($batch_records)) {
            $DB->insert_records('local_aa_course_cache', $batch_records);
        }
        
        if ($verbose) {
            mtrace("Synced {$count} courses");
        }
        
        return $count;
    }
    
    /**
     * Sync department statistics - FIXED: Ensure no NULL values
     */
    private static function sync_department_stats($date, $verbose) {
        global $DB;
        
        $departments = \local_advancedanalytics\data_extractor::extract_department_stats($date);
        
        // Delete existing data for this date
        $DB->delete_records('local_aa_dept_stats', ['date' => $date]);
        
        $count = 0;
        $batch_records = [];
        
        foreach ($departments as $dept) {
            $record = new \stdClass();
            $record->department = $dept->department ?: 'Unassigned';
            $record->date = $date;
            $record->total_employees = (int)($dept->total_employees ?? 0);
            $record->trained_employees = (int)($dept->trained_employees ?? 0);
            $record->compliance_rate = (float)($dept->compliance_rate ?? 0);
            $record->avg_performance = (float)($dept->avg_performance ?? 0);
            $record->certifications_earned = (int)($dept->certifications_earned ?? 0);
            $record->certifications_expiring = (int)($dept->certifications_expiring ?? 0);
            $record->at_risk_count = (int)($dept->at_risk_count ?? 0);
            $record->high_performers = (int)($dept->high_performers ?? 0);
            $record->low_performers = (int)($dept->low_performers ?? 0);
            $record->timecreated = time();
            $record->timemodified = time();
            
            $batch_records[] = $record;
            $count++;
        }
        
        if (!empty($batch_records)) {
            $DB->insert_records('local_aa_dept_stats', $batch_records);
        }
        
        if ($verbose) {
            mtrace("Synced {$count} departments");
        }
        
        return $count;
    }
    
   // In data_sync.php, fix the update_user_performance_cache method:

private static function update_user_performance_cache($date, $verbose) {
    global $DB;
    
    $count = 0;
    $batchsize = 500;
    $offset = 0;
    
    while (true) {
        $performances = \local_advancedanalytics\data_extractor::extract_user_performance_batch($date, $batchsize, $offset);
        
        if (empty($performances)) {
            break;
        }
        
        $batch_records = [];
        foreach ($performances as $perf) {
            // Ensure all values are within valid ranges
            $engagement = min(100, max(0, (float)($perf->engagement_score ?? 0)));
            $completion = min(100, max(0, (float)($perf->completion_percentage ?? 0)));
            $quiz_score = min(100, max(0, (float)($perf->avg_quiz_score ?? 0)));
            $time_spent = min(999999999, max(0, (int)($perf->time_spent ?? 0)));
            $login_count = min(30, max(0, (int)($perf->login_count ?? 0)));
            $last_access = (int)($perf->last_access ?? time());
            
            // Determine risk level
            if ($engagement < 30 || $completion < 20) {
                $risk_level = 'high';
            } elseif ($engagement < 50 || $completion < 50) {
                $risk_level = 'medium';
            } else {
                $risk_level = 'low';
            }
            
            // Predict completion
            $predicted = $completion + (($engagement / 100) * 30) + (($quiz_score / 100) * 20);
            $predicted = min(100, round($predicted, 2));
            
            $record = new \stdClass();
            $record->userid = (int)$perf->userid;
            $record->courseid = 0;
            $record->engagement_score = $engagement;
            $record->completion_percentage = $completion;
            $record->avg_quiz_score = $quiz_score;
            $record->time_spent = $time_spent;
            $record->last_access = $last_access;
            $record->login_count = $login_count;
            $record->risk_level = $risk_level;
            $record->predicted_completion = $predicted;
            $record->last_calculated = (int)$date;
            $record->timecreated = time();
            $record->timemodified = time();
            
            // Check if record exists
            $existing = $DB->get_record('local_aa_user_perf', [
                'userid' => $perf->userid,
                'courseid' => 0
            ]);
            
            if ($existing) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_aa_user_perf', $record);
            } else {
                $batch_records[] = $record;
            }
            $count++;
        }
        
        // Batch insert new records
        if (!empty($batch_records)) {
            $DB->insert_records('local_aa_user_perf', $batch_records);
        }
        
        $offset += $batchsize;
        
        if ($verbose && $count % 1000 == 0) {
            mtrace("Processed {$count} user performances...");
        }
        
        unset($batch_records);
        unset($performances);
    }
    
    return $count;
}
    /**
     * Calculate risk level based on performance metrics
     */
    private static function calculate_risk_level($performance) {
        $engagement = (float)($performance->engagement_score ?? 0);
        $completion = (float)($performance->completion_percentage ?? 0);
        
        if ($engagement < 30 || $completion < 20) {
            return 'high';
        } elseif ($engagement < 50 || $completion < 50) {
            return 'medium';
        }
        return 'low';
    }
    
    /**
     * Predict completion percentage using simple algorithm
     */
    private static function predict_completion($performance) {
        $base = (float)($performance->completion_percentage ?? 0);
        $engagement_factor = ((float)($performance->engagement_score ?? 0) / 100);
        $quiz_factor = ((float)($performance->avg_quiz_score ?? 0) / 100);
        
        $predicted = $base + ($engagement_factor * 30) + ($quiz_factor * 20);
        
        return min(100, round($predicted, 2));
    }
    
    /**
     * Sync compliance data - Logic for Point 7
     */
    private static function sync_compliance_data($date, $verbose) {
        global $DB;
        
        $count = 0;
        $users = $DB->get_records('user', ['deleted' => 0, 'suspended' => 0], '', 'id');
        $mandatory_courses = $DB->get_records('local_aa_compliance', ['is_mandatory' => 1]);
        
        if (empty($mandatory_courses)) {
            return 0;
        }

        foreach ($users as $user) {
            $total_m = count($mandatory_courses);
            $completed_m = 0;
            $overdue = 0;
            
            foreach ($mandatory_courses as $mc) {
                $comp = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $mc->courseid]);
                if ($comp && $comp->timecompleted) {
                    $completed_m++;
                } else {
                    if ($user->timecreated < (time() - (30 * 24 * 3600))) {
                        $overdue++;
                    }
                }
            }
            
            $pct = $total_m > 0 ? ($completed_m / $total_m) * 100 : 100;
            
            $record = new \stdClass();
            $record->userid = $user->id;
            $record->compliance_percentage = round($pct, 2);
            $record->completed_count = $completed_m;
            $record->total_mandatory = $total_m;
            $record->expired_count = 0;
            // Check if user is new (less than 30 days)
$is_new_user = ($user->timecreated > (time() - (30 * 24 * 3600)));

if ($pct == 100) {
    $record->status = 'compliant';
} elseif ($is_new_user) {
    $record->status = 'pending';
} else {
    $record->status = 'overdue';
}
            $record->timecreated = time();
            $record->timemodified = time();
            
            $existing = $DB->get_record('local_aa_user_compliance', ['userid' => $user->id]);
            if ($existing) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_aa_user_compliance', $record);
            } else {
                $DB->insert_record('local_aa_user_compliance', $record);
            }
            $count++;
        }
        
        if ($verbose) {
            mtrace("Synced {$count} compliance records");
        }
        
        return $count;
    }

    /**
     * Clean old data based on retention policy
     */
    private static function clean_old_data($verbose) {
        global $DB;
        
        $retention_days = get_config('local_advancedanalytics', 'data_retention_days') ?: 365;
        $cutoff = time() - ($retention_days * 24 * 3600);
        
        $tables = ['local_aa_summary', 'local_aa_dept_stats', 'local_aa_course_cache'];
        $total_cleaned = 0;
        
        foreach ($tables as $table) {
            $count = $DB->count_records_select($table, 'date < ?', [$cutoff]);
            if ($count > 0) {
                $DB->delete_records_select($table, 'date < ?', [$cutoff]);
                $total_cleaned += $count;
                
                if ($verbose) {
                    mtrace("Cleaned {$count} records from {$table}");
                }
            }
        }
        
        return $total_cleaned;
    }
}