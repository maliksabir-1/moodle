<?php
define('CLI_SCRIPT', true);
require('config.php');
global $DB;
$logs = $DB->get_records_sql("SELECT id, eventname, component, action, target FROM {logstore_standard_log} ORDER BY timecreated DESC LIMIT 100");
foreach ($logs as $log) {
    echo $log->eventname . " (" . $log->component . ")\n";
}
