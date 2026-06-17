<?php
// local/advancedanalytics/debug_completion.php
require_once('../../config.php');
require_login();
global $DB;

echo "<h3>Completion Debug</h3>";
$total_cc = $DB->count_records('course_completions');
echo "Total records in course_completions: " . $total_cc . "<br>";

$all = $DB->get_records('course_completions', null, '', 'id, userid, course, timecompleted');
echo "<h4>Raw Data (First 10 records):</h4>";
foreach($all as $a) {
    echo "ID: $a->id | UserID: $a->userid | CourseID: $a->course | TimeCompleted: " . ($a->timecompleted ?: 'NULL/0') . "<br>";
}

