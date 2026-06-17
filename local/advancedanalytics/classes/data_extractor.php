<?php
// local/advancedanalytics/classes/data_extractor.php
// Optimized data extraction queries for large datasets - FIXED scaling

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class data_extractor {
    
    /**
     * Extract user summary data with single optimized query
     */
    public static function extract_user_summary($date, $batchsize = 1000, $offset = 0) {
        global $DB;
        
        $sql = "SELECT 
                    u.id as userid,
                    u.department,
                    u.firstname,
                    u.lastname,
                    u.email,
                    u.timecreated as user_created,
                    COALESCE((
                        SELECT COUNT(DISTINCT l.userid)
                        FROM {logstore_standard_log} l
                        WHERE l.userid = u.id
                        AND l.timecreated > ?
                    ), 0) as is_active,
                    COALESCE((
                        SELECT COUNT(DISTINCT cc.id)
                        FROM {course_completions} cc
                        WHERE cc.userid = u.id
                        AND cc.timecompleted IS NOT NULL
                    ), 0) as completed_courses,
                    COALESCE((
                        SELECT AVG(gg.finalgrade)
                        FROM {grade_grades} gg
                        JOIN {grade_items} gi ON gi.id = gg.itemid
                        WHERE gg.userid = u.id
                        AND gi.itemtype = 'course'
                        AND gg.finalgrade IS NOT NULL
                    ), 0) as avg_grade
                FROM {user} u
                WHERE u.deleted = 0
                AND u.suspended = 0
                ORDER BY u.id";
        
        $params = [$date - (30 * 24 * 3600)];
        
        return $DB->get_records_sql($sql, $params, $offset, $batchsize);
    }
    
    /**
     * Extract course analytics - FIXED: ensure numeric values
     */
    public static function extract_course_analytics($date) {
        global $DB;
        
        $sql = "SELECT 
                    c.id as courseid,
                    COALESCE((SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = c.id AND e.status = 0), 0) as total_enrolled,
                    COALESCE((SELECT COUNT(DISTINCT cc.userid) FROM {course_completions} cc WHERE cc.course = c.id AND cc.timecompleted IS NOT NULL), 0) as completed_count,
                    COALESCE((
                        SELECT AVG(gg.finalgrade)
                        FROM {grade_grades} gg
                        JOIN {grade_items} gi ON gi.id = gg.itemid
                        WHERE gi.courseid = c.id AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                    ), 0) as avg_grade,
                    COALESCE((SELECT COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l WHERE l.courseid = c.id AND l.timecreated > ?), 0) as active_participants,
                    COALESCE((SELECT MAX(l.timecreated) FROM {logstore_standard_log} l WHERE l.courseid = c.id), 0) as avg_time_spent,
                    COALESCE((SELECT MAX(l.timecreated) FROM {logstore_standard_log} l WHERE l.courseid = c.id), c.timecreated) as last_activity
                FROM {course} c
                WHERE c.visible = 1";
        
        $params = [ $date - (30 * 24 * 3600) ];
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Extract department statistics
     */
    public static function extract_department_stats($date) {
        global $DB;
        
        $sql = "SELECT 
                    COALESCE(u.department, 'Unassigned') as department,
                    COUNT(DISTINCT u.id) as total_employees,
                    COALESCE(AVG(up.completion_percentage), 0) as compliance_rate,
                    COALESCE(AVG(up.avg_quiz_score), 0) as avg_performance,
                    (SELECT COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l JOIN {user} u2 ON u2.id = l.userid WHERE COALESCE(u2.department, 'Unassigned') = COALESCE(u.department, 'Unassigned') AND l.timecreated > ?) as trained_employees,
                    COUNT(DISTINCT CASE WHEN up.risk_level IN ('high', 'medium') THEN u.id END) as at_risk_count,
                    COUNT(DISTINCT CASE WHEN up.engagement_score > 80 THEN u.id END) as high_performers
                FROM {user} u
                LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id
                WHERE u.deleted = 0
                GROUP BY COALESCE(u.department, 'Unassigned')";
        
        $params = [$date - (30 * 24 * 3600)];
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Extract user performance for batch update - FIXED: scale values to 0-100 range
     */
    public static function extract_user_performance_batch($date, $batchsize = 500, $offset = 0) {
        global $DB;
        
        $sql = "SELECT 
                    u.id as userid,
                    -- Scale engagement: count logs, cap at 500 logs = 100 points
                    LEAST(100, ROUND(COALESCE((
                        SELECT COUNT(DISTINCT l.id)
                        FROM {logstore_standard_log} l
                        WHERE l.userid = u.id
                        AND l.timecreated > ?
                    ), 0) / 5, 2)) as engagement_score,
                    -- Completion percentage (Average across all enrolled courses)
                    COALESCE((
                        SELECT AVG(p) FROM (
                            SELECT (COUNT(DISTINCT cmc.id) * 100.0 / NULLIF(COUNT(DISTINCT cm.id), 0)) as p
                            FROM {course} c2
                            JOIN {enrol} e ON e.courseid = c2.id
                            JOIN {user_enrolments} ue ON ue.enrolid = e.id
                            LEFT JOIN {course_modules} cm ON cm.course = c2.id AND cm.completion > 0
                            LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id AND cmc.completionstate IN (1,2)
                            WHERE ue.userid = u.id
                            GROUP BY c2.id
                        ) as course_progress
                    ), 0) as completion_percentage,
                    -- Quiz score (already 0-100)
                    COALESCE((
                        SELECT AVG(qg.grade * 100.0 / NULLIF(q.grade, 0))
                        FROM {quiz_grades} qg
                        JOIN {quiz} q ON q.id = qg.quiz
                        WHERE qg.userid = u.id
                        AND qg.grade <= q.grade
                        LIMIT 1
                    ), 0) as avg_quiz_score,
                    -- Time spent in seconds (cap at 999999999)
                    LEAST(999999999, COALESCE((
                        SELECT COUNT(l.id) * 60
                        FROM {logstore_standard_log} l
                        WHERE l.userid = u.id
                        AND l.timecreated > ?
                    ), 0)) as time_spent,
                    -- Last access (ensure not NULL)
                    COALESCE((
                        SELECT MAX(l.timecreated)
                        FROM {logstore_standard_log} l
                        WHERE l.userid = u.id
                    ), u.timecreated, UNIX_TIMESTAMP()) as last_access,
                    -- Login count last 30 days (cap at 30)
                    LEAST(30, COALESCE((
                        SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(l.timecreated)))
                        FROM {logstore_standard_log} l
                        WHERE l.userid = u.id
                        AND l.timecreated > ?
                    ), 0)) as login_count
                FROM {user} u
                WHERE u.deleted = 0 AND u.suspended = 0";
        
        $params = [$date - (90 * 24 * 3600), $date - (90 * 24 * 3600), $date - (30 * 24 * 3600)];
        
        return $DB->get_records_sql($sql, $params, $offset, $batchsize);
    }
}