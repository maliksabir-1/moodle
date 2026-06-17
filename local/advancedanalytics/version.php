<?php
// local/advancedanalytics/version.php
// Plugin version and metadata

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_advancedanalytics';
$plugin->version   = 2024100107;  // Fixed event observers and session mutations
$plugin->requires  = 2021051700;  // Moodle 3.11+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->cron      = 3600;        // Run cron every hour