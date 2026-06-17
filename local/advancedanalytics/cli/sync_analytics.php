#!/usr/bin/env php
<?php
// local/advancedanalytics/cli/sync_analytics.php
// Manual CLI script to trigger analytics sync

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/data_extractor.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/cron/data_sync.php');

// CLI options
list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'verbose' => false,
], [
    'h' => 'help',
    'v' => 'verbose',
]);

if ($options['help']) {
    echo "Analytics Data Sync Tool\n\n";
    echo "Options:\n";
    echo "  -h, --help     Print this help\n";
    echo "  -v, --verbose  Show detailed output\n\n";
    echo "Usage: php cli/sync_analytics.php [-v]\n";
    exit(0);
}

cli_heading('Advanced Analytics - Data Sync');

$verbose = !empty($options['verbose']);

try {
    \local_advancedanalytics\cron\data_sync::sync_all($verbose);
    cli_writeln("\n✅ Sync completed successfully!");
} catch (Exception $e) {
    cli_writeln("\n❌ Error: " . $e->getMessage());
    exit(1);
}

exit(0);