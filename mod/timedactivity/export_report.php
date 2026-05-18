<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

$id = required_param('id', PARAM_INT);
$userid_param = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('timedactivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/timedactivity:viewreports', $context);

// Initialize visits table if needed
timedactivity_check_visits_table();

// Set headers for CSV download
header('Content-Type: text/csv');
$filename = 'timedactivity_visits_report_' . $timedactivity->id . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add UTF-8 BOM for Excel compatibility
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

// Define CSV headers
$headers = [
    'Student Name',
    'Session Started',
    'Watch Time'
];
fputcsv($output, $headers);

// Build SQL query
$params = [$timedactivity->id];
$sql = "SELECT v.id, v.userid, v.sessionstarted, v.watchtime 
        FROM {timedactivity_visits} v
        WHERE v.timedactivityid = ?";

if ($userid_param > 0) {
    $sql .= " AND v.userid = ?";
    $params[] = $userid_param;
}

$sql .= " ORDER BY v.sessionstarted DESC";
$visits = $DB->get_records_sql($sql, $params);

foreach ($visits as $visit) {
    $user = $DB->get_record('user', ['id' => $visit->userid]);
    $username = $user ? fullname($user) : 'Unknown User';
    
    $row = [
        $username,
        userdate($visit->sessionstarted),
        timedactivity_format_time($visit->watchtime)
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;