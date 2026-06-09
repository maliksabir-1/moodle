<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_loggedin',
        'callback' => '\local_point_badges\observer::user_loggedin',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_point_badges\observer::quiz_completed',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_point_badges\observer::assignment_submitted',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_assign\event\submission_status_updated',
        'callback' => '\local_point_badges\observer::assignment_submitted',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_assign\event\submission_created',
        'callback' => '\local_point_badges\observer::assignment_submitted',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_assign\event\submission_updated',
        'callback' => '\local_point_badges\observer::assignment_submitted',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_forum\event\post_created',
        'callback' => '\local_point_badges\observer::forum_post',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_lesson\event\lesson_completed',
        'callback' => '\local_point_badges\observer::lesson_completed',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_scorm\event\tracks_viewed',
        'callback' => '\local_point_badges\observer::scorm_completed',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_attendance\event\attendance_taken',
        'callback' => '\local_point_badges\observer::attendance_taken',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_point_badges\observer::course_completed',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_point_badges\observer::activity_completed',
        'internal' => true,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_viewed',
        'callback' => '\local_point_badges\observer::before_quiz_attempt',
        'internal' => true,
    ],
];

// ========== CRITICAL: This is the correct way to register course module visibility callback ==========
// This function name MUST be exactly: local_<pluginname>_coursemodule_visibility
// Moodle automatically calls this function for every course module