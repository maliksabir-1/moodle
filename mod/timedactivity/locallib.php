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

defined('MOODLE_INTERNAL') || die();

/**
 * Timed Activity local library functions
 * 
 * @package    mod_timedactivity
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get user grade for timed activity
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return int|null Grade percentage (0-100) or null if no grade available
 */
function timedactivity_get_user_grade($timedactivity, $userid) {
    global $DB;
    
    if (!$timedactivity || !$userid) {
        return null;
    }
    
    // Get quiz questions for this activity
    $quizquestions = $DB->get_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]);
    $totalquestions = count($quizquestions);
    
    // Get tracking data
    $track = $DB->get_record('timedactivity_tracking', [
        'timedactivityid' => $timedactivity->id, 
        'userid' => $userid
    ]);
    $totaltime = $track ? $track->totaltimespent : 0;
    
    // Determine grade based on grade method
    $grademethod = (int)($timedactivity->grademethod ?? 0);
    
    switch ($grademethod) {
        case 0: // No grade
            return null;
            
        case 1: // Quiz score only
            if ($totalquestions == 0) {
                return null;
            }
            return timedactivity_calculate_quiz_score($timedactivity->id, $userid, $totalquestions);
            
        case 2: // Time completion only
            if ($timedactivity->requiredtime <= 0) {
                return null;
            }
            $timeprogress = min(100, round(($totaltime / $timedactivity->requiredtime) * 100));
            return $timeprogress;
            
        case 3: // Quiz + Time completion
            if ($totalquestions == 0) {
                // No quizzes, fall back to time only
                if ($timedactivity->requiredtime > 0) {
                    $timeprogress = min(100, round(($totaltime / $timedactivity->requiredtime) * 100));
                    return $timeprogress;
                }
                return null;
            }
            
            $quizscore = timedactivity_calculate_quiz_score($timedactivity->id, $userid, $totalquestions);
            
            // Apply time requirement for full grade if needed
            if (!empty($timedactivity->requiretimeforgrade) && $timedactivity->requiredtime > 0) {
                if ($totaltime >= $timedactivity->requiredtime) {
                    return $quizscore;
                } else {
                    // Time requirement not met, reduce grade proportionally
                    $timefactor = $totaltime / $timedactivity->requiredtime;
                    return round($quizscore * $timefactor);
                }
            }
            
            return $quizscore;
            
        default:
            return null;
    }
}

/**
 * Calculate quiz score for a user
 * 
 * @param int $timedactivityid The timed activity ID
 * @param int $userid The user ID
 * @param int $totalquestions Total number of questions
 * @return int Grade percentage (0-100)
 */
function timedactivity_calculate_quiz_score($timedactivityid, $userid, $totalquestions) {
    global $DB;
    
    if ($totalquestions == 0) {
        return 0;
    }
    
    // Count correct answers based on the user's latest attempt for each quiz
    $correctanswers = $DB->count_records_sql("
        SELECT COUNT(DISTINCT qa.quizid)
        FROM {timedactivity_quiz_attempts} qa
        JOIN {timedactivity_quiz} q ON qa.quizid = q.id
        JOIN (
            SELECT quizid, MAX(id) as maxid
            FROM {timedactivity_quiz_attempts}
            WHERE userid = ?
            GROUP BY quizid
        ) latest ON qa.id = latest.maxid
        WHERE q.timedactivityid = ? AND qa.iscorrect = 1
    ", [$userid, $timedactivityid]);
    
    return round(($correctanswers / $totalquestions) * 100);
}

/**
 * Check if user has completed time requirement
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return bool True if time requirement is met
 */
function timedactivity_is_time_complete($timedactivity, $userid) {
    global $DB;
    
    if ($timedactivity->requiredtime <= 0) {
        return true;
    }
    
    $track = $DB->get_record('timedactivity_tracking', [
        'timedactivityid' => $timedactivity->id,
        'userid' => $userid
    ]);
    
    $totaltime = $track ? $track->totaltimespent : 0;
    return $totaltime >= $timedactivity->requiredtime;
}

/**
 * Check if user has completed all quizzes
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return bool True if all quizzes are answered
 */
function timedactivity_are_all_quizzes_complete($timedactivity, $userid) {
    global $DB;
    
    $totalquizzes = $DB->count_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]);
    
    if ($totalquizzes == 0) {
        return true;
    }
    
    $answered = $DB->count_records_sql("
        SELECT COUNT(DISTINCT quizid)
        FROM {timedactivity_quiz_attempts}
        WHERE quizid IN (SELECT id FROM {timedactivity_quiz} WHERE timedactivityid = ?)
        AND userid = ?
    ", [$timedactivity->id, $userid]);
    
    return $answered >= $totalquizzes;
}

/**
 * Get user's quiz results for the activity
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return array Array of quiz results with question details
 */
function timedactivity_get_user_quiz_results($timedactivity, $userid) {
    global $DB;
    
    $results = [];
    $quizzes = $DB->get_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id], 'timeposition ASC');
    
    foreach ($quizzes as $quiz) {
        // Retrieve the latest attempt for this quiz question
        $attempts = $DB->get_records('timedactivity_quiz_attempts', [
            'quizid' => $quiz->id,
            'userid' => $userid
        ], 'id DESC', '*', 0, 1);
        $attempt = !empty($attempts) ? reset($attempts) : null;
        
        $results[] = (object)[
            'id' => $quiz->id,
            'timeposition' => $quiz->timeposition,
            'questiontext' => $quiz->questiontext,
            'options' => json_decode($quiz->options, true),
            'correctanswer' => $quiz->correctanswer,
            'explanation' => $quiz->explanation,
            'useranswer' => $attempt ? $attempt->answer : -1,
            'iscorrect' => $attempt ? (bool)$attempt->iscorrect : false,
            'answered' => $attempt ? true : false
        ];
    }
    
    return $results;
}

/**
 * Update user's video watching progress
 * 
 * @param int $timedactivityid The timed activity ID
 * @param int $userid The user ID
 * @param int $duration Seconds to add to total time
 * @param float $position Current video position in seconds
 * @return object Updated tracking record
 */
function timedactivity_update_progress($timedactivityid, $userid, $duration = 0, $position = null) {
    global $DB;
    
    $track = $DB->get_record('timedactivity_tracking', [
        'timedactivityid' => $timedactivityid,
        'userid' => $userid
    ]);
    
    if (!$track) {
        $track = new stdClass();
        $track->timedactivityid = $timedactivityid;
        $track->userid = $userid;
        $track->totaltimespent = 0;
        $track->videoposition = 0;
        $track->attempts = 1;
        $track->timemodified = time();
        $track->id = $DB->insert_record('timedactivity_tracking', $track);
    } else {
        $updates = new stdClass();
        $updates->id = $track->id;
        $updates->timemodified = time();
        
        if ($duration > 0) {
            $updates->totaltimespent = $track->totaltimespent + $duration;
        }
        
        if ($position !== null) {
            $updates->videoposition = $position;
        }
        
        $DB->update_record('timedactivity_tracking', $updates);
        
        // Refresh track record
        $track = $DB->get_record('timedactivity_tracking', ['id' => $track->id]);
    }
    
    return $track;
}

/**
 * Get activity completion status for a user
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return array Completion status for each rule
 */
function timedactivity_get_completion_status($timedactivity, $userid) {
    global $DB;
    
    $status = [];
    
    // Time requirement
    $status['time'] = (object)[
        'complete' => timedactivity_is_time_complete($timedactivity, $userid),
        'required' => $timedactivity->requiredtime,
        'current' => $DB->get_field('timedactivity_tracking', 'totaltimespent', [
            'timedactivityid' => $timedactivity->id,
            'userid' => $userid
        ]) ?: 0
    ];
    
    // Quiz requirement
    $status['quizzes'] = (object)[
        'complete' => timedactivity_are_all_quizzes_complete($timedactivity, $userid),
        'total' => $DB->count_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]),
        'answered' => $DB->count_records_sql("
            SELECT COUNT(DISTINCT qa.quizid)
            FROM {timedactivity_quiz_attempts} qa
            JOIN {timedactivity_quiz} q ON qa.quizid = q.id
            WHERE q.timedactivityid = ? AND qa.userid = ?
        ", [$timedactivity->id, $userid])
    ];
    
    // Grade requirement
    $grade = timedactivity_get_user_grade($timedactivity, $userid);
    $status['grade'] = (object)[
        'complete' => ($grade !== null && $grade >= ($timedactivity->passinggrade ?? 70)),
        'current' => $grade,
        'required' => $timedactivity->passinggrade ?? 70
    ];
    
    return $status;
}

/**
 * Save user's quiz answer
 * 
 * @param int $quizid The quiz ID
 * @param int $userid The user ID
 * @param int $answer The selected answer index
 * @param bool $iscorrect Whether the answer is correct
 * @return bool Success status
 */
function timedactivity_save_quiz_answer($quizid, $userid, $answer, $iscorrect) {
    global $DB;
    
    // Create a new attempt record to preserve attempt history in database
    $attempt = new stdClass();
    $attempt->quizid = $quizid;
    $attempt->userid = $userid;
    $attempt->answer = $answer;
    $attempt->iscorrect = $iscorrect ? 1 : 0;
    $attempt->timeattempted = time();
    return $DB->insert_record('timedactivity_quiz_attempts', $attempt);
}

/**
 * Format time in seconds to human readable string
 * 
 * @param int $seconds Time in seconds
 * @return string Formatted time string
 */
function timedactivity_format_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($minutes > 0 || $hours > 0) {
        $parts[] = $minutes . 'm';
    }
    $parts[] = $secs . 's';
    
    return implode(' ', $parts);
}

/**
 * Get user's attempts history for this activity
 * 
 * @param object $timedactivity The timed activity record
 * @param int $userid The user ID
 * @return array Attempt history
 */
function timedactivity_get_attempts_history($timedactivity, $userid) {
    global $DB;
    
    $track = $DB->get_record('timedactivity_tracking', [
        'timedactivityid' => $timedactivity->id,
        'userid' => $userid
    ]);
    
    if (!$track) {
        return (object)[
            'attempts' => 0,
            'first_access' => null,
            'last_access' => null,
            'total_time_spent' => 0
        ];
    }
    
    // Get first and last access from logs
    $logs = $DB->get_records_sql("
        SELECT timecreated
        FROM {logstore_standard_log}
        WHERE component = 'mod_timedactivity'
        AND action = 'viewed'
        AND contextinstanceid = ?
        AND userid = ?
        ORDER BY timecreated ASC
    ", [$timedactivity->id, $userid], 0, 1);
    
    $firstlog = $DB->get_records_sql("
        SELECT timecreated
        FROM {logstore_standard_log}
        WHERE component = 'mod_timedactivity'
        AND action = 'viewed'
        AND contextinstanceid = ?
        AND userid = ?
        ORDER BY timecreated DESC
    ", [$timedactivity->id, $userid], 0, 1);
    
    return (object)[
        'attempts' => $track->attempts,
        'first_access' => $firstlog ? reset($firstlog)->timecreated : null,
        'last_access' => $track->timemodified,
        'total_time_spent' => $track->totaltimespent
    ];
}

/**
 * Automatically check and create the timedactivity_visits table if it doesn't exist.
 */
function timedactivity_check_visits_table() {
    global $DB;
    $dbman = $DB->get_manager();
    $table = new xmldb_table('timedactivity_visits');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timedactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionstarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('watchtime', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastaccess', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
}