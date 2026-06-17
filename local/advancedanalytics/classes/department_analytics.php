<?php
// local/advancedanalytics/classes/department_analytics.php
// Department Analytics Service

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class department_analytics {
    
    /**
     * Get all department analytics
     * @return array
     */
    public static function get_all_department_analytics() {
        global $DB;
        
        $departments = $DB->get_records_sql_menu("
            SELECT DISTINCT department, department 
            FROM {user} 
            WHERE department IS NOT NULL AND department != '' AND deleted = 0
        ");
        
        $analytics = [];
        foreach ($departments as $dept) {
            $analytics[$dept] = self::get_department_analytics($dept);
        }
        
        return $analytics;
    }
    
    /**
     * Get analytics for specific department
     * @param string $department
     * @return array
     */
    public static function get_department_analytics($department) {
        global $DB;
        
        // Get department users
        $user_ids = $DB->get_fieldset_sql("
            SELECT id FROM {user} 
            WHERE department = :dept AND deleted = 0 AND suspended = 0
        ", ['dept' => $department]);
        
        if (empty($user_ids)) {
            return null;
        }
        
        list($insql, $params) = $DB->get_in_or_equal($user_ids);
        
        // Basic stats
        $stats = [
            'total_users' => count($user_ids),
            'department' => $department
        ];
        
        // Completion stats
        $completion = $DB->get_record_sql("
            SELECT 
                COUNT(DISTINCT u.id) as total,
                COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN u.id END) as completed,
                COUNT(DISTINCT cc.course) as courses_completed
            FROM {user} u
            LEFT JOIN {course_completions} cc ON cc.userid = u.id
            WHERE u.id $insql
        ", $params);
        
        $stats['completion_rate'] = $completion->total > 0 ? round(($completion->completed / $completion->total) * 100, 2) : 0;
        $stats['users_completed'] = $completion->completed;
        $stats['courses_completed'] = $completion->courses_completed;
        
        // Engagement stats
        $engagement = $DB->get_record_sql("
            SELECT 
                AVG(up.engagement_score) as avg_engagement,
                COUNT(CASE WHEN up.risk_level = 'high' THEN 1 END) as high_risk,
                COUNT(CASE WHEN up.risk_level = 'medium' THEN 1 END) as medium_risk,
                COUNT(CASE WHEN up.risk_level = 'low' THEN 1 END) as low_risk
            FROM {local_aa_user_perf} up
            WHERE up.userid $insql
        ", $params);
        
        $stats['avg_engagement'] = round($engagement->avg_engagement ?? 0, 2);
        $stats['high_risk'] = $engagement->high_risk ?? 0;
        $stats['medium_risk'] = $engagement->medium_risk ?? 0;
        $stats['low_risk'] = $engagement->low_risk ?? 0;
        
        // Grade stats
        $grades = $DB->get_record_sql("
            SELECT 
                AVG(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as avg_grade,
                COUNT(DISTINCT gg.userid) as graded_users
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gi.itemtype = 'course' AND gg.userid $insql AND gg.finalgrade IS NOT NULL
        ", $params);
        
        $stats['avg_grade'] = round($grades->avg_grade ?? 0, 2);
        $stats['graded_users'] = $grades->graded_users ?? 0;
        
        // Activity stats
        $activity = $DB->get_record_sql("
            SELECT 
                COUNT(DISTINCT l.userid) as active_users,
                COUNT(l.id) as total_actions
            FROM {logstore_standard_log} l
            WHERE l.userid $insql AND l.timecreated > :cutoff
        ", array_merge($params, ['cutoff' => time() - (30 * 24 * 3600)]));
        
        $stats['active_users'] = $activity->active_users ?? 0;
        $stats['activity_rate'] = $stats['total_users'] > 0 ? round(($stats['active_users'] / $stats['total_users']) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get department comparison
     * @return array
     */
    public static function get_department_comparison() {
        $departments = self::get_all_department_analytics();
        
        $comparison = [
            'best_completion' => null,
            'best_engagement' => null,
            'best_grades' => null,
            'most_active' => null,
            'lowest_risk' => null
        ];
        
        foreach ($departments as $dept => $data) {
            if (!$data) continue;
            
            // Best completion rate
            if (!$comparison['best_completion'] || $data['completion_rate'] > $comparison['best_completion']['rate']) {
                $comparison['best_completion'] = [
                    'department' => $dept,
                    'rate' => $data['completion_rate']
                ];
            }
            
            // Best engagement
            if (!$comparison['best_engagement'] || $data['avg_engagement'] > $comparison['best_engagement']['score']) {
                $comparison['best_engagement'] = [
                    'department' => $dept,
                    'score' => $data['avg_engagement']
                ];
            }
            
            // Best grades
            if (!$comparison['best_grades'] || $data['avg_grade'] > $comparison['best_grades']['grade']) {
                $comparison['best_grades'] = [
                    'department' => $dept,
                    'grade' => $data['avg_grade']
                ];
            }
            
            // Most active
            if (!$comparison['most_active'] || $data['activity_rate'] > $comparison['most_active']['rate']) {
                $comparison['most_active'] = [
                    'department' => $dept,
                    'rate' => $data['activity_rate']
                ];
            }
            
            // Lowest risk (fewest high-risk users)
            $risk_score = ($data['high_risk'] * 3) + ($data['medium_risk'] * 2) + ($data['low_risk'] * 1);
            $risk_normalized = $data['total_users'] > 0 ? $risk_score / $data['total_users'] : 0;
            
            if (!$comparison['lowest_risk'] || $risk_normalized < $comparison['lowest_risk']['score']) {
                $comparison['lowest_risk'] = [
                    'department' => $dept,
                    'score' => round($risk_normalized, 2)
                ];
            }
        }
        
        return $comparison;
    }
    
    /**
     * Get department trend data
     * @param string $department
     * @param int $months
     * @return array
     */
    public static function get_department_trend($department, $months = 6) {
        global $DB;
        
        $trends = [];
        $end_date = time();
        
        for ($i = $months; $i >= 0; $i--) {
            $month_start = strtotime("-$i months", strtotime(date('Y-m-01')));
            $month_end = strtotime('+1 month', $month_start) - 1;
            $month_label = date('M Y', $month_start);
            
            // Get department users for this period
            $users = $DB->get_records_sql("
                SELECT id FROM {user} 
                WHERE department = :dept AND deleted = 0 AND timecreated <= :end
            ", ['dept' => $department, 'end' => $month_end]);
            
            $user_ids = array_keys($users);
            
            if (empty($user_ids)) {
                $trends[] = [
                    'month' => $month_label,
                    'completion_rate' => 0,
                    'avg_engagement' => 0,
                    'avg_grade' => 0
                ];
                continue;
            }
            
            list($insql, $params) = $DB->get_in_or_equal($user_ids);
            $params['start'] = $month_start;
            $params['end'] = $month_end;
            
            // Completion rate for period
            $completion = $DB->get_record_sql("
                SELECT COUNT(DISTINCT u.id) as total,
                       COUNT(DISTINCT CASE WHEN cc.timecompleted BETWEEN :start AND :end THEN u.id END) as completed
                FROM {user} u
                LEFT JOIN {course_completions} cc ON cc.userid = u.id
                WHERE u.id $insql
            ", $params);
            
            $completion_rate = $completion->total > 0 ? ($completion->completed / $completion->total) * 100 : 0;
            
            // Engagement for period
            $engagement = $DB->get_record_sql("
                SELECT AVG(up.engagement_score) as avg_engagement
                FROM {local_aa_user_perf} up
                WHERE up.userid $insql AND up.last_calculated BETWEEN :start AND :end
            ", $params);
            
            // Grades for period
            $grades = $DB->get_record_sql("
                SELECT AVG(CASE WHEN gg.finalgrade > 100 THEN 100 ELSE gg.finalgrade END) as avg_grade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE gi.itemtype = 'course' AND gg.userid $insql 
                AND gg.timecreated BETWEEN :start AND :end
            ", $params);
            
            $trends[] = [
                'month' => $month_label,
                'completion_rate' => round($completion_rate, 2),
                'avg_engagement' => round($engagement->avg_engagement ?? 0, 2),
                'avg_grade' => round($grades->avg_grade ?? 0, 2)
            ];
        }
        
        return $trends;
    }
}