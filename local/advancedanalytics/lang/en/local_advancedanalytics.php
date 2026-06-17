<?php
// local/advancedanalytics/lang/en/local_advancedanalytics.php
// English language strings

defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['pluginname'] = 'Advanced Analytics Dashboard';

// Dashboard titles
$string['dashboard'] = 'Analytics Dashboard';
$string['executive_dashboard'] = 'Executive Dashboard';
$string['manager_dashboard'] = 'Manager Dashboard';
$string['hr_dashboard'] = 'HR Analytics Dashboard';

// Permissions
$string['advancedanalytics:viewadmin'] = 'View admin analytics dashboard';
$string['advancedanalytics:viewmanager'] = 'View manager analytics dashboard';
$string['advancedanalytics:viewhr'] = 'View HR analytics dashboard';
$string['no_permission'] = 'You do not have permission to view this page';

// KPI labels
$string['total_users'] = 'Total Users';
$string['active_users'] = 'Active Users';
$string['total_courses'] = 'Total Courses';
$string['completion_rate'] = 'Completion Rate';
$string['average_grade'] = 'Average Grade';
$string['engagement_rate'] = 'Engagement Rate';
$string['certification_rate'] = 'Certification Rate';

// Settings
$string['analytics_settings'] = 'Analytics Settings';
$string['cron_enabled'] = 'Enable automatic data aggregation';
$string['cron_enabled_desc'] = 'Automatically aggregate analytics data via cron job';
$string['data_retention_days'] = 'Data retention period (days)';
$string['data_retention_days_desc'] = 'How long to keep detailed analytics data';

// Errors
$string['error_loading_data'] = 'Error loading analytics data';
$string['error_insufficient_permissions'] = 'Insufficient permissions to view this data';

// Export
$string['export_pdf'] = 'Export to PDF';
$string['export_excel'] = 'Export to Excel';
$string['export_csv'] = 'Export to CSV';
$string['export_success'] = 'Report exported successfully';