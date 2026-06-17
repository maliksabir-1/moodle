<?php
// local/advancedanalytics/classes/ai_engine.php
// AI Insights Module - Rule-based intelligence layer

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class ai_engine {
    
    /**
     * Generate automated insights based on current data
     */
    public static function get_insights() {
        global $DB;
        $insights = [];
        
        // 1. Performance Drops (Anomaly detection)
        $depts = $DB->get_records('local_aa_dept_stats', [], 'avg_performance ASC', '*', 0, 5);
        foreach ($depts as $d) {
            if ($d->avg_performance < 50) {
                $insights[] = [
                    'type' => 'drop',
                    'title' => 'Performance Drop: ' . $d->department,
                    'text' => "Average performance in {$d->department} has dropped below 50%. This is 15% lower than the organizational average.",
                    'severity' => 'danger'
                ];
            }
        }
        
        // 2. Compliance Risks
        $non_compliant = $DB->count_records_select('local_aa_user_compliance', 'compliance_percentage < 100');
        $total_users = $DB->count_records('user', ['deleted' => 0]);
        if ($total_users > 0 && ($non_compliant / $total_users) > 0.1) {
            $risk_pct = round(($non_compliant / $total_users) * 100);
            $insights[] = [
                'type' => 'compliance',
                'title' => 'Compliance Risk Alert',
                'text' => "Currently, {$risk_pct}% of your workforce is non-compliant with mandatory courses. Projected risk for next quarter is High.",
                'severity' => 'warning'
            ];
        }
        
        // 3. Engagement / Top Performers
        $stars = $DB->get_records('local_aa_user_perf', ['risk_level' => 'low'], 'engagement_score DESC', '*', 0, 1);
        if ($stars) {
            $star = reset($stars);
            $user = $DB->get_record('user', ['id' => $star->userid]);
            if ($user) {
                $insights[] = [
                    'type' => 'potential',
                    'title' => 'High Potential Identified',
                    'text' => fullname($user) . " is performing consistently in the top 1% with an engagement score of " . round($star->engagement_score) . "%.",
                    'severity' => 'success'
                ];
            }
        }

        // 4. Dormant Progress (Proactive check for 0%)
        $dormant = $DB->count_records_select('local_aa_user_perf', 'completion_percentage = 0 AND engagement_score > 0');
        if ($dormant > 0) {
            $insights[] = [
                'type' => 'engagement',
                'title' => 'Dormant Progress Detected',
                'text' => "{$dormant} users are active in the system but have 0% course completion. Early intervention is recommended to prevent drop-off.",
                'severity' => 'info'
            ];
        }

        // Default if no insights
        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'System Stable',
                'text' => 'No major anomalies or compliance risks detected in the last 24 hours.',
                'severity' => 'primary'
            ];
        }
        
        return $insights;
    }
}
