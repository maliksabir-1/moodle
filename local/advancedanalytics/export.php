<?php
// local/advancedanalytics/export.php
// Landing page for report generation and downloads

require_once('../../config.php');

$type = optional_param('type', '', PARAM_ALPHA); // executive, compliance, learners
if (empty($type)) {
    throw new moodle_exception('missing_report_type', 'local_advancedanalytics', '', null, 'The "type" parameter is required for exports.');
}
$format = required_param('format', PARAM_ALPHA); // pdf, csv, excel
$dept = optional_param('dept', '', PARAM_TEXT);
$course = optional_param('course', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$filters = [
    'dept' => $dept,
    'course' => $course
];

$filename = "analytics_" . $type . "_" . date('Ymd_His');

if ($format === 'pdf') {
    $content = \local_advancedanalytics\report_generator::generate_pdf($type, $filters);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $content;
    exit;
}

if ($format === 'excel') {
    $content = \local_advancedanalytics\report_generator::generate_excel($type, $filters);
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo $content;
    exit;
}

if ($format === 'csv') {
    $content = \local_advancedanalytics\report_generator::generate_csv($type, $filters);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    echo $content;
    exit;
}

redirect(new moodle_url('/local/advancedanalytics/index.php'), 'Invalid format requested', 3);
