#!/usr/bin/env php
<?php
// local/advancedanalytics/cli/populate_tables.php
// Populate all Phase 2 tables with REAL data from Moodle - COMPLETELY FIXED

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('📊 PHASE 2: Populating Tables with REAL Data');

global $DB;

// Get real data counts
$total_users = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
cli_writeln("\n📈 Found {$total_users} active users in your Moodle database.");

$today = strtotime('today midnight');

// ============================================
// 1. POPULATE local_aa_summary
// ============================================
cli_writeln("\n[1/4] Populating local_aa_summary...");

$DB->delete_records('local_aa_summary', ['date' => $today]);

$sql = "SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT CASE WHEN l.timecreated > :active_cutoff THEN u.id END) as active_users,
            COUNT(DISTINCT CASE WHEN u.timecreated > :new_cutoff THEN u.id END) as new_users,
            COUNT(DISTINCT cc.id) as completions,
            AVG(CASE 
                WHEN gg.finalgrade > 100 THEN 100 
                WHEN gg.finalgrade < 0 THEN 0 
                ELSE gg.finalgrade 
            END) as avg_grade,
            COUNT(DISTINCT l.id) as logins_count
        FROM {user} u
        LEFT JOIN {logstore_standard_log} l ON l.userid = u.id
        LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.timecompleted IS NOT NULL
        LEFT JOIN {grade_grades} gg ON gg.userid = u.id
        LEFT JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
        WHERE u.deleted = 0 AND u.suspended = 0";

$params = [
    'active_cutoff' => time() - (30 * 24 * 3600),
    'new_cutoff' => time() - (30 * 24 * 3600)
];

$stats = $DB->get_record_sql($sql, $params);

if ($stats) {
    $summary = new stdClass();
    $summary->date = $today;
    $summary->department = 'All Departments';
    $summary->courseid = 0;
    $summary->total_users = $stats->total_users ?? 0;
    $summary->active_users = $stats->active_users ?? 0;
    $summary->new_users = $stats->new_users ?? 0;
    $summary->completions = $stats->completions ?? 0;
    $summary->avg_grade = round($stats->avg_grade ?? 0, 2);
    $summary->total_time = 0;
    $summary->engagement_score = 0;
    $summary->logins_count = $stats->logins_count ?? 0;
    $summary->timecreated = time();
    $summary->timemodified = time();
    
    $DB->insert_record('local_aa_summary', $summary);
    cli_writeln("   ✅ Inserted summary record with {$summary->total_users} users, {$summary->active_users} active");
}

// ============================================
// 2. POPULATE local_aa_user_perf (FIXED - No NULL last_access)
// ============================================
cli_writeln("\n[2/4] Populating local_aa_user_perf...");

$DB->delete_records('local_aa_user_perf');

$users = $DB->get_records_sql("SELECT id, timecreated FROM {user} WHERE deleted = 0 AND suspended = 0");

$inserted = 0;
foreach ($users as $user) {
    // Get log count for engagement (scale to 0-100)
    $log_count = $DB->get_field_sql("
        SELECT COUNT(*) 
        FROM {logstore_standard_log} 
        WHERE userid = ? AND timecreated > ?",
        [$user->id, time() - (90 * 24 * 3600)]
    ) ?: 0;
    
    // Scale engagement score to 0-100 (max 500 logs = 100 points)
    $engagement_score = min(100, round($log_count / 5, 2));
    
    // Get completion percentage
    $total_courses = $DB->count_records('course', ['visible' => 1]);
    $completed_courses = $DB->count_records_sql("
        SELECT COUNT(DISTINCT course) 
        FROM {course_completions} 
        WHERE userid = ? AND timecompleted IS NOT NULL",
        [$user->id]
    );
    $completion_pct = $total_courses > 0 ? round(($completed_courses / $total_courses) * 100, 2) : 0;
    
    // Get average quiz score (0-100)
    $quiz_score = $DB->get_field_sql("
        SELECT AVG(qg.grade * 100.0 / q.grade) 
        FROM {quiz_grades} qg
        JOIN {quiz} q ON q.id = qg.quiz
        WHERE qg.userid = ?",
        [$user->id]
    ) ?: 0;
    $quiz_score = round(min(100, $quiz_score), 2);
    
    // Get time spent in seconds (limit to reasonable value)
    $time_spent = $DB->get_field_sql("
        SELECT COUNT(*) * 60 
        FROM {logstore_standard_log} 
        WHERE userid = ? AND timecreated > ?",
        [$user->id, time() - (90 * 24 * 3600)]
    ) ?: 0;
    $time_spent = min(999999999, $time_spent);
    
    // Get last access - FIXED: ensure it's never NULL
    $last_access = $DB->get_field_sql("
        SELECT MAX(timecreated) 
        FROM {logstore_standard_log} 
        WHERE userid = ?",
        [$user->id]
    );
    if (empty($last_access)) {
        $last_access = $user->timecreated ?: time();
    }
    
    // Get login count (last 30 days, max 30)
    $login_count = $DB->count_records_sql("
        SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(timecreated)))
        FROM {logstore_standard_log}
        WHERE userid = ? AND timecreated > ?",
        [$user->id, time() - (30 * 24 * 3600)]
    );
    $login_count = min(30, $login_count);
    
    // Determine risk level based on engagement
    if ($engagement_score < 30) {
        $risk_level = 'high';
    } elseif ($engagement_score < 50) {
        $risk_level = 'medium';
    } else {
        $risk_level = 'low';
    }
    
    // Predict completion (0-100)
    $predicted = min(100, round($completion_pct + ($engagement_score * 0.3) + ($quiz_score * 0.2), 2));
    
    $perf = new stdClass();
    $perf->userid = $user->id;
    $perf->courseid = 0;
    $perf->engagement_score = $engagement_score;
    $perf->completion_percentage = $completion_pct;
    $perf->avg_quiz_score = $quiz_score;
    $perf->time_spent = $time_spent;
    $perf->last_access = $last_access;
    $perf->login_count = $login_count;
    $perf->risk_level = $risk_level;
    $perf->predicted_completion = $predicted;
    $perf->last_calculated = time();
    $perf->timecreated = time();
    $perf->timemodified = time();
    
    try {
        $DB->insert_record('local_aa_user_perf', $perf);
        $inserted++;
    } catch (Exception $e) {
        cli_writeln("   ⚠️ Warning: Could not insert user {$user->id}: " . $e->getMessage());
    }
    
    if ($inserted % 10 == 0 && $inserted > 0) {
        cli_writeln("   Processed {$inserted} users...");
    }
}

cli_writeln("   ✅ Inserted {$inserted} user performance records");

// ============================================
// 3. POPULATE local_aa_dept_stats (FIXED)
// ============================================
cli_writeln("\n[3/4] Populating local_aa_dept_stats...");

$DB->delete_records('local_aa_dept_stats');

// Get unique departments
$departments = $DB->get_records_sql_menu("
    SELECT DISTINCT COALESCE(department, 'Unassigned') as dept_key, 
           COALESCE(department, 'Unassigned') as dept_name
    FROM {user} 
    WHERE deleted = 0 AND suspended = 0
");

$dept_inserted = 0;
foreach ($departments as $dept_key => $dept_name) {
    $dept = ($dept_name == 'Unassigned' || empty($dept_name)) ? 'Unassigned' : $dept_name;
    
    // Get users in this department
    if ($dept == 'Unassigned') {
        $user_ids = $DB->get_fieldset_sql("
            SELECT id FROM {user} 
            WHERE deleted = 0 AND suspended = 0 
            AND (department IS NULL OR department = '')
        ");
    } else {
        $user_ids = $DB->get_fieldset_sql("
            SELECT id FROM {user} 
            WHERE department = ? AND deleted = 0 AND suspended = 0",
            [$dept]
        );
    }
    
    if (empty($user_ids)) {
        continue;
    }
    
    $total = count($user_ids);
    list($insql, $params) = $DB->get_in_or_equal($user_ids);
    
    // Count active users (logged in last 30 days)
    $active = $DB->count_records_sql("
        SELECT COUNT(DISTINCT userid)
        FROM {logstore_standard_log}
        WHERE userid $insql AND timecreated > ?",
        array_merge($params, [time() - (30 * 24 * 3600)])
    );
    
    // Count users who completed at least one course
    $completed = $DB->count_records_sql("
        SELECT COUNT(DISTINCT userid)
        FROM {course_completions}
        WHERE userid $insql AND timecompleted IS NOT NULL",
        $params
    );
    
    $compliance = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    
    // Get average performance
    $avg_perf = $DB->get_field_sql("
        SELECT AVG(gg.finalgrade)
        FROM {grade_grades} gg
        JOIN {grade_items} gi ON gi.id = gg.itemid
        WHERE gg.userid $insql AND gi.itemtype = 'course' AND gg.finalgrade <= 100",
        $params
    ) ?: 0;
    $avg_perf = round($avg_perf, 2);
    
    // Count at-risk users
    $at_risk = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_aa_user_perf}
        WHERE userid $insql AND risk_level IN ('high', 'medium')",
        $params
    );
    
    // Count high performers (engagement >= 80)
    $high_performers = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_aa_user_perf}
        WHERE userid $insql AND engagement_score >= 80",
        $params
    );
    
    // Count low performers (engagement < 30)
    $low_performers = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_aa_user_perf}
        WHERE userid $insql AND engagement_score < 30",
        $params
    );
    
    $dept_stat = new stdClass();
    $dept_stat->department = $dept;
    $dept_stat->date = $today;
    $dept_stat->total_employees = $total;
    $dept_stat->trained_employees = $active;
    $dept_stat->compliance_rate = $compliance;
    $dept_stat->avg_performance = $avg_perf;
    $dept_stat->certifications_earned = $completed;
    $dept_stat->certifications_expiring = 0;
    $dept_stat->at_risk_count = $at_risk;
    $dept_stat->high_performers = $high_performers;
    $dept_stat->low_performers = $low_performers;
    $dept_stat->timecreated = time();
    $dept_stat->timemodified = time();
    
    $DB->insert_record('local_aa_dept_stats', $dept_stat);
    $dept_inserted++;
}

cli_writeln("   ✅ Inserted {$dept_inserted} department statistics records");

// ============================================
// 4. POPULATE local_aa_course_cache (FIXED)
// ============================================
cli_writeln("\n[4/4] Populating local_aa_course_cache...");

// Check if table has records
$existing = $DB->count_records('local_aa_course_cache');

if ($existing == 0) {
    $courses = $DB->get_records('course', ['visible' => 1], '', 'id');
    
    $course_inserted = 0;
    foreach ($courses as $course) {
        $context = context_course::instance($course->id);
        $enrolled = count(get_enrolled_users($context));
        
        // Count completed
        $completed = $DB->count_records('course_completions', [
            'course' => $course->id,
            'timecompleted' => ['<>' => null]
        ]);
        
        $completion_rate = $enrolled > 0 ? round(($completed / $enrolled) * 100, 2) : 0;
        
        // Get average grade - FIXED: use proper parameter
        $avg_grade_sql = "SELECT AVG(gg.finalgrade) as avg_grade
                          FROM {grade_grades} gg
                          JOIN {grade_items} gi ON gi.id = gg.itemid
                          WHERE gi.courseid = ? AND gi.itemtype = 'course' AND gg.finalgrade <= 100";
        $avg_grade_result = $DB->get_record_sql($avg_grade_sql, [$course->id]);
        $avg_grade = round($avg_grade_result->avg_grade ?? 0, 2);
        
        // Count active participants
        $active = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid)
            FROM {logstore_standard_log}
            WHERE courseid = ? AND timecreated > ?",
            [$course->id, time() - (30 * 24 * 3600)]
        );
        
        // Get last activity
        $last_activity = $DB->get_field_sql("
            SELECT MAX(timecreated)
            FROM {logstore_standard_log}
            WHERE courseid = ?",
            [$course->id]
        );
        if (empty($last_activity)) {
            $last_activity = time();
        }
        
        $cache = new stdClass();
        $cache->courseid = $course->id;
        $cache->date = $today;
        $cache->total_enrolled = $enrolled;
        $cache->completed_count = $completed;
        $cache->completion_rate = $completion_rate;
        $cache->avg_grade = $avg_grade;
        $cache->active_participants = $active;
        $cache->avg_time_spent = 0;
        $cache->last_activity = $last_activity;
        $cache->timecreated = time();
        $cache->timemodified = time();
        
        $DB->insert_record('local_aa_course_cache', $cache);
        $course_inserted++;
    }
    cli_writeln("   ✅ Inserted {$course_inserted} course cache records");
} else {
    cli_writeln("   ✅ Course cache already has {$existing} records (skipped)");
}

// ============================================
// FINAL SUMMARY
// ============================================
cli_heading("\n📊 POPULATION COMPLETE!");

$summary_count = $DB->count_records('local_aa_summary');
$perf_count = $DB->count_records('local_aa_user_perf');
$dept_count = $DB->count_records('local_aa_dept_stats');
$course_count = $DB->count_records('local_aa_course_cache');

cli_writeln("\nFinal counts:");
cli_writeln("   local_aa_summary: {$summary_count}");
cli_writeln("   local_aa_user_perf: {$perf_count}");
cli_writeln("   local_aa_dept_stats: {$dept_count}");
cli_writeln("   local_aa_course_cache: {$course_count}");

// Update last sync time
set_config('last_sync', time(), 'local_advancedanalytics');
set_config('last_sync_duration', 0, 'local_advancedanalytics');

if ($perf_count > 0) {
    cli_writeln("\n✅ All Phase 2 tables populated with REAL data from your Moodle database!");
    cli_writeln("🎉 You can now test the cached dashboard!");
} else {
    cli_writeln("\n⚠️ Some tables are empty. Please check the errors above.");
}