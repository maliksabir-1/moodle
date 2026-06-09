<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'availability_level';
$plugin->version = 2026060301;
$plugin->requires = 2025041406;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.0';
$plugin->dependencies = [
    'local_point_badges' => 2026060901,
];
