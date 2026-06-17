<?php
// local/advancedanalytics/db/tasks.php
// Scheduled tasks

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_advancedanalytics\task\aggregate_analytics',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_advancedanalytics\task\send_scheduled_reports',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];