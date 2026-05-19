<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

require_sesskey();

$cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($cm->course, true, $cm);

// Get fresh quiz results
$quiz_results = timedactivity_get_user_quiz_results($timedactivity, $userid);
$quiz_answered = 0;
foreach ($quiz_results as $result) {
    if ($result->iscorrect) {
        $quiz_answered++;
    }
}

$user_grade = timedactivity_get_user_grade($timedactivity, $userid);
$passed = ($user_grade !== null && $user_grade >= $timedactivity->passinggrade);

// Format quiz results for JSON response
$formatted_results = [];
foreach ($quiz_results as $result) {
    $formatted_results[] = [
        'id' => $result->id,
        'timeposition' => $result->timeposition,
        'questiontext' => $result->questiontext,
        'options' => $result->options,
        'correctanswer' => $result->correctanswer,
        'explanation' => $result->explanation,
        'useranswer' => $result->useranswer,
        'iscorrect' => $result->iscorrect,
        'answered' => $result->answered
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'quiz_answered' => $quiz_answered,
    'total_quizzes' => count($quiz_results),
    'grade' => $user_grade,
    'passed' => $passed,
    'quiz_results' => $formatted_results
]);