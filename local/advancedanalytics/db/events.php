<?php
// local/advancedanalytics/db/events.php
// Defines events for analytics tracking

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback' => '\local_advancedanalytics\observer::user_loggedin',
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_advancedanalytics\observer::course_completed',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_advancedanalytics\observer::quiz_attempt_submitted',
    ],
];