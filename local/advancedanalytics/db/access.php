<?php
// local/advancedanalytics/db/access.php
// Capabilities for the plugin

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Admin capability
    'local/advancedanalytics:viewadmin' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    
    // Manager capability
    'local/advancedanalytics:viewmanager' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
    
    // HR capability
    'local/advancedanalytics:viewhr' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [],
    ],
    
    // Export capability
    'local/advancedanalytics:exportdata' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    
    // Settings management
    'local/advancedanalytics:managesettings' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];