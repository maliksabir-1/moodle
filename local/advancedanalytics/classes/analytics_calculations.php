<?php
// local/advancedanalytics/classes/analytics_calculations.php
// Core Analytics Calculations Service - FULLY FIXED

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class analytics_calculations {
    
    /**
     * Calculate completion rate for courses
     * @param int $courseid (optional) - specific course, null for all
     * @param string $period (day, week, month, year, all)
     * @return array
     */
    public static function calculate_completion_rate($courseid = null, $period = 'all') {
        global $DB;
        
        $params = [];
        $date_condition = self::get_date_condition($period, $params);
        
        if ($courseid) {
            $params['courseid'] = $courseid;
            
            // Course-specific completion
            $sql = "SELECT 
                        COUNT(DISTINCT ue.userid) as total_enrolled,
                        COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN ue.userid END) as completed
                    FROM {enrol} e
                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
                    WHERE e.courseid = :courseid AND e.status = 0
                    $date_condition";
            
            $result = $DB->get_record_sql($sql, $params);
            
            return [
                'total_enrolled' => (int)$result->total_enrolled,
                'completed' => (int)$result->completed,
                'rate' => $result->total_enrolled > 0 ? round(($result->completed / $result->total_enrolled) * 100, 2) : 0,
                'remaining' => $result->total_enrolled - $result->completed
            ];
        } else {
            // FIXED: Overall completion - users who completed at least one course
            $sql = "SELECT 
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN u.id END) as users_completed,
                        COUNT(DISTINCT cc.course) as courses_completed,
                        COUNT(DISTINCT cc.id) as total_completions
                    FROM {user} u
                    LEFT JOIN {course_completions} cc ON cc.userid = u.id
                    WHERE u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'
                    $date_condition";
            
            $result = $DB->get_record_sql($sql, $params);
            
            return [
                'total_users' => (int)$result->total_users,
                'users_completed' => (int)$result->users_completed,
                'completion_rate' => $result->total_users > 0 ? round(($result->users_completed / $result->total_users) * 100, 2) : 0,
                'courses_completed' => (int)$result->courses_completed,
                'total_completions' => (int)$result->total_completions
            ];
        }
    }
    
    /**
     * Calculate certification rate
     * @return array
     */
    public static function calculate_certification_rate() {
        global $DB;
        
        $total_users = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
        
        // Check if customcert tables exist
        $dbman = $DB->get_manager();
        $certified_users = 0;
        
        if ($dbman->table_exists('customcert_issues')) {
            $certified_users = $DB->count_records_sql("SELECT COUNT(DISTINCT userid) FROM {customcert_issues}");
        }
        
        return [
            'total_users' => $total_users,
            'certified_users' => $certified_users,
            'certification_rate' => $total_users > 0 ? round(($certified_users / $total_users) * 100, 2) : 0
        ];
    }

    /**
     * Calculate active users count
     * @param string $period (day, week, month, year)
     * @param int $courseid (optional)
     * @return array
     */
    public static function calculate_active_users($period = 'month', $courseid = null) {
        global $DB;
        
        $cutoff = self::get_cutoff_time($period);
        $params = ['cutoff' => $cutoff];
        
        $sql = "SELECT COUNT(DISTINCT l.userid) as active_users
                FROM {logstore_standard_log} l
                JOIN {user} u ON u.id = l.userid
                WHERE l.timecreated > :cutoff
                AND u.deleted = 0 AND u.suspended = 0";
        
        if ($courseid) {
            $sql .= " AND l.courseid = :courseid";
            $params['courseid'] = $courseid;
        }
        
        $result = $DB->get_record_sql($sql, $params);
        
        // Get total users for comparison
        $total_users = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
        
        return [
            'active_users' => (int)$result->active_users,
            'total_users' => $total_users,
            'activity_rate' => $total_users > 0 ? round(($result->active_users / $total_users) * 100, 2) : 0,
            'period' => $period,
            'cutoff_date' => date('Y-m-d H:i:s', $cutoff)
        ];
    }
    
    /**
     * Calculate course engagement score
     * @param int $courseid
     * @param string $period
     * @return array
     */
    public static function calculate_course_engagement($courseid, $period = 'month') {
        global $DB;
        
        $cutoff = self::get_cutoff_time($period);
        
        // Get course activity metrics
        $metrics = $DB->get_record_sql("
            SELECT 
                COUNT(DISTINCT l.userid) as unique_users,
                COUNT(l.id) as total_actions,
                COUNT(DISTINCT DATE(FROM_UNIXTIME(l.timecreated))) as active_days
            FROM {logstore_standard_log} l
            WHERE l.courseid = :courseid AND l.timecreated > :cutoff
        ", ['courseid' => $courseid, 'cutoff' => $cutoff]);
        
        // Get total enrolled users
        $enrolled = $DB->count_records_sql("
            SELECT COUNT(DISTINCT ue.userid)
            FROM {enrol} e
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE e.courseid = :courseid AND e.status = 0
        ", ['courseid' => $courseid]);
        
        if ($enrolled == 0) {
            return [
                'courseid' => $courseid,
                'total_enrolled' => 0,
                'active_users' => 0,
                'total_actions' => 0,
                'active_days' => 0,
                'participation_rate' => 0,
                'engagement_score' => 0,
                'level' => 'No Data'
            ];
        }
        
        // Calculate engagement score (0-100)
        $participation_rate = ($metrics->unique_users / $enrolled) * 100;
        $action_score = min(100, ($metrics->total_actions / ($enrolled * 10)) * 100);
        $consistency_score = min(100, ($metrics->active_days / 30) * 100);
        
        $engagement_score = round(($participation_rate * 0.4) + ($action_score * 0.4) + ($consistency_score * 0.2), 2);
        
        return [
            'courseid' => $courseid,
            'total_enrolled' => $enrolled,
            'active_users' => (int)$metrics->unique_users,
            'total_actions' => (int)$metrics->total_actions,
            'active_days' => (int)$metrics->active_days,
            'participation_rate' => round($participation_rate, 2),
            'engagement_score' => $engagement_score,
            'level' => self::get_engagement_level($engagement_score)
        ];
    }
    
    /**
     * Calculate average score for courses
     * @param int $courseid (optional)
     * @param string $period
     * @return array
     */
    public static function calculate_average_score($courseid = null, $period = 'all') {
        global $DB;
        
        $params = [];
        $date_condition = self::get_date_condition($period, $params);
        
        if ($courseid) {
            $params['courseid'] = $courseid;
            $sql = "SELECT 
                        AVG(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as avg_grade,
                        MIN(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as min_grade,
                        MAX(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as max_grade,
                        COUNT(DISTINCT gg.userid) as graded_users,
                        COUNT(DISTINCT ue.userid) as total_enrolled
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    LEFT JOIN {enrol} e ON e.courseid = gi.courseid
                    LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    WHERE gi.courseid = :courseid AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                    $date_condition";
            
            $result = $DB->get_record_sql($sql, $params);
            
            return [
                'courseid' => $courseid,
                'average_grade' => round($result->avg_grade ?? 0, 2),
                'min_grade' => round($result->min_grade ?? 0, 2),
                'max_grade' => round($result->max_grade ?? 0, 2),
                'graded_users' => (int)$result->graded_users,
                'total_enrolled' => (int)$result->total_enrolled,
                'grading_rate' => $result->total_enrolled > 0 ? round(($result->graded_users / $result->total_enrolled) * 100, 2) : 0
            ];
        } else {
            // Overall average
            $sql = "SELECT 
                        AVG(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as overall_avg,
                        COUNT(DISTINCT gi.courseid) as courses_with_grades,
                        COUNT(DISTINCT gg.userid) as graded_users
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid
                    WHERE gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                    $date_condition";
            
            $result = $DB->get_record_sql($sql, $params);
            
            return [
                'overall_average' => round($result->overall_avg ?? 0, 2),
                'courses_with_grades' => (int)$result->courses_with_grades,
                'graded_users' => (int)$result->graded_users,
                'grade_distribution' => self::get_grade_distribution($date_condition, $params)
            ];
        }
    }
    
    /**
     * Get all KPIs in one call
     * @param int $courseid (optional)
     * @return array
     */
    public static function get_all_kpis($courseid = null) {
        $completion = self::calculate_completion_rate($courseid);
        $active = self::calculate_active_users('month', $courseid);
        $average = self::calculate_average_score($courseid);
        
        if ($courseid) {
            $engagement = self::calculate_course_engagement($courseid);
            return [
                'completion' => $completion,
                'active_users' => $active,
                'average_grade' => $average,
                'engagement' => $engagement
            ];
        }
        
        return [
            'completion' => $completion,
            'active_users' => $active,
            'average_grade' => $average
        ];
    }
    
    /**
     * Get KPI trends over time
     * @param string $metric (completion, active, grade)
     * @param int $months (3,6,12)
     * @return array
     */
    public static function get_kpi_trends($metric = 'completion', $months = 6) {
        global $DB;
        
        $trends = [];
        
        for ($i = $months; $i >= 0; $i--) {
            $month_start = strtotime("-$i months", strtotime(date('Y-m-01')));
            $month_end = strtotime('+1 month', $month_start) - 1;
            $month_label = date('M Y', $month_start);
            
            switch ($metric) {
                case 'completion':
                    $value = self::calculate_completion_rate_for_period($month_start, $month_end);
                    break;
                case 'active':
                    $value = self::calculate_active_users_for_period($month_start, $month_end);
                    break;
                case 'grade':
                    $value = self::calculate_average_grade_for_period($month_start, $month_end);
                    break;
                default:
                    $value = 0;
            }
            
            $trends[] = [
                'month' => $month_label,
                'value' => $value,
                'timestamp' => $month_start
            ];
        }
        
        return $trends;
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    private static function get_cutoff_time($period) {
        switch ($period) {
            case 'day': return time() - (24 * 3600);
            case 'week': return time() - (7 * 24 * 3600);
            case 'month': return time() - (30 * 24 * 3600);
            case 'year': return time() - (365 * 24 * 3600);
            default: return 0;
        }
    }
    
    private static function get_date_condition($period, &$params) {
        if ($period == 'all') return '';
        
        $cutoff = self::get_cutoff_time($period);
        $params['date_cutoff'] = $cutoff;
        return " AND timecreated > :date_cutoff";
    }
    
    private static function get_engagement_level($score) {
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Average';
        if ($score >= 20) return 'Low';
        return 'Very Low';
    }
    
    private static function get_grade_distribution($date_condition, $params) {
        global $DB;
        
        $sql = "SELECT 
                    CASE 
                        WHEN gg.finalgrade >= 90 THEN 'A+'
                        WHEN gg.finalgrade >= 80 THEN 'A'
                        WHEN gg.finalgrade >= 70 THEN 'B'
                        WHEN gg.finalgrade >= 60 THEN 'C'
                        WHEN gg.finalgrade >= 50 THEN 'D'
                        ELSE 'F'
                    END as grade_letter,
                    COUNT(DISTINCT gg.userid) as count
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                $date_condition
                GROUP BY grade_letter";
        
        $distribution = $DB->get_records_sql($sql, $params);
        
        $result = ['A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        foreach ($distribution as $d) {
            $result[$d->grade_letter] = $d->count;
        }
        
        return $result;
    }
    
    private static function calculate_completion_rate_for_period($start, $end) {
        global $DB;
        
        $sql = "SELECT COUNT(DISTINCT u.id) as total,
                       COUNT(DISTINCT CASE WHEN cc.timecompleted BETWEEN :start AND :end THEN u.id END) as completed
                FROM {user} u
                LEFT JOIN {course_completions} cc ON cc.userid = u.id
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        
        $result = $DB->get_record_sql($sql, ['start' => $start, 'end' => $end]);
        
        return $result->total > 0 ? round(($result->completed / $result->total) * 100, 2) : 0;
    }
    
    private static function calculate_active_users_for_period($start, $end) {
        global $DB;
        
        return $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid)
            FROM {logstore_standard_log}
            WHERE timecreated BETWEEN :start AND :end
        ", ['start' => $start, 'end' => $end]);
    }
    
    private static function calculate_average_grade_for_period($start, $end) {
        global $DB;
        
        $sql = "SELECT AVG(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as avg_grade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                AND gg.timecreated BETWEEN :start AND :end";
        
        $result = $DB->get_record_sql($sql, ['start' => $start, 'end' => $end]);
        
        return round($result->avg_grade ?? 0, 2);
    }
}