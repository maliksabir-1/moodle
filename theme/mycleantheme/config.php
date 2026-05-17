<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'mycleantheme';
$THEME->parents = ['boost'];
$THEME->enable_dock = false;
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_DEFAULT;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;

// Define layouts to use drawers.php for all main pages including admin.
$THEME->layouts = [
    'standard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'mydashboard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'course' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'incourse' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'admin' => [
        'file' => 'drawers.php',  // Use drawers.php for admin pages (includes navbar)
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
];