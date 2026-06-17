<?php
// local/advancedanalytics/cli/hard_reset_sync.php
// CLI script to completely wipe analytics cache and perform a fresh sync

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'verbose' => false],
    ['h' => 'help', 'v' => 'verbose']
);

if ($options['help']) {
    echo "Hard Reset Analytics Sync\n";
    echo "Usage: php hard_reset_sync.php [-v]\n";
    exit;
}

mtrace("== 🧹 HARD RESET: Wiping all cached analytics ==");
$DB->execute("TRUNCATE TABLE {local_aa_summary}");
$DB->execute("TRUNCATE TABLE {local_aa_dept_stats}");
$DB->execute("TRUNCATE TABLE {local_aa_user_perf}");
$DB->execute("TRUNCATE TABLE {local_aa_user_compliance}");
$DB->execute("TRUNCATE TABLE {local_aa_course_cache}");
mtrace("✅ All tables truncated.");

mtrace("\n== 🔄 STARTING FRESH SYNC ==");
\local_advancedanalytics\cron\data_sync::sync_all(true);

mtrace("\n== ✨ RESET COMPLETE! ==");
mtrace("Your dashboard should now show the correct 'Real-World' numbers.");
