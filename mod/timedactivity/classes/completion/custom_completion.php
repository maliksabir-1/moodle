<?php
namespace mod_timedactivity\completion;
defined('MOODLE_INTERNAL') || die();
use core_completion\activity_custom_completion;

class custom_completion extends activity_custom_completion {

    public function get_state(string $rule): int {
        global $DB, $CFG; 
        $this->validate_rule($rule);

        $timedactivity = $DB->get_record('timedactivity', ['id' => $this->cm->instance], '*', MUST_EXIST);
        
        // Check rule: require time.
        if ($rule === 'completionrequiretime') {
            // Check if completion date is set and reached
            if ($timedactivity->completiontime > 0 && time() >= $timedactivity->completiontime) {
                return COMPLETION_COMPLETE;
            }
            
            $track = $DB->get_record('timedactivity_tracking', [
                'timedactivityid' => $this->cm->instance, 
                'userid' => $this->userid
            ]);
            $totaltime = $track ? $track->totaltimespent : 0;
            return ($totaltime >= $timedactivity->requiredtime) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        // Check rule: passing grade.
        if ($rule === 'completionpass') {
            if ($timedactivity->grademethod <= 0) {
                return COMPLETION_COMPLETE;
            }
            require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');
            $grade = timedactivity_get_user_grade($timedactivity, $this->userid);
            if ($grade === null || $timedactivity->passinggrade <= 0) {
                return COMPLETION_INCOMPLETE;
            }
            return ($grade >= $timedactivity->passinggrade) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        // Check rule: all quizzes.
        if ($rule === 'completionallquizzes') {
            $totalquizzes = $DB->count_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]);
            if ($totalquizzes === 0) return COMPLETION_COMPLETE;
            
            $sql = "SELECT COUNT(DISTINCT quizid) FROM {timedactivity_quiz_attempts} qa 
                    JOIN {timedactivity_quiz} q ON qa.quizid = q.id 
                    WHERE q.timedactivityid = ? AND qa.userid = ?";
            $answered = $DB->count_records_sql($sql, [$timedactivity->id, $this->userid]);
            return ($answered >= $totalquizzes) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    public static function get_defined_custom_rules(): array {
        return ['completionrequiretime', 'completionpass', 'completionallquizzes'];
    }

    public function get_custom_rule_descriptions(): array {
        global $DB;
        $timedactivity = $DB->get_record('timedactivity', ['id' => $this->cm->instance]);
        
        $descriptions = [];
        
        if ($timedactivity->requiredtime > 0) {
            $descriptions['completionrequiretime'] = get_string('completionrequiretime_desc', 'mod_timedactivity', format_time($timedactivity->requiredtime));
        }
        
        if ($timedactivity->passinggrade > 0 && $timedactivity->passinggrade < 100) {
            $descriptions['completionpass'] = get_string('completionpass_desc', 'mod_timedactivity', $timedactivity->passinggrade);
        }
        
        $totalquizzes = $DB->count_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]);
        if ($totalquizzes > 0) {
            $descriptions['completionallquizzes'] = get_string('completionallquizzes_desc', 'mod_timedactivity');
        }
        
        return $descriptions;
    }

    public function get_sort_order(): array {
        return ['completionview', 'completionusegrade', 'completionpass', 'completionallquizzes', 'completionrequiretime'];
    }
}