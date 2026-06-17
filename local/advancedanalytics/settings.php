<?php
// local/advancedanalytics/settings.php
// Plugin settings page

defined('MOODLE_INTERNAL') || die();

global $ADMIN;

if (has_capability('local/advancedanalytics:managesettings', context_system::instance())) {
    
    $settings = new admin_settingpage('local_advancedanalytics', 
        get_string('pluginname', 'local_advancedanalytics'));
    
    $ADMIN->add('localplugins', $settings);
    
    // General settings heading
    $settings->add(new admin_setting_heading(
        'local_advancedanalytics/general',
        get_string('analytics_settings', 'local_advancedanalytics'),
        'Configure the analytics dashboard settings'
    ));
    
    // Enable cron aggregation
    $settings->add(new admin_setting_configcheckbox(
        'local_advancedanalytics/cron_enabled',
        get_string('cron_enabled', 'local_advancedanalytics'),
        get_string('cron_enabled_desc', 'local_advancedanalytics'),
        1
    ));
    
    // Data retention period
    $settings->add(new admin_setting_configtext(
        'local_advancedanalytics/data_retention_days',
        get_string('data_retention_days', 'local_advancedanalytics'),
        get_string('data_retention_days_desc', 'local_advancedanalytics'),
        365,
        PARAM_INT
    ));
    
    // Dashboard menu entry
    $ADMIN->add('reports', new admin_externalpage(
        'local_advancedanalytics_dashboard',
        get_string('dashboard', 'local_advancedanalytics'),
        new moodle_url('/local/advancedanalytics/index.php'),
        'local/advancedanalytics:viewadmin'
    ));
}