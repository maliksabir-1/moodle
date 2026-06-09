<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    /**
     * Handle user login event
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        $userid = $event->userid;
        
        if ($userid) {
            // Update login streak
            manager::update_login_streak($userid);
            
            // Trigger daily login challenge progress
            manager::check_daily_challenge_progress($userid, 'daily_login');
        }
    }
    
    /**
     * Handle quiz completion event
     */
    public static function quiz_completed(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Award XP
        $xp = get_config('local_point_badges', 'xp_quiz') ?: manager::XP_QUIZ_COMPLETE;
        manager::award_xp($userid, $courseid, $xp, 'quiz_completed');
        
        // Trigger challenge progress
        manager::check_daily_challenge_progress($userid, 'quiz_completed');
        
        // Check for High Score achievement
        $count = $DB->count_records('local_pb_xp_log', ['userid' => $userid, 'reason' => 'quiz_completed']);
        badge_manager::check_achievement_badge($userid, 'high_score', $count);
    }
    
    /**
     * Handle assignment submission event
     */
    public static function assignment_submitted(\core\event\base $event) {
        global $DB;
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Prepare a unique reason that includes the specific assignment ID
        // Note: For assignment events, objectid is the submission ID, and contextinstanceid is the cmid
        $cmid = $event->contextinstanceid;
        $reason = 'assignment_submitted_' . $cmid;
        
        // Prevent duplicate XP for the same assignment upload
        $already_awarded = $DB->record_exists_sql("
            SELECT id FROM {local_pb_xp_log} 
            WHERE userid = ? AND reason = ?
        ", [$userid, $reason]);
        
        if ($already_awarded) {
            return; // Only award XP once per specific assignment
        }
        
        // Award XP
        $xp = get_config('local_point_badges', 'xp_assignment') ?: manager::XP_ASSIGNMENT_SUBMIT;
        // Use the specific assignment reason so it tracks which assignment they completed
        manager::award_xp($userid, $courseid, $xp, $reason);
        
        // Trigger challenge progress - must use the generic string because challenges DB checks exact 'event_name' match
        manager::check_daily_challenge_progress($userid, 'assignment_submitted');
        
        // Check skill achievement
        $count = $DB->count_records_select('local_pb_xp_log', "userid = ? AND reason LIKE 'assignment_submitted%'", [$userid]);
        badge_manager::check_achievement_badge($userid, 'skill', $count);
    }
    
    /**
     * Handle forum post event
     */
    public static function forum_post(\mod_forum\event\post_created $event) {
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Award XP
        $xp = get_config('local_point_badges', 'xp_forum') ?: manager::XP_FORUM_POST;
        manager::award_xp($userid, $courseid, $xp, 'forum_post');
        
        // Trigger challenge progress
        manager::check_daily_challenge_progress($userid, 'forum_post');
    }
    
    /**
     * Handle lesson completion event
     */
    public static function lesson_completed(\mod_lesson\event\lesson_completed $event) {
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Award XP
        $xp = get_config('local_point_badges', 'xp_lesson') ?: manager::XP_LESSON_COMPLETE;
        manager::award_xp($userid, $courseid, $xp, 'lesson_completed');
        
        // Trigger challenge progress
        manager::check_daily_challenge_progress($userid, 'lesson_completed');
    }
    
    /**
     * Handle SCORM completion event
     */
    public static function scorm_completed(\mod_scorm\event\tracks_viewed $event) {
        global $DB;
        
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        $scormid = $event->other['instanceid'];
        
        // Check if SCORM is completed
        $track = $DB->get_record('scorm_scoes_track', [
            'scormid' => $scormid,
            'userid' => $userid,
            'element' => 'cmi.core.lesson_status'
        ], 'id, value', IGNORE_MULTIPLE);
        
        if ($track && in_array($track->value, ['completed', 'passed'])) {
            // Award XP
            $xp = get_config('local_point_badges', 'xp_scorm') ?: manager::XP_SCORM_COMPLETE;
            manager::award_xp($userid, $courseid, $xp, 'scorm_completed');
            
            // Trigger challenge progress
            manager::check_daily_challenge_progress($userid, 'scorm_completed');
            
            // Skill achievement
            $count = $DB->count_records('local_pb_xp_log', ['userid' => $userid, 'reason' => 'scorm_completed']);
            badge_manager::check_achievement_badge($userid, 'skill', $count);
        }
    }
    
    /**
     * Handle attendance event (if Attendance plugin is installed)
     */
    public static function attendance_taken(\mod_attendance\event\attendance_taken $event) {
        global $DB;
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Award XP
        $xp = get_config('local_point_badges', 'xp_attendance') ?: 20;
        manager::award_xp($userid, $courseid, $xp, 'attendance_taken');
        
        // Trigger challenge progress
        manager::check_daily_challenge_progress($userid, 'attendance_taken');
        
        // Check for Attendance Badge
        $count = $DB->count_records('local_pb_xp_log', ['userid' => $userid, 'reason' => 'attendance_taken']);
        badge_manager::check_achievement_badge($userid, 'attendance', $count);
    }
    
    /**
     * Handle Course Completion
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB;
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        manager::award_xp($userid, $courseid, 500, 'course_completed');
        
        if ($DB->get_manager()->table_exists('course_completions')) {
            $count = $DB->count_records('course_completions', ['userid' => $userid]);
            badge_manager::check_achievement_badge($userid, 'course_completion', $count);
        }
    }
    
    /**
     * Handle generic activity completion event
     */
    public static function activity_completed(\core\event\course_module_completion_updated $event) {
        global $DB;
        $userid = $event->relateduserid ?? $event->userid;
        $courseid = $event->courseid;
        
        // Fetch completion data
        if ($event->objecttable === 'course_modules_completion') {
            $completiondata = $event->get_record_snapshot('course_modules_completion', $event->objectid);
            if ($completiondata && in_array($completiondata->completionstate, [1, 2])) {
                // Determine module context to avoid duplicate XP
                $contextinstanceid = $event->contextinstanceid;
                $reason = 'activity_completed_' . $contextinstanceid;
                
                // Prevent duplicate XP for the same activity completion
                $already_awarded = $DB->record_exists_sql("
                    SELECT id FROM {local_pb_xp_log} 
                    WHERE userid = ? AND courseid = ? AND reason = ?
                ", [$userid, $courseid, $reason]);
                
                if ($already_awarded) {
                    return; 
                }
                
                // General activity xp can be a default like 10 or configured.
                $xp = get_config('local_point_badges', 'xp_general_activity') ?: 10;
                manager::award_xp($userid, $courseid, $xp, $reason);
                
                // Trigger daily challenge for generic activity
                manager::check_daily_challenge_progress($userid, 'activity_completed');
            }
        }
    }
   public static function before_quiz_attempt(\mod_quiz\event\attempt_viewed $event) {
    global $DB, $USER;
    
    // Only proceed if in a web context (not CLI or AJAX)
    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        return;
    }
    
    $quizid = $event->other['quizid'];
    $extra = \local_point_badges\quiz_manager::get_remaining_extra_attempts($USER->id, $quizid);
    
    if ($extra > 0) {
        // Store in session that user has extra attempts
        if (session_id()) {
            $_SESSION['extra_quiz_attempts'][$quizid] = $extra;
        }
    }
}
}