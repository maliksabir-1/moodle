<?php
// local/advancedanalytics/db/uninstall.php
// Runs when plugin is uninstalled

defined('MOODLE_INTERNAL') || die();

function xmldb_local_advancedanalytics_uninstall() {
    // Remove all configuration settings
    unset_config('cron_enabled', 'local_advancedanalytics');
    unset_config('data_retention_days', 'local_advancedanalytics');
    unset_config('last_aggregation', 'local_advancedanalytics');
    unset_config('install_date', 'local_advancedanalytics');
    unset_config('plugin_version', 'local_advancedanalytics');
    
    return true;
}