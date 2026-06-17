<?php
// local/advancedanalytics/classes/analytics_engine.php
// Core analytics calculations - FULLY FIXED VERSION

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class analytics_engine {
    
    /**
     * Get total users count with filters
     */
    public static function get_total_users($dept = '', $courseid = 0, $role_filter = 0, $search = '') {
        global $DB;
        
        $params = [];
        $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        
        if ($dept) {
            if ($dept === 'Unassigned') {
                $where .= " AND (u.department IS NULL OR u.department = '')";
            } else {
                $where .= " AND u.department = ?";
                $params[] = $dept;
            }
        }
        
        if ($courseid) {
            $where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $params[] = $courseid;
        }
        
        if ($role_filter) {
            $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $params[] = $role_filter;
        }
        
        if ($search) {
            $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        return $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) FROM {user} u WHERE $where", $params);
    }
    
    /**
     * Get active users in last 30 days with filters
     */
    public static function get_active_users($dept = '', $courseid = 0, $role_filter = 0, $search = '') {
        global $DB;
        
        $params = [];
        $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        
        if ($dept) {
            if ($dept === 'Unassigned') {
                $where .= " AND (u.department IS NULL OR u.department = '')";
            } else {
                $where .= " AND u.department = ?";
                $params[] = $dept;
            }
        }
        
        if ($courseid) {
            $where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $params[] = $courseid;
        }
        
        if ($role_filter) {
            $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $params[] = $role_filter;
        }
        
        if ($search) {
            $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $cutoff = time() - (30 * 24 * 3600);
        $sql = "SELECT COUNT(DISTINCT u.id) 
                FROM {user} u 
                JOIN {logstore_standard_log} l ON l.userid = u.id 
                WHERE $where AND l.timecreated > ?";
                
        return $DB->count_records_sql($sql, array_merge($params, [$cutoff]));
    }
    
    /**
     * Get course completion rate with filters
     */
    public static function get_completion_rate($dept = '', $courseid = 0, $role_filter = 0, $search = '') {
        $total_users = self::get_total_users($dept, $courseid, $role_filter, $search);
        
        if ($total_users == 0) {
            return 0;
        }
        
        global $DB;
        $params = [];
        $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        
        if ($dept) {
            if ($dept === 'Unassigned') {
                $where .= " AND (u.department IS NULL OR u.department = '')";
            } else {
                $where .= " AND u.department = ?";
                $params[] = $dept;
            }
        }
        
        if ($courseid) {
            $where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $params[] = $courseid;
        }
        
        if ($role_filter) {
            $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $params[] = $role_filter;
        }
        
        if ($search) {
            $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql = "SELECT COUNT(DISTINCT u.id) 
                FROM {user} u 
                JOIN {course_completions} cc ON cc.userid = u.id 
                WHERE $where AND cc.timecompleted IS NOT NULL";
        
        $completed_users = $DB->count_records_sql($sql, $params);
        
        return round(($completed_users / $total_users) * 100, 1);
    }
    
    /**
     * Get average grade
     * FIXED: Now correctly averages grades between 0-100
     */
    public static function get_average_grade($dept = '', $courseid = 0, $role_filter = 0, $search = '') {
        global $DB;
        
        $params = [];
        $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest' AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL";
        
        if ($dept) {
            if ($dept === 'Unassigned') {
                $where .= " AND (u.department IS NULL OR u.department = '')";
            } else {
                $where .= " AND u.department = ?";
                $params[] = $dept;
            }
        }
        
        if ($courseid) {
            $where .= " AND gi.courseid = ?";
            $params[] = $courseid;
        }
        
        if ($role_filter) {
            $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $params[] = $role_filter;
        }
        
        if ($search) {
            $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT AVG(
                    CASE 
                        WHEN gg.finalgrade > 100 THEN 100 
                        WHEN gg.finalgrade < 0 THEN 0 
                        ELSE gg.finalgrade 
                    END
                ) as avg_grade
                FROM {grade_grades} gg
                JOIN {user} u ON u.id = gg.userid
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE $where";
        
        $result = $DB->get_record_sql($sql, $params);
        return round($result->avg_grade ?? 0, 1);
    }
    
    /**
     * Get course engagement score
     * @param int $courseid
     * @param int $days
     * @return float
     */
    public static function get_course_engagement($courseid, $days = 30) {
        global $DB;
        
        $cutoff = time() - ($days * 24 * 3600);
        
        // Get total enrolled users
        $total_enrolled = $DB->count_records_sql("
            SELECT COUNT(DISTINCT ue.userid)
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = ? AND e.status = 0
        ", [$courseid]);
        
        if ($total_enrolled == 0) {
            return 0;
        }
        
        // Get active users in period
        $active_users = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid)
            FROM {logstore_standard_log}
            WHERE courseid = ? AND timecreated > ?
        ", [$courseid, $cutoff]);
        
        // Get total actions
        $total_actions = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {logstore_standard_log}
            WHERE courseid = ? AND timecreated > ?
        ", [$courseid, $cutoff]);
        
        // Calculate engagement score (0-100)
        $participation_score = ($active_users / $total_enrolled) * 100;
        $action_score = min(100, ($total_actions / ($total_enrolled * 10)) * 100);
        
        return round(($participation_score * 0.6) + ($action_score * 0.4), 2);
    }
    
    /**
     * Get course completion rate for a specific course
     */
    public static function get_course_completion_rate($courseid) {
        global $DB;
        
        $total_enrolled = $DB->count_records_sql("
            SELECT COUNT(DISTINCT ue.userid)
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = ?
        ", [$courseid]);
        
        $completed = $DB->count_records_select('course_completions', 'course = ? AND timecompleted IS NOT NULL', [$courseid]);
        
        if ($total_enrolled > 0) {
            return round(($completed / $total_enrolled) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Get all KPIs as array
     */
    public static function get_all_kpis($dept = '', $courseid = 0, $role_filter = 0, $search = '') {
        return [
            'total_users' => self::get_total_users($dept, $courseid, $role_filter, $search),
            'active_users' => self::get_active_users($dept, $courseid, $role_filter, $search),
            'completion_rate' => self::get_completion_rate($dept, $courseid, $role_filter, $search),
            'average_grade' => self::get_average_grade($dept, $courseid, $role_filter, $search),
        ];
    }
    
    /**
     * Get user-specific performance data
     */
    public static function get_user_performance($userid) {
        global $DB;
        
        // Get user's completed courses
        $completed_courses = $DB->count_records_select('course_completions', 'userid = ? AND timecompleted IS NOT NULL', [$userid]);
        
        // Get user's average grade
        $avg_grade = $DB->get_field_sql("
            SELECT AVG(
                CASE 
                    WHEN gg.finalgrade > 100 THEN 100 
                    WHEN gg.finalgrade < 0 THEN 0 
                    ELSE gg.finalgrade 
                END
            )
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gg.userid = ?
            AND gi.itemtype = 'course'
            AND gg.finalgrade IS NOT NULL
        ", [$userid]);
        
        // Get user's total time spent (in minutes) - approximate
        $time_spent = $DB->get_field_sql("
            SELECT COUNT(*) * 5  -- Approximate 5 minutes per log entry
            FROM {logstore_standard_log}
            WHERE userid = ?
            AND timecreated > ?
        ", [$userid, time() - (90 * 24 * 3600)]);
        
        return (object)[
            'completed_courses' => $completed_courses,
            'avg_grade' => round($avg_grade ?? 0, 1),
            'time_spent_minutes' => $time_spent ?? 0,
        ];
    }
}