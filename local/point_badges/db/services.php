<?php
// /local/point_badges/db/services.php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_point_badges_check_restrictions' => [
        'classname' => 'local_point_badges\external\restrictions',
        'methodname' => 'check_restrictions',
        'classpath' => '',
        'description' => 'Check if user has access to restricted activities',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Point Badges Service' => [
        'functions' => ['local_point_badges_check_restrictions'],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];