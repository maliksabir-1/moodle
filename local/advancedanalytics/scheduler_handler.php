<?php
// local/advancedanalytics/scheduler_handler.php
// Handle report scheduling requests

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$delete = optional_param('delete', 0, PARAM_INT);
if ($delete) {
    $DB->delete_records('local_aa_reports', ['id' => $delete]);
    redirect(new moodle_url('/local/advancedanalytics/index.php', ['view' => 'reports']), 'Schedule removed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = optional_param('type', '', PARAM_ALPHA);
    if (empty($type)) {
        throw new moodle_exception('missing_report_type', 'local_advancedanalytics', '', null, 'The "type" parameter is required for scheduling.');
    }
    $frequency = required_param('frequency', PARAM_ALPHA);
    $format = required_param('format', PARAM_ALPHA);
    $recipients = required_param('recipients', PARAM_TEXT);
    
    $record = new \stdClass();
    $record->userid = $USER->id;
    $record->name = strtoupper($type) . " " . strtoupper($frequency);
    $record->report_type = $type;
    $record->frequency = $frequency;
    $record->format = $format;
    $record->recipients = $recipients;
    $record->status = 1;
    $record->timecreated = time();
    $record->timemodified = time();
    
    $DB->insert_record('local_aa_reports', $record);
    redirect(new moodle_url('/local/advancedanalytics/index.php', ['view' => 'reports']), 'New report schedule created.');
}

redirect(new moodle_url('/local/advancedanalytics/index.php', ['view' => 'reports']));
