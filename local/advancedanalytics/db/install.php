<?php
// local/advancedanalytics/db/install.php
// Runs when plugin is first installed

defined('MOODLE_INTERNAL') || die();

function xmldb_local_advancedanalytics_install() {
    global $DB;
    
    // Use Moodle's config system - no custom table needed
    set_config('cron_enabled', 1, 'local_advancedanalytics');
    set_config('data_retention_days', 365, 'local_advancedanalytics');
    set_config('last_aggregation', 0, 'local_advancedanalytics');
    set_config('install_date', time(), 'local_advancedanalytics');
    set_config('plugin_version', '0.1.0', 'local_advancedanalytics');
    
    return true;
}