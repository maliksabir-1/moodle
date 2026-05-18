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

namespace mod_timedactivity\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External function to save quiz answers
 *
 * @package    mod_timedactivity
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_quiz_answer extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function save_quiz_answer_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'Quiz ID'),
            'answer' => new external_value(PARAM_INT, 'Selected answer index'),
            'iscorrect' => new external_value(PARAM_INT, 'Whether answer is correct (1 or 0)')
        ]);
    }

    /**
     * Save user's quiz answer
     *
     * @param int $quizid The quiz ID
     * @param int $answer The selected answer index
     * @param int $iscorrect Whether the answer is correct (1 or 0)
     * @return array Response with success status
     */
    public static function save_quiz_answer($quizid, $answer, $iscorrect) {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_quiz_answer_parameters(), [
            'quizid' => $quizid,
            'answer' => $answer,
            'iscorrect' => $iscorrect
        ]);

        // Get quiz to verify context
        $quiz = $DB->get_record('timedactivity_quiz', ['id' => $params['quizid']], '*', MUST_EXIST);
        $timedactivity = $DB->get_record('timedactivity', ['id' => $quiz->timedactivityid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('timedactivity', $timedactivity->id, 0, false, MUST_EXIST);
        
        // Validate context
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        
        // Check if user can answer (basic capability)
        require_capability('mod/timedactivity:view', $context);

        // Save the answer
        $success = timedactivity_save_quiz_answer(
            $params['quizid'],
            $USER->id,
            $params['answer'],
            (bool)$params['iscorrect']
        );

        $grade = null;
        $passed = false;
        if ($success) {
            global $CFG;
            require_once($CFG->dirroot . '/mod/timedactivity/lib.php');
            timedactivity_update_user_grade_and_completion($timedactivity, $USER->id);
            $grade = timedactivity_get_user_grade($timedactivity, $USER->id);
            $passed = ($grade !== null && $grade >= $timedactivity->passinggrade);
        }

        return [
            'success' => $success,
            'grade' => $grade !== null ? (int)$grade : null,
            'passed' => $passed
        ];
    }

    /**
     * Returns description of method return value
     *
     * @return external_single_structure
     */
    public static function save_quiz_answer_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the answer was saved successfully'),
            'grade' => new external_value(PARAM_INT, 'User current grade', VALUE_OPTIONAL),
            'passed' => new external_value(PARAM_BOOL, 'Whether the user passed the grade requirement', VALUE_OPTIONAL)
        ]);
    }
}