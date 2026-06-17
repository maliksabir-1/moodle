<?php
// local/advancedanalytics/db/services.php
// Defines web services for external API access

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_advancedanalytics_get_kpis' => [
        'classname' => 'local_advancedanalytics\external\get_kpis',
        'methodname' => 'get_kpis',
        'description' => 'Get analytics KPIs for dashboard',
        'type' => 'read',
        'capabilities' => 'local/advancedanalytics:viewadmin',
        'ajax' => true,
    ],
    'local_advancedanalytics_get_learners' => [
        'classname' => 'local_advancedanalytics\external\get_learners',
        'methodname' => 'get_learners',
        'description' => 'Get learner performance data',
        'type' => 'read',
        'capabilities' => 'local/advancedanalytics:viewmanager',
        'ajax' => true,
    ],
];

$services = [
    'Advanced Analytics Service' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];