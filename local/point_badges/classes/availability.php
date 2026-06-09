<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class availability {
    
    /**
     * Check if user can access content based on level
     */
    public static function can_access($userid, $required_level, $courseid = null) {
        $level_info = manager::get_user_level_info($userid, $courseid);
        return $level_info['current_level'] >= $required_level;
    }
    
    /**
     * Get restriction message
     */
    public static function get_restriction_message($required_level) {
        $level_info = manager::get_level_details($required_level);
        return "⚠️ This content requires <strong>{$level_info['name']}</strong> level (Level {$required_level}) or higher.";
    }
    
    /**
     * Check and display restriction for a course module
     */
    public static function check_cm_access($cm, $required_level) {
        global $USER, $OUTPUT;
        
        if (!self::can_access($USER->id, $required_level, $cm->course)) {
            echo $OUTPUT->notification(self::get_restriction_message($required_level), 'warning');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all level-restricted activities for a user in a course
     */
    public static function get_restricted_activities($userid, $courseid) {
        global $DB;
        
        $level_info = manager::get_user_level_info($userid, $courseid);
        $user_level = $level_info['current_level'];
        
        // Get all restricted activities from custom table
        $sql = "SELECT cm.id, cm.instance, m.name as modname, cm.restricted_level
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {local_pb_restrictions} r ON r.cmid = cm.id
                WHERE cm.course = :courseid
                AND r.restricted_level > :user_level";
        
        return $DB->get_records_sql($sql, ['courseid' => $courseid, 'user_level' => $user_level]);
    }
}