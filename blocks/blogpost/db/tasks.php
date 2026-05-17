<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'block_blogpost\task\send_blog_notifications',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];