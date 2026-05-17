<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_timedactivity_save_quiz_answer' => array(
        'classname' => 'mod_timedactivity\external\save_quiz_answer',
        'methodname' => 'save_quiz_answer',
        'description' => 'Save user answer to a quiz popup',
        'type' => 'write',
        'ajax' => true,
    ),
);