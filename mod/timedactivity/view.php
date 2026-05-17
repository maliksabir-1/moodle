<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);

$cm = get_coursemodule_from_id('timedactivity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$track = $DB->get_record('timedactivity_tracking', array('timedactivityid' => $timedactivity->id, 'userid' => $userid));
if (!$track) {
    $track = new stdClass();
    $track->timedactivityid = $timedactivity->id;
    $track->userid = $userid;
    $track->totaltimespent = 0;
    $track->videoposition = 0;
    $track->timemodified = time();
    $track->id = $DB->insert_record('timedactivity_tracking', $track);
}
// Increment attempts counter.
$track->attempts++;
$track->timemodified = time();
$DB->update_record('timedactivity_tracking', $track);


$videofileurl = '';
if ($timedactivity->videosource == 'local') {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_timedactivity', 'video', $timedactivity->id, 'id DESC', false);
    if ($files) {
        $file = reset($files);
        $videofileurl = moodle_url::make_pluginfile_url($context->id, 'mod_timedactivity', 'video', $timedactivity->id, '/', $file->get_filename())->out();
    }
}

$js_params = array(
    'cmid' => $cm->id,
    'userid' => $userid,
    'required' => (int)$timedactivity->requiredtime,
    'current' => (int)$track->totaltimespent,
    'savedPosition' => (float)$track->videoposition,
    'ajaxUrl' => (new moodle_url('/mod/timedactivity/track.php'))->out(false),
    'durationUrl' => (new moodle_url('/mod/timedactivity/track_duration.php'))->out(false),
    'videoSource' => $timedactivity->videosource,
    'videoFileUrl' => $videofileurl,
    'youtubeUrl' => $timedactivity->youtubeurl,
    'matchDuration' => (bool)$timedactivity->matchduration,
    'quizQuestions' => array_values($DB->get_records('timedactivity_quiz', array('timedactivityid' => $timedactivity->id), 'timeposition ASC')),
    'sesskey' => sesskey()
);

$PAGE->set_url('/mod/timedactivity/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($timedactivity->name));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_timedactivity/video_timer', 'init', array($js_params));

echo $OUTPUT->header();
echo $OUTPUT->heading($timedactivity->name);

if ($timedactivity->intro) {
    echo $OUTPUT->box(format_module_intro('timedactivity', $timedactivity, $cm->id), 'generalbox', 'intro');
}

echo html_writer::start_div('timedactivity-timer-wrapper', array('class' => 'alert alert-info text-center'));
echo html_writer::tag('h4', get_string('activityprogress', 'mod_timedactivity'));
echo html_writer::tag('div', get_string('timespent', 'mod_timedactivity') . ': <span id="timespent">' . format_time($track->totaltimespent) . '</span>');
echo html_writer::tag('div', get_string('timeremaining', 'mod_timedactivity') . ': <span id="timeremaining">' . format_time(max(0, $timedactivity->requiredtime - $track->totaltimespent)) . '</span>');
echo html_writer::tag('div', '', array('id' => 'completion-status', 'style' => 'margin-top:10px; font-weight:bold;'));
echo html_writer::end_div();

echo html_writer::start_div('video-container', array('style' => 'max-width:800px; margin:0 auto;'));
echo html_writer::tag('div', '', array('id' => 'video-player'));
echo html_writer::end_div();

echo $OUTPUT->footer();