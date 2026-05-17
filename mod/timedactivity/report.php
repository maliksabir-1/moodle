<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('timedactivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/timedactivity:viewreports', $context);

$PAGE->set_url('/mod/timedactivity/report.php', array('id' => $cm->id));
$PAGE->set_title('Timed Activity Report');
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading('Report for ' . format_string($timedactivity->name));

$tracks = $DB->get_records('timedactivity_tracking', ['timedactivityid' => $timedactivity->id]);

if ($tracks) {
    $table = new html_table();
    $table->head = ['User', 'Time Spent', 'Progress (%)', 'Attempts', 'Status'];
    foreach ($tracks as $t) {
        $user = $DB->get_record('user', ['id' => $t->userid]);
        $progress = $timedactivity->requiredtime > 0 ? round(($t->totaltimespent / $timedactivity->requiredtime) * 100, 1) : 100;
        $status = ($t->totaltimespent >= $timedactivity->requiredtime) ? 'Complete' : 'Incomplete';
        $table->data[] = [fullname($user), format_time($t->totaltimespent), $progress . '%', $t->attempts, $status];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification('No tracking data yet.', 'info');
}

echo $OUTPUT->footer();
