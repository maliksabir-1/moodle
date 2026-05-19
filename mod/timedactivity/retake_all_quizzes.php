<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
require_sesskey();

header('Content-Type: application/json');

try {
    $cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
    $timedactivity = $DB->get_record('timedactivity', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($cm->course, true, $cm);

    // Check if retakes are allowed
    if (empty($timedactivity->retakesallowed)) {
        echo json_encode(['success' => false, 'error' => 'Retakes not allowed for this activity']);
        exit;
    }

    // Start transaction
    $transaction = $DB->start_delegated_transaction();

    // Reset video position to 0
    $DB->set_field('timedactivity_tracking', 'videoposition', 0, [
        'timedactivityid' => $timedactivity->id,
        'userid' => $USER->id
    ]);

    // Reset all quiz attempts for this user
    $quiz_ids = $DB->get_fieldset_select('timedactivity_quiz', 'id', 'timedactivityid = ?', [$timedactivity->id]);
    if (!empty($quiz_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($quiz_ids);
        $params[] = $USER->id;
        $DB->delete_records_select('timedactivity_quiz_attempts', "quizid {$insql} AND userid = ?", $params);
    }

    // Update grade and completion
    timedactivity_update_user_grade_and_completion($timedactivity, $USER->id);

    // Commit transaction
    $transaction->allow_commit();

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}