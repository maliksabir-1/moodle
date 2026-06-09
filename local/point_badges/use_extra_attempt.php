<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$quizid = required_param('quizid', PARAM_INT);

$cm = get_coursemodule_from_instance('quiz', $quizid);

if (!$cm) {
    redirect(new moodle_url('/local/point_badges/shop.php'), 'Quiz not found!', null, 'error');
}

// Check if user has remaining extra attempts
$remaining = \local_point_badges\quiz_manager::get_remaining_extra_attempts($USER->id, $quizid);

if ($remaining <= 0) {
    redirect(new moodle_url('/local/point_badges/shop.php'), 'No extra attempts left! Purchase from shop.', null, 'error');
}

// Just redirect to quiz - access rule will handle marking extra attempts
redirect(new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]), 
        '✓ You have ' . $remaining . ' extra attempt(s) available!', 
        null, 
        'success');
?>