<?php
// local/advancedanalytics/classes/learner_scoring.php
// Learner Performance Scoring System - FULLY FIXED

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class learner_scoring {
    
    /**
     * Calculate comprehensive learner score
     * @param int $userid
     * @return array
     */
    public static function calculate_learner_score($userid) {
        global $DB;
        
        // Get learner data
        $completion_stats = self::get_completion_stats($userid);
        $grade_stats = self::get_grade_stats($userid);
        $activity_stats = self::get_activity_stats($userid);
        
        // Calculate individual scores (0-100)
        $completion_score = self::calculate_completion_score($completion_stats);
        $engagement_score = self::calculate_engagement_score($activity_stats);
        $grade_score = self::calculate_grade_score($grade_stats);
        $consistency_score = self::calculate_consistency_score($activity_stats);
        
        // Calculate overall score with weights
        $weights = [
            'completion' => 0.35,
            'engagement' => 0.30,
            'grades' => 0.25,
            'consistency' => 0.10
        ];
        
        $overall_score = round(
            ($completion_score * $weights['completion']) +
            ($engagement_score * $weights['engagement']) +
            ($grade_score * $weights['grades']) +
            ($consistency_score * $weights['consistency']),
            2
        );
        
        // Determine performance level
        $performance_level = self::get_performance_level($overall_score);
        
        // Get recommendations
        $recommendations = self::get_recommendations($completion_score, $engagement_score, $grade_score);
        
        return [
            'userid' => $userid,
            'overall_score' => $overall_score,
            'performance_level' => $performance_level,
            'scores' => [
                'completion' => $completion_score,
                'engagement' => $engagement_score,
                'grades' => $grade_score,
                'consistency' => $consistency_score
            ],
            'stats' => [
                'completed_courses' => $completion_stats['completed_courses'],
                'total_courses' => $completion_stats['total_courses'],
                'avg_grade' => $grade_stats['avg_grade'],
                'avg_quiz_grade' => $grade_stats['avg_quiz_grade'],
                'quizzes_taken' => $grade_stats['quizzes_taken'],
                'total_actions' => $activity_stats['total_actions'],
                'active_days' => $activity_stats['active_days'],
                'time_spent' => $activity_stats['time_spent']
            ],
            'recommendations' => $recommendations
        ];
    }
    
    /**
     * Get batch learner scores
     * @param array $userids
     * @return array
     */
    public static function get_batch_learner_scores($userids) {
        $scores = [];
        foreach ($userids as $userid) {
            $scores[$userid] = self::calculate_learner_score($userid);
        }
        return $scores;
    }
    
    /**
     * Get at-risk learners
     * @param int $limit
     * @return array
     */
    public static function get_at_risk_learners($limit = 50) {
        global $DB;
        
        $users = $DB->get_records_sql("
            SELECT u.id
            FROM {user} u
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'
        ");
        
        $at_risk = [];
        foreach ($users as $user) {
            $score = self::calculate_learner_score($user->id);
            if ($score['overall_score'] < 50) {
                $at_risk[] = array_merge(['userid' => $user->id], $score);
            }
        }
        
        // Sort by score ascending
        usort($at_risk, function($a, $b) {
            return $a['overall_score'] <=> $b['overall_score'];
        });
        
        return array_slice($at_risk, 0, $limit);
    }
    
    /**
     * Get learner progress over time
     * @param int $userid
     * @return array
     */
    public static function get_learner_progress($userid) {
        global $DB;
        
        // Get course completions timeline
        $completions = $DB->get_records_sql("
            SELECT 
                DATE(FROM_UNIXTIME(timecompleted)) as id,
                DATE(FROM_UNIXTIME(timecompleted)) as date,
                COUNT(*) as completions
            FROM {course_completions}
            WHERE userid = :userid AND timecompleted IS NOT NULL
            GROUP BY DATE(FROM_UNIXTIME(timecompleted))
            ORDER BY date ASC
        ", ['userid' => $userid]);
        
        // Get grade improvements - focused on quizzes
        $grades = $DB->get_records_sql("
            SELECT 
                gg.id,
                DATE(FROM_UNIXTIME(gg.timecreated)) as date,
                gi.courseid,
                c.fullname,
                gg.finalgrade
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            JOIN {course} c ON c.id = gi.courseid
            WHERE gg.userid = :userid AND gi.itemmodule = 'quiz'
            ORDER BY gg.timecreated ASC
        ", ['userid' => $userid]);
        
        // Calculate trend
        $grade_trend = self::calculate_trend($grades);
        
        return [
            'userid' => $userid,
            'completion_timeline' => array_values($completions),
            'grade_progress' => array_values($grades),
            'grade_trend' => $grade_trend,
            'total_completions' => count($completions),
            'last_completion' => !empty($completions) ? end($completions)->date : null,
            'total_grades' => count($grades)
        ];
    }
    
    // ============================================
    // PRIVATE METHODS
    // ============================================
    
    private static function get_completion_stats($userid) {
        global $DB;
        
        // FIXED: Get total courses count properly
        $total_courses = $DB->count_records('course', ['visible' => 1]);
        
        // FIXED: Use correct parameter format for count_records_select
        $completed_courses = $DB->count_records_select(
            'course_completions',
            'userid = ? AND timecompleted IS NOT NULL',
            [$userid]
        );
        
        // Get in-progress courses - FIXED parameter format
        $in_progress = $DB->count_records_sql("
            SELECT COUNT(DISTINCT course)
            FROM {course_completions}
            WHERE userid = ? AND timecompleted IS NULL
        ", [$userid]);
        
        $completion_rate = $total_courses > 0 ? ($completed_courses / $total_courses) * 100 : 0;
        
        return [
            'total_courses' => $total_courses,
            'completed_courses' => $completed_courses,
            'in_progress' => $in_progress,
            'completion_rate' => $completion_rate
        ];
    }
    
    private static function get_engagement_stats($userid) {
        global $DB;
        
        $cutoff = time() - (90 * 24 * 3600);
        
        $stats = $DB->get_record_sql("
            SELECT 
                COUNT(DISTINCT l.id) as total_actions,
                COUNT(DISTINCT DATE(FROM_UNIXTIME(l.timecreated))) as active_days,
                COUNT(DISTINCT l.courseid) as courses_accessed
            FROM {logstore_standard_log} l
            WHERE l.userid = :userid AND l.timecreated > :cutoff
        ", ['userid' => $userid, 'cutoff' => $cutoff]);
        
        // Get recent activity (last 7 days)
        $recent_activity = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {logstore_standard_log}
            WHERE userid = :userid AND timecreated > :recent_cutoff
        ", [
            'userid' => $userid,
            'recent_cutoff' => time() - (7 * 24 * 3600)
        ]);
        
        return [
            'total_actions' => $stats->total_actions ?? 0,
            'active_days' => $stats->active_days ?? 0,
            'courses_accessed' => $stats->courses_accessed ?? 0,
            'recent_activity' => $recent_activity
        ];
    }
    
    private static function get_grade_stats($userid) {
        global $DB;
        
        $stats = $DB->get_record_sql("
            SELECT 
                AVG(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as avg_grade,
                MIN(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as min_grade,
                MAX(CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END) as max_grade,
                COUNT(DISTINCT gi.courseid) as graded_courses
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gg.userid = :userid AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
        ", ['userid' => $userid]);

        // Specific Quiz performance
        $quiz_stats = $DB->get_record_sql("
            SELECT AVG(gg.finalgrade) as avg_quiz_grade, COUNT(*) as quizzes_taken
            FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gg.userid = :userid AND gi.itemmodule = 'quiz' AND gg.finalgrade IS NOT NULL
        ", ['userid' => $userid]);
        
        return [
            'avg_grade' => round($stats->avg_grade ?? 0, 2),
            'min_grade' => round($stats->min_grade ?? 0, 2),
            'max_grade' => round($stats->max_grade ?? 0, 2),
            'graded_courses' => $stats->graded_courses ?? 0,
            'avg_quiz_grade' => round($quiz_stats->avg_quiz_grade ?? 0, 2),
            'quizzes_taken' => $quiz_stats->quizzes_taken ?? 0
        ];
    }
    
    private static function get_activity_stats($userid) {
        global $DB;
        
        $cutoff = time() - (90 * 24 * 3600);
        
        $stats = $DB->get_record_sql("
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT DATE(FROM_UNIXTIME(timecreated))) as active_days
            FROM {logstore_standard_log}
            WHERE userid = :userid AND timecreated > :cutoff
        ", ['userid' => $userid, 'cutoff' => $cutoff]);

        // Estimate time spent (Sum durations between logs < 45 mins)
        $logs = $DB->get_records_sql("
            SELECT id, timecreated 
            FROM {logstore_standard_log} 
            WHERE userid = ? AND timecreated > ? 
            ORDER BY timecreated ASC", 
        [$userid, $cutoff]);

        $time_spent = 0;
        $last_time = 0;
        foreach ($logs as $log) {
            if ($last_time > 0) {
                $diff = $log->timecreated - $last_time;
                if ($diff < 2700) { // 45 minutes
                    $time_spent += $diff;
                }
            }
            $last_time = $log->timecreated;
        }
        
        return [
            'total_actions' => $stats->total_actions ?? 0,
            'active_days' => $stats->active_days ?? 0,
            'time_spent' => round($time_spent / 60, 0) // Convert to minutes
        ];
    }
    
    private static function calculate_completion_score($stats) {
        $rate = $stats['completion_rate'];
        if ($rate >= 80) return 90 + (($rate - 80) / 20) * 10;
        if ($rate >= 60) return 70 + (($rate - 60) / 20) * 20;
        if ($rate >= 40) return 50 + (($rate - 40) / 20) * 20;
        if ($rate >= 20) return 30 + (($rate - 20) / 20) * 20;
        return ($rate / 20) * 30;
    }
    
    private static function calculate_engagement_score($stats) {
        // Normalize metrics to 0-100
        $actions_score = min(100, ($stats['total_actions'] ?? 0) / 50 * 100); // 50 actions/90d as benchmark
        $time_score = min(100, ($stats['time_spent'] ?? 0) / 120 * 100);    // 2 hours/90d as benchmark
        $days_score = min(100, (($stats['active_days'] ?? 0) / 15) * 100);  // 15 active days/90d as benchmark
        
        return round(($actions_score * 0.4) + ($time_score * 0.4) + ($days_score * 0.2), 2);
    }
    
    private static function calculate_grade_score($stats) {
        $avg = $stats['avg_grade'] ?? 0;
        if ($avg >= 90) return 95 + ($avg - 90);
        if ($avg >= 80) return 85 + ($avg - 80);
        if ($avg >= 70) return 75 + ($avg - 70);
        if ($avg >= 60) return 65 + ($avg - 60);
        if ($avg >= 50) return 50 + ($avg - 50);
        return ($avg / 50) * 50;
    }
    
    private static function calculate_consistency_score($stats) {
        $login_regularity = min(100, (($stats['active_days'] ?? 0) / 30) * 100);
        return round($login_regularity, 2);
    }
    
    private static function get_performance_level($score) {
        if ($score >= 85) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 55) return 'Satisfactory';
        if ($score >= 40) return 'Needs Improvement';
        return 'At Risk';
    }
    
    private static function get_recommendations($completion, $engagement, $grades) {
        $recommendations = [];
        
        if ($completion < 40) {
            $recommendations[] = 'Focus on completing pending courses';
        }
        if ($engagement < 30) {
            $recommendations[] = 'Increase platform engagement and participation';
        }
        if ($grades < 50) {
            $recommendations[] = 'Seek additional help for challenging subjects';
        }
        if ($completion >= 60 && $grades < 60) {
            $recommendations[] = 'Review course materials and practice more';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Continue maintaining your current performance level';
        }
        
        return $recommendations;
    }
    
    private static function calculate_trend($grades) {
        // Filter for quizzes specifically if we want "Quiz History" trend
        $quiz_grades = array_filter($grades, function($g) {
            // In the query we joined course, but we can check if it's a quiz if we had more info.
            // For now, we'll use all grades retrieved since the query was course-based, 
            // but the user wants "Quiz history". 
            return true; 
        });

        if (count($grades) < 2) return 'stable';
        
        $grades_array = array_values($grades);
        $first = $grades_array[0]->finalgrade ?? 0;
        $last = end($grades_array)->finalgrade ?? 0;
        
        if ($last > $first + 3) return 'improving';
        if ($last < $first - 3) return 'declining';
        return 'stable';
    }
}