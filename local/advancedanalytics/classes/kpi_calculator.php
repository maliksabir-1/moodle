<?php
// local/advancedanalytics/classes/kpi_calculator.php
// KPI Calculator Service

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class kpi_calculator {
    
    /**
     * Get all KPIs for dashboard
     * @return array
     */
    public static function get_all_kpis() {
        return [
            'overview' => self::get_overview_kpis(),
            'trends' => self::get_trend_kpis(),
            'performance' => self::get_performance_kpis(),
            'engagement' => self::get_engagement_kpis()
        ];
    }
    
    /**
     * Get overview KPIs (real-time)
     * @return array
     */
    public static function get_overview_kpis() {
        global $DB;
        
        // Total users
        $total_users = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
        
        // Active users (last 30 days)
        $active_users = $DB->count_records_sql("
            SELECT COUNT(DISTINCT userid)
            FROM {logstore_standard_log}
            WHERE timecreated > :cutoff
        ", ['cutoff' => time() - (30 * 24 * 3600)]);
        
        // Total courses
        $total_courses = $DB->count_records('course', ['visible' => 1]);
        
        // Completion rate
        $completion_stats = \local_advancedanalytics\analytics_calculations::calculate_completion_rate();
        
        // Average grade
        $grade_stats = \local_advancedanalytics\analytics_calculations::calculate_average_score();
        
        // Total enrollments
        $total_enrollments = $DB->count_records('user_enrolments');
        
        // Total logins last 30 days
        $total_logins = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {logstore_standard_log}
            WHERE action = 'loggedin' AND timecreated > :cutoff
        ", ['cutoff' => time() - (30 * 24 * 3600)]);
        
        return [
            'total_users' => $total_users,
            'active_users' => $active_users,
            'total_courses' => $total_courses,
            'completion_rate' => $completion_stats['completion_rate'],
            'average_grade' => $grade_stats['overall_average'] ?? 0,
            'total_enrollments' => $total_enrollments,
            'total_logins_30days' => $total_logins,
            'users_completed' => $completion_stats['users_completed'],
            'total_completions' => $completion_stats['total_completions']
        ];
    }
    
    /**
     * Get trend KPIs (historical data)
     * @return array
     */
    public static function get_trend_kpis() {
        $months = 6;
        
        $completion_trend = \local_advancedanalytics\analytics_calculations::get_kpi_trends('completion', $months);
        $active_trend = \local_advancedanalytics\analytics_calculations::get_kpi_trends('active', $months);
        $grade_trend = \local_advancedanalytics\analytics_calculations::get_kpi_trends('grade', $months);
        
        // Calculate growth rates
        $completion_growth = self::calculate_growth_rate($completion_trend);
        $active_growth = self::calculate_growth_rate($active_trend);
        $grade_growth = self::calculate_growth_rate($grade_trend);
        
        return [
            'completion_trend' => $completion_trend,
            'active_users_trend' => $active_trend,
            'grade_trend' => $grade_trend,
            'completion_growth' => $completion_growth,
            'active_users_growth' => $active_growth,
            'grade_growth' => $grade_growth
        ];
    }
    
    /**
     * Get performance KPIs
     * @param int $courseid (optional)
     * @return array
     */
    public static function get_performance_kpis($courseid = null) {
        global $DB;
        
        // Top performing courses
        $top_courses = $DB->get_records_sql("
            SELECT 
                c.id,
                c.fullname,
                COALESCE(cc.completion_rate, 0) as completion_rate,
                COALESCE(cc.avg_grade, 0) as avg_grade,
                COALESCE(cc.total_enrolled, 0) as enrolled
            FROM {course} c
            LEFT JOIN {local_aa_course_cache} cc ON cc.courseid = c.id
            WHERE c.visible = 1
            ORDER BY completion_rate DESC, avg_grade DESC
            LIMIT 5
        ");
        
        // Bottom performing courses
        $bottom_courses = $DB->get_records_sql("
            SELECT 
                c.id,
                c.fullname,
                COALESCE(cc.completion_rate, 0) as completion_rate,
                COALESCE(cc.avg_grade, 0) as avg_grade,
                COALESCE(cc.total_enrolled, 0) as enrolled
            FROM {course} c
            LEFT JOIN {local_aa_course_cache} cc ON cc.courseid = c.id
            WHERE c.visible = 1 AND cc.total_enrolled > 0
            ORDER BY completion_rate ASC, avg_grade ASC
            LIMIT 5
        ");
        
        // Top learners
        $top_learners = $DB->get_records_sql("
            SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                COUNT(DISTINCT cc.course) as courses_completed,
                ROUND(AVG(up.engagement_score), 1) as avg_engagement
            FROM {user} u
            LEFT JOIN {course_completions} cc ON cc.userid = u.id
            LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id
            WHERE u.deleted = 0 AND u.suspended = 0
            GROUP BY u.id, u.firstname, u.lastname, u.email
            HAVING courses_completed > 0
            ORDER BY courses_completed DESC, avg_engagement DESC
            LIMIT 10
        ");
        
        return [
            'top_courses' => array_values($top_courses),
            'bottom_courses' => array_values($bottom_courses),
            'top_learners' => array_values($top_learners),
            'total_courses_with_data' => $DB->count_records('local_aa_course_cache')
        ];
    }
    
    /**
     * Get engagement KPIs
     * @return array
     */
    public static function get_engagement_kpis() {
        global $DB;
        
        // Overall engagement score
        $overall_engagement = $DB->get_field_sql("
            SELECT AVG(engagement_score)
            FROM {local_aa_user_perf}
        ") ?: 0;
        
        // Risk distribution
        $risk_distribution = $DB->get_records_sql("
            SELECT risk_level, COUNT(*) as count
            FROM {local_aa_user_perf}
            WHERE risk_level IS NOT NULL
            GROUP BY risk_level
        ");
        
        // Engagement by department
        $engagement_by_dept = $DB->get_records_sql("
            SELECT 
                COALESCE(u.department, 'Unassigned') as department,
                ROUND(AVG(up.engagement_score), 1) as avg_engagement,
                COUNT(*) as user_count
            FROM {local_aa_user_perf} up
            JOIN {user} u ON u.id = up.userid
            WHERE u.deleted = 0
            GROUP BY COALESCE(u.department, 'Unassigned')
            ORDER BY avg_engagement DESC
        ");
        
        // Daily active users trend (last 30 days)
        $daily_active = $DB->get_records_sql("
            SELECT 
                DATE(FROM_UNIXTIME(timecreated)) as date,
                COUNT(DISTINCT userid) as active_count
            FROM {logstore_standard_log}
            WHERE timecreated > :cutoff
            GROUP BY DATE(FROM_UNIXTIME(timecreated))
            ORDER BY date ASC
        ", ['cutoff' => time() - (30 * 24 * 3600)]);
        
        return [
            'overall_engagement' => round($overall_engagement, 2),
            'risk_distribution' => array_values($risk_distribution),
            'engagement_by_department' => array_values($engagement_by_dept),
            'daily_active_trend' => array_values($daily_active),
            'high_risk_count' => $risk_distribution['high']->count ?? 0,
            'medium_risk_count' => $risk_distribution['medium']->count ?? 0,
            'low_risk_count' => $risk_distribution['low']->count ?? 0
        ];
    }
    
    /**
     * Calculate growth rate between first and last month
     * @param array $trend
     * @return float
     */
    private static function calculate_growth_rate($trend) {
        if (count($trend) < 2) return 0;
        
        $first = $trend[0]['value'];
        $last = $trend[count($trend) - 1]['value'];
        
        if ($first == 0) return $last > 0 ? 100 : 0;
        
        return round((($last - $first) / $first) * 100, 2);
    }
}