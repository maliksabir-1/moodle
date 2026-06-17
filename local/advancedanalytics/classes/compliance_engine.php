<?php
// local/advancedanalytics/classes/compliance_engine.php
// Compliance Calculation Engine - FULLY FIXED

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class compliance_engine {
    
    /**
     * Check if compliance tables exist
     */
    private static function tables_exist() {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists('local_aa_compliance');
    }
    
    /**
     * Calculate compliance for a user
     * @param int $userid
     * @param array $mandatory_courses (optional)
     * @return array
     */
    public static function calculate_user_compliance($userid, $mandatory_courses = null) {
        global $DB;
        
        // If compliance table doesn't exist, return default
        if (!self::tables_exist()) {
            return [
                'userid' => $userid,
                'total_mandatory' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'not_started' => 0,
                'expired' => 0,
                'expiring_soon' => 0,
                'compliance_percentage' => 100,
                'status' => 'compliant',
                'courses' => []
            ];
        }
        
        // Get mandatory courses if not provided
        if ($mandatory_courses === null) {
            $mandatory_courses = self::get_mandatory_courses();
        }
        
        $total_mandatory = count($mandatory_courses);
        $completed = 0;
        $in_progress = 0;
        $not_started = 0;
        $expired = 0;
        $expiring_soon = 0;
        
        $course_status = [];
        
        foreach ($mandatory_courses as $courseid) {
            $completion = $DB->get_record('course_completions', [
                'course' => $courseid,
                'userid' => $userid
            ]);
            
            $is_completed = ($completion && $completion->timecompleted > 0);
            $completion_date = $is_completed ? $completion->timecompleted : null;
            
            // Get course expiry (if configured)
            $expiry_date = self::get_course_expiry($courseid, $completion_date);
            $is_expired = $expiry_date && $expiry_date < time();
            $is_expiring = $expiry_date && $expiry_date < time() + (30 * 24 * 3600);
            
            $course_name = $DB->get_field('course', 'fullname', ['id' => $courseid]);
            
            $status = [
                'courseid' => $courseid,
                'course_name' => $course_name ?: 'Unknown Course',
                'completed' => $is_completed,
                'completion_date' => $completion_date ? userdate($completion_date) : null,
                'expiry_date' => $expiry_date ? userdate($expiry_date) : null,
                'status' => self::get_compliance_status($is_completed, $is_expired, $is_expiring)
            ];
            
            if ($is_completed && $is_expired) {
                $expired++;
            } elseif ($is_completed && $is_expiring) {
                $expiring_soon++;
            } elseif ($is_completed) {
                $completed++;
            } elseif ($completion && $completion->timecompleted === null) {
                $in_progress++;
            } else {
                $not_started++;
            }
            
            $course_status[] = $status;
        }
        
        $compliance_percentage = $total_mandatory > 0 ? round(($completed / $total_mandatory) * 100, 2) : 100;
        
        // Determine status based on percentage and user age (30-day grace period)
        $user = $DB->get_record('user', ['id' => $userid], 'id, timecreated');
        $grace_period = 30 * 24 * 3600;
        $is_old_user = $user && ($user->timecreated < (time() - $grace_period));

        if ($compliance_percentage >= 100 && $expired == 0) {
            $overall_status = 'compliant';
        } else {
            // New logic: Only mark as overdue if user is OLD (>30 days) AND has incomplete items
            if ($is_old_user) {
                $overall_status = 'overdue';
            } else {
                $overall_status = 'pending';
            }
        }
        
        return [
            'userid' => $userid,
            'total_mandatory' => $total_mandatory,
            'completed' => $completed,
            'in_progress' => $in_progress,
            'not_started' => $not_started,
            'expired' => $expired,
            'expiring_soon' => $expiring_soon,
            'compliance_percentage' => $compliance_percentage,
            'status' => $overall_status,
            'courses' => $course_status
        ];
    }
    
    /**
     * Calculate compliance for all users
     * @param int $departmentid (optional)
     * @return array
     */
    public static function calculate_bulk_compliance($departmentid = null) {
        global $DB;
        
        if (!self::tables_exist()) {
            return [];
        }
        
        $params = [];
        $sql = "SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0";
        
        if ($departmentid) {
            $sql .= " AND department = :department";
            $params['department'] = $departmentid;
        }
        
        $users = $DB->get_records_sql($sql, $params);
        $mandatory_courses = self::get_mandatory_courses();
        
        $compliance_data = [];
        foreach ($users as $user) {
            $compliance_data[$user->id] = self::calculate_user_compliance($user->id, $mandatory_courses);
        }
        
        return $compliance_data;
    }
    
    /**
     * Get department compliance summary
     * @return array
     */
    public static function get_department_compliance() {
        global $DB;
        
        if (!self::tables_exist()) {
            return [];
        }
        
        $departments = $DB->get_records_sql_menu("
            SELECT DISTINCT department, department 
            FROM {user} 
            WHERE department IS NOT NULL AND department != '' AND deleted = 0
        ");
        
        $summary = [];
        foreach ($departments as $dept) {
            $users = $DB->get_records_sql("
                SELECT id FROM {user} 
                WHERE department = :dept AND deleted = 0 AND suspended = 0
            ", ['dept' => $dept]);
            
            $user_compliance = [];
            $mandatory_courses = self::get_mandatory_courses();
            foreach ($users as $user) {
                $user_compliance[] = self::calculate_user_compliance($user->id, $mandatory_courses);
            }
            
            $total_users = count($user_compliance);
            $fully_compliant = count(array_filter($user_compliance, function($c) {
                return $c['compliance_percentage'] >= 90;
            }));
            $partially_compliant = count(array_filter($user_compliance, function($c) {
                return $c['compliance_percentage'] >= 50 && $c['compliance_percentage'] < 90;
            }));
            $non_compliant = count(array_filter($user_compliance, function($c) {
                return $c['compliance_percentage'] < 50;
            }));
            
            $avg_compliance = $total_users > 0 ? array_sum(array_column($user_compliance, 'compliance_percentage')) / $total_users : 0;
            
            $summary[] = [
                'department' => $dept,
                'total_users' => $total_users,
                'fully_compliant' => $fully_compliant,
                'partially_compliant' => $partially_compliant,
                'non_compliant' => $non_compliant,
                'average_compliance' => round($avg_compliance, 2),
                'expiring_certifications' => self::get_expiring_certifications_by_dept($dept)
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get compliance alerts (expiring/overdue)
     * @return array
     */
    public static function get_compliance_alerts() {
        global $DB;
        
        if (!self::tables_exist()) {
            return ['overdue' => [], 'expiring_soon' => [], 'not_started' => []];
        }
        
        $mandatory_courses = self::get_mandatory_courses();
        $alerts = [
            'overdue' => [],
            'expiring_soon' => [],
            'not_started' => []
        ];
        
        $users = $DB->get_records_sql("SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0");
        
        foreach ($users as $user) {
            $compliance = self::calculate_user_compliance($user->id, $mandatory_courses);
            
            foreach ($compliance['courses'] as $course) {
                if ($course['status'] == 'expired') {
                    $alerts['overdue'][] = [
                        'userid' => $user->id,
                        'user_name' => self::get_user_name($user->id),
                        'course_name' => $course['course_name'],
                        'expiry_date' => $course['expiry_date']
                    ];
                } elseif ($course['status'] == 'expiring') {
                    $alerts['expiring_soon'][] = [
                        'userid' => $user->id,
                        'user_name' => self::get_user_name($user->id),
                        'course_name' => $course['course_name'],
                        'expiry_date' => $course['expiry_date']
                    ];
                } elseif (!$course['completed'] && $course['status'] == 'not_started') {
                    $alerts['not_started'][] = [
                        'userid' => $user->id,
                        'user_name' => self::get_user_name($user->id),
                        'course_name' => $course['course_name']
                    ];
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get compliance summary for dashboard
     * @return array
     */
    public static function get_compliance_summary() {
        global $DB;
        
        if (!self::tables_exist()) {
            return [
                'total_mandatory_courses' => 0,
                'total_users_tracked' => 0,
                'fully_compliant' => 0,
                'partially_compliant' => 0,
                'non_compliant' => 0,
                'expired_certifications' => 0,
                'expiring_soon' => 0
            ];
        }
        
        $mandatory_courses = self::get_mandatory_courses();
        $total_mandatory = count($mandatory_courses);
        
        if ($total_mandatory == 0) {
            return [
                'total_mandatory_courses' => 0,
                'total_users_tracked' => 0,
                'fully_compliant' => 0,
                'partially_compliant' => 0,
                'non_compliant' => 0,
                'expired_certifications' => 0,
                'expiring_soon' => 0
            ];
        }
        
        // Get users with enrollments in mandatory courses
        $in_sql = implode(',', array_fill(0, $total_mandatory, '?'));
        $users = $DB->get_records_sql("
            SELECT DISTINCT u.id
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE u.deleted = 0 AND u.suspended = 0
            AND e.courseid IN ($in_sql)
        ", $mandatory_courses);
        
        $fully_compliant = 0;
        $partially_compliant = 0;
        $non_compliant = 0;
        $expired_total = 0;
        $expiring_total = 0;
        
        foreach ($users as $user) {
            $compliance = self::calculate_user_compliance($user->id, $mandatory_courses);
            
            if ($compliance['compliance_percentage'] >= 100) {
                $fully_compliant++;
            } elseif ($compliance['compliance_percentage'] >= 50) {
                $partially_compliant++;
            } else {
                $non_compliant++;
            }
            
            $expired_total += $compliance['expired'];
            $expiring_total += $compliance['expiring_soon'];
        }
        
        return [
            'total_mandatory_courses' => $total_mandatory,
            'total_users_tracked' => count($users),
            'fully_compliant' => $fully_compliant,
            'partially_compliant' => $partially_compliant,
            'non_compliant' => $non_compliant,
            'expired_certifications' => $expired_total,
            'expiring_soon' => $expiring_total
        ];
    }
    
    /**
     * Mark a course as mandatory
     * @param int $courseid
     * @param int $expiry_days
     * @return bool
     */
    public static function set_mandatory_course($courseid, $expiry_days = null) {
        global $DB;
        
        if (!self::tables_exist()) {
            return false;
        }
        
        $record = [
            'courseid' => $courseid,
            'is_mandatory' => 1,
            'expiry_days' => $expiry_days,
            'timecreated' => time(),
            'timemodified' => time()
        ];
        
        $existing = $DB->get_record('local_aa_compliance', ['courseid' => $courseid]);
        
        if ($existing) {
            $record['id'] = $existing->id;
            return $DB->update_record('local_aa_compliance', $record);
        } else {
            return $DB->insert_record('local_aa_compliance', $record);
        }
    }
    
    /**
     * Remove mandatory status from course
     * @param int $courseid
     * @return bool
     */
    public static function remove_mandatory_course($courseid) {
        global $DB;
        
        if (!self::tables_exist()) {
            return false;
        }
        
        return $DB->delete_records('local_aa_compliance', ['courseid' => $courseid]);
    }
    
    /**
     * Get all mandatory courses
     * @return array
     */
    public static function get_mandatory_courses() {
        global $DB;
        
        if (!self::tables_exist()) {
            return [];
        }
        
        $courses = $DB->get_records_sql("
            SELECT courseid FROM {local_aa_compliance} WHERE is_mandatory = 1
        ");
        
        return array_keys($courses);
    }
    
    /**
     * Update compliance in cache table
     * @return int number of users updated
     */
    public static function update_compliance_cache() {
        global $DB;
        
        if (!self::tables_exist() || !$DB->get_manager()->table_exists('local_aa_user_compliance')) {
            return 0;
        }
        
        $users = $DB->get_records_sql("SELECT id FROM {user} WHERE deleted = 0 AND suspended = 0");
        $mandatory_courses = self::get_mandatory_courses();
        
        $count = 0;
        foreach ($users as $user) {
            $compliance = self::calculate_user_compliance($user->id, $mandatory_courses);
            
            $record = [
                'userid' => $user->id,
                'compliance_percentage' => $compliance['compliance_percentage'],
                'completed_count' => $compliance['completed'],
                'total_mandatory' => $compliance['total_mandatory'],
                'expired_count' => $compliance['expired'],
                'status' => $compliance['status'],
                'timemodified' => time()
            ];
            
            $existing = $DB->get_record('local_aa_user_compliance', ['userid' => $user->id]);
            
            if ($existing) {
                $record['id'] = $existing->id;
                $DB->update_record('local_aa_user_compliance', $record);
            } else {
                $record['timecreated'] = time();
                $DB->insert_record('local_aa_user_compliance', $record);
            }
            $count++;
        }
        
        return $count;
    }
    
    // ============================================
    // PRIVATE METHODS
    // ============================================
    
    private static function get_course_expiry($courseid, $completion_date) {
        global $DB;
        
        if (!self::tables_exist()) {
            return null;
        }
        
        $compliance = $DB->get_record('local_aa_compliance', ['courseid' => $courseid]);
        
        if (!$compliance || !$compliance->expiry_days || !$completion_date) {
            return null;
        }
        
        return $completion_date + ($compliance->expiry_days * 24 * 3600);
    }
    
    private static function get_compliance_status($is_completed, $is_expired = false, $is_expiring = false) {
        if ($is_completed && $is_expired) return 'expired';
        if ($is_completed && $is_expiring) return 'expiring';
        if ($is_completed) return 'compliant';
        return 'not_started';
    }
    
    private static function get_user_name($userid) {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid]);
        return $user ? fullname($user) : 'Unknown User';
    }
    
    private static function get_expiring_certifications_by_dept($department) {
        global $DB;
        
        if (!self::tables_exist()) {
            return 0;
        }
        
        return $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {local_aa_user_compliance} c
            JOIN {user} u ON u.id = c.userid
            WHERE u.department = :dept AND c.status = 'warning'
        ", ['dept' => $department]);
    }
}