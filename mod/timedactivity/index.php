<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course, true);

$PAGE->set_url('/mod/timedactivity/index.php', array('id' => $id));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);

echo $OUTPUT->header();

$modinfo = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('timedactivity');

if (!$instances) {
    notice(get_string('notimers', 'mod_timedactivity'), new moodle_url('/course/view.php', array('id' => $course->id)));
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = array(get_string('name'), get_string('requiredtime', 'mod_timedactivity'), get_string('completion', 'completion'));
$table->align = array('left', 'center', 'center');

foreach ($instances as $instance) {
    $cm = $instance;
    $context = context_module::instance($cm->id);
    $timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance));

    $required = $timedactivity->requiredtime ? format_time($timedactivity->requiredtime) : get_string('no');
    $completion = new completion_info($course);
    $iscomplete = $completion->is_course_complete($USER->id) || $completion->get_data($cm, false, $USER->id)->completionstate == COMPLETION_COMPLETE;
    $status = $iscomplete ? get_string('complete', 'completion') : get_string('incomplete', 'completion');

    $row = array(
        html_writer::link(new moodle_url('/mod/timedactivity/view.php', array('id' => $cm->id)), $timedactivity->name),
        $required,
        $status
    );
    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();