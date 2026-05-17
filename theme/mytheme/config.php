<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'mytheme';
$THEME->parents = ['boost'];
$THEME->enable_dock = false;
$THEME->sheets = ['custom'];
$THEME->editor_sheets = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_DEFAULT;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
// In config.php - ensure AJAX scripts can access theme functions
$THEME->has_custom_ajax_handler = true;
$THEME->layouts = [
    'standard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'frontpage' => [
        'file' => 'frontpage.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
        'options' => ['langmenu' => true],
    ],
    'mydashboard' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'admin' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
    'embedded' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'popup' => [
        'file' => 'drawers.php',
        'regions' => [],
        'options' => ['nofooter' => true, 'nonavbar' => true],
    ],
    'base' => [
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
    'report' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'social' => [
        'file' => 'drawers.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
    'signup' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
];

