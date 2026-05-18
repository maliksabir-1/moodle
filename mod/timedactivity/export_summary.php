<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

$id = required_param('id', PARAM_INT);

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
$filename = 'timedactivity_summary_report_' . $timedactivity->id . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add UTF-8 BOM for Excel compatibility
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

// Define CSV headers
$headers = [
    'Student Name',
    'Total Visits',
    'Total Watch Time',
    'Status'
];
fputcsv($output, $headers);

// Retrieve all enrolled students
$enrolled_users = get_enrolled_users($context, 'mod/timedactivity:view');
$completion = new completion_info($course);

foreach ($enrolled_users as $student) {
    // Count student visits
    $total_visits = $DB->count_records('timedactivity_visits', [
        'timedactivityid' => $timedactivity->id,
        'userid' => $student->id
    ]);
    
    // Sum student watch time
    $total_watch = $DB->get_field_sql("
        SELECT SUM(watchtime) 
        FROM {timedactivity_visits} 
        WHERE timedactivityid = ? AND userid = ?
    ", [$timedactivity->id, $student->id]) ?: 0;
    
    // Get cumulative tracker record
    $track = $DB->get_record('timedactivity_tracking', [
        'timedactivityid' => $timedactivity->id,
        'userid' => $student->id
    ]);
    
    $attempts = $track ? ($track->attempts ?? 1) : 0;
    
    // Calculate status: Complete, Failed, or Pending
    $is_time_complete = ($timedactivity->requiredtime <= 0 || ($track && $track->totaltimespent >= $timedactivity->requiredtime));
    $are_quizzes_complete = timedactivity_are_all_quizzes_complete($timedactivity, $student->id);
    
    $is_complete = $is_time_complete && $are_quizzes_complete;
    $grade = timedactivity_get_user_grade($timedactivity, $student->id);
    if ($timedactivity->grademethod > 0 && $timedactivity->passinggrade > 0 && ($grade === null || $grade < $timedactivity->passinggrade)) {
        $is_complete = false;
    }
    
    if ($completion->is_enabled($cm)) {
        $cm_data = $completion->get_data($cm, false, $student->id);
        if ($cm_data->completionstate == COMPLETION_COMPLETE) {
            $is_complete = true;
        }
    }
    
    if ($timedactivity->requiredtime > 0 && (!$track || $track->totaltimespent < $timedactivity->requiredtime)) {
        $is_complete = false;
    }
    
    if ($is_complete) {
        $status = 'Complete';
    } else if ($timedactivity->allowedattempts > 0 && $attempts >= $timedactivity->allowedattempts) {
        $status = 'Failed';
    } else {
        $status = 'Pending';
    }
    
    $row = [
        fullname($student),
        $total_visits,
        timedactivity_format_time($total_watch),
        $status
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
