<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    // 1. Create user
    'local_webservice_create_user' => [
        'classname'   => 'local_webservice\external\user',
        'methodname'  => 'create_user',
        'description' => 'Creates a new user in Moodle',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    // 2. Enrol user in course
    'local_webservice_enrol_user' => [
        'classname'   => 'local_webservice\external\user',
        'methodname'  => 'enrol_user',
        'description' => 'Enrols a user into a course',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    // 3. Suspend user from course
    'local_webservice_suspend_user' => [
        'classname'   => 'local_webservice\external\user',
        'methodname'  => 'suspend_user',
        'description' => 'Suspends a user enrolment in a specific course',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    // 4. Get complete course details
    'local_webservice_get_course_details' => [
        'classname'   => 'local_webservice\external\user',
        'methodname'  => 'get_course_details',
        'description' => 'Get course details including enrolled users, progress, grades, and activities',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

$services = [
    'Local Web Service' => [
        'functions' => [
            'local_webservice_create_user',
            'local_webservice_enrol_user',
            'local_webservice_suspend_user',
            'local_webservice_get_course_details'
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'shortname' => 'local_webservice',
    ],
];