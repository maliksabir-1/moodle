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
$filter_userid = optional_param('filter_userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('timedactivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/timedactivity:viewreports', $context);

// Initialize/verify schema
timedactivity_check_visits_table();

$PAGE->set_url('/mod/timedactivity/report.php', array('id' => $cm->id));
$PAGE->set_title('Timed Activity Report - ' . format_string($timedactivity->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Stylesheet integration for premium dashboard cards and real-time filters
echo html_writer::start_tag('style');
echo '
.report-card {
    background: #ffffff;
    border: 1px solid #e3e6f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 25px;
    overflow: hidden;
}
.report-card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
    padding: 15px 20px;
    font-weight: bold;
    color: #4e73df;
    font-size: 1.15em;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.report-card-body {
    padding: 20px;
}
.stat-box {
    background: #f8f9fc;
    border-left: 4px solid #4e73df;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.stat-box-title {
    font-size: 0.85em;
    font-weight: bold;
    color: #858796;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.stat-box-value {
    font-size: 1.6em;
    font-weight: bold;
    color: #5a5c69;
}
.status-pill-complete {
    background-color: #d4edda;
    color: #155724;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: bold;
    display: inline-block;
}
.status-pill-pending {
    background-color: #fff3cd;
    color: #856404;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: bold;
    display: inline-block;
}
.status-pill-failed {
    background-color: #f8d7da;
    color: #721c24;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: bold;
    display: inline-block;
}
.search-container {
    background: #f8f9fc;
    border: 1px solid #e3e6f0;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}
';
echo html_writer::end_tag('style');

$icon = $OUTPUT->pix_icon('icon', '', 'mod_timedactivity', ['class' => 'activityicon mr-2', 'style' => 'width:28px; height:28px; vertical-align:middle;']);
echo $OUTPUT->heading($icon . ' Report: ' . format_string($timedactivity->name), 2, 'heading-with-icon d-flex align-items-center mb-4');

// Retrieve all enrolled students
$enrolled_users = get_enrolled_users($context, 'mod/timedactivity:view');
$students_count = count($enrolled_users);

// Pre-calculate summary statistics and track data
$completed_count = 0;
$total_attempts = 0;
$summary_data = [];

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
    $total_attempts += $attempts;
    
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
        $completed_count++;
        $status = 'Complete';
        $status_pill = '<span class="status-pill-complete">Complete</span>';
    } else if ($timedactivity->allowedattempts > 0 && $attempts >= $timedactivity->allowedattempts) {
        $status = 'Failed';
        $status_pill = '<span class="status-pill-failed">Failed</span>';
    } else {
        $status = 'Pending';
        $status_pill = '<span class="status-pill-pending">Pending</span>';
    }
    
    $summary_data[] = (object)[
        'fullname' => fullname($student),
        'total_visits' => $total_visits,
        'total_watch' => $total_watch,
        'status' => $status,
        'status_pill' => $status_pill
    ];
}

$completion_rate = $students_count > 0 ? round(($completed_count / $students_count) * 100, 1) : 0;
$avg_attempts = $students_count > 0 ? round($total_attempts / $students_count, 1) : 0;

// ── RENDER TOP STATS BLOCKS ──
echo '<div class="row mb-4">';

echo '<div class="col-md-4">';
echo '<div class="stat-box">';
echo '<div class="stat-box-title">Total Enrolled Students</div>';
echo '<div class="stat-box-value">' . $students_count . '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-4">';
echo '<div class="stat-box" style="border-left-color: #28a745;">';
echo '<div class="stat-box-title">Completion Rate</div>';
echo '<div class="stat-box-value">' . $completed_count . ' <small style="font-size: 0.6em; color: #6c757d;">(' . $completion_rate . '%)</small></div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-4">';
echo '<div class="stat-box" style="border-left-color: #ffc107;">';
echo '<div class="stat-box-title">Average Attempts</div>';
echo '<div class="stat-box-value">' . $avg_attempts . '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

// ── TABLE 2: THE SUMMARY TABLE (FIRST ROW FOR BETTER PROMINENCE) ──
echo '<div class="report-card">';
echo '<div class="report-card-header">';
echo '<span>📋 Student Summary Dashboard</span>';
echo '</div>';
echo '<div class="report-card-body">';

// Search and Dropdown Filter Container
echo '<div class="search-container row align-items-center">';
echo '<div class="col-md-6 mb-2 mb-md-0">';
echo '<label class="font-weight-bold" style="font-size: 0.9em; color:#495057;">🔍 Search Student Name</label>';
echo '<input type="text" id="summary-search" class="form-control" placeholder="Type student name to search...">';
echo '</div>';
echo '<div class="col-md-6">';
echo '<label class="font-weight-bold" style="font-size: 0.9em; color:#495057;">📌 Filter by Status</label>';
echo '<select id="summary-status-filter" class="form-control">';
echo '<option value="all">All Statuses</option>';
echo '<option value="complete">Complete</option>';
echo '<option value="pending">Pending</option>';
echo '<option value="failed">Failed</option>';
echo '</select>';
echo '</div>';
echo '</div>';

// Render Summary Table
$summary_table = new html_table();
$summary_table->head = ['Student Name', 'Total Visits', 'Total Watch Time', 'Status'];
$summary_table->width = '100%';
$summary_table->attributes['class'] = 'generaltable table table-striped table-hover mb-0';
$summary_table->id = 'summary-report-table';

foreach ($summary_data as $row) {
    $table_row = new html_table_row();
    $table_row->attributes['data-fullname'] = strtolower($row->fullname);
    $table_row->attributes['data-status'] = strtolower($row->status);
    $table_row->cells = [
        new html_table_cell($row->fullname),
        new html_table_cell($row->total_visits),
        new html_table_cell(timedactivity_format_time($row->total_watch)),
        new html_table_cell($row->status_pill)
    ];
    $summary_table->data[] = $table_row;
}

echo html_writer::table($summary_table);

// Download Summary Action Button
echo html_writer::start_div('d-flex justify-content-end mt-3');
$export_summary_url = new moodle_url('/mod/timedactivity/export_summary.php', ['id' => $cm->id]);
echo html_writer::link($export_summary_url, '📥 Download Summary Data (CSV)', [
    'id' => 'summary-download-btn',
    'class' => 'btn btn-success font-weight-bold px-4 py-2',
    'style' => 'border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'
]);
echo html_writer::end_div();

echo '</div>';
echo '</div>';

// ── TABLE 1: THE GENERAL TABLE (VISIT HISTORY LOGS) ──
echo '<div class="report-card">';
echo '<div class="report-card-header">';
echo '<span>🎥 Granular Visit & Watch Logs</span>';

// General Table student filter dropdown
echo '<div class="d-flex align-items-center">';
echo '<span class="mr-2 text-muted font-weight-bold" style="font-size: 0.85em;">Select Student:</span>';
echo '<select id="general-student-filter" class="form-control form-control-sm" style="width: auto; display: inline-block;">';
echo '<option value="0">All Students</option>';
foreach ($enrolled_users as $student) {
    echo '<option value="' . $student->id . '">' . fullname($student) . '</option>';
}
echo '</select>';
echo '</div>';

echo '</div>';
echo '<div class="report-card-body">';

// Retrieve all visits for this activity
$visit_sql = "SELECT v.id, v.userid, v.sessionstarted, v.watchtime 
              FROM {timedactivity_visits} v
              WHERE v.timedactivityid = ?
              ORDER BY v.sessionstarted DESC";
$visits = $DB->get_records_sql($visit_sql, [$timedactivity->id]);

if ($visits) {
    $general_table = new html_table();
    $general_table->head = ['Student Name', 'Session Started', 'Watch Time'];
    $general_table->width = '100%';
    $general_table->attributes['class'] = 'generaltable table table-striped table-hover mb-3';
    $general_table->id = 'general-report-table';
    
    foreach ($visits as $visit) {
        $student_user = $DB->get_record('user', ['id' => $visit->userid]);
        $student_name = $student_user ? fullname($student_user) : 'Unknown User';
        
        $table_row = new html_table_row();
        $table_row->attributes['data-userid'] = $visit->userid;
        $table_row->cells = [
            new html_table_cell($student_name),
            new html_table_cell(userdate($visit->sessionstarted)),
            new html_table_cell(timedactivity_format_time($visit->watchtime))
        ];
        $general_table->data[] = $table_row;
    }
    
    echo html_writer::table($general_table);
    
    // Download Action Button
    echo html_writer::start_div('d-flex justify-content-end');
    $export_url = new moodle_url('/mod/timedactivity/export_report.php', ['id' => $cm->id]);
    echo html_writer::link($export_url, '📥 Download Table Data (CSV)', [
        'id' => 'general-download-btn',
        'class' => 'btn btn-primary font-weight-bold px-4 py-2',
        'style' => 'border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'
    ]);
    echo html_writer::end_div();
} else {
    echo $OUTPUT->notification('No visit history logs found.', 'info');
}

echo '</div>';
echo '</div>';

// ── CLIENT-SIDE INTERACTIVE JS FILTER ENGINE ──
echo html_writer::start_tag('script');
echo '
document.addEventListener("DOMContentLoaded", function() {
    // 1. General Table Student Filter WITHOUT Page Reload
    var genFilter = document.getElementById("general-student-filter");
    var generalRows = document.querySelectorAll("#general-report-table tbody tr");
    var downloadBtn = document.getElementById("general-download-btn");

    if (genFilter) {
        genFilter.addEventListener("change", function() {
            var selectedVal = this.value;
            
            // Filter general table rows
            generalRows.forEach(function(row) {
                if (selectedVal === "0" || row.getAttribute("data-userid") === selectedVal) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
            
            // Update CSV download button link to target selected user
            if (downloadBtn) {
                var baseHref = "export_report.php?id=' . $cm->id . '";
                if (selectedVal !== "0") {
                    downloadBtn.setAttribute("href", baseHref + "&userid=" + selectedVal);
                } else {
                    downloadBtn.setAttribute("href", baseHref);
                }
            }
        });
    }

    // 2. Summary Table Instant Search and Status Filter
    var searchInput = document.getElementById("summary-search");
    var statusFilter = document.getElementById("summary-status-filter");
    var summaryTableRows = document.querySelectorAll("#summary-report-table tbody tr");

    function applySummaryFilters() {
        var query = searchInput.value.toLowerCase().trim();
        var statusVal = statusFilter.value;

        summaryTableRows.forEach(function(row) {
            var nameMatch = true;
            var statusMatch = true;

            // Search query match
            if (query !== "") {
                var fullname = row.getAttribute("data-fullname") || "";
                if (fullname.indexOf(query) === -1) {
                    nameMatch = false;
                }
            }

            // Status filter match
            if (statusVal !== "all") {
                var rowStatus = row.getAttribute("data-status") || "";
                if (rowStatus !== statusVal) {
                    statusMatch = false;
                }
            }

            if (nameMatch && statusMatch) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener("input", applySummaryFilters);
    }
    if (statusFilter) {
        statusFilter.addEventListener("change", applySummaryFilters);
    }
});
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();