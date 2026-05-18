<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$duration = optional_param('duration', 0, PARAM_INT);
$position = optional_param('position', null, PARAM_FLOAT);
$visitid = optional_param('visitid', 0, PARAM_INT);

require_sesskey();

$cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', array('id' => $cm->instance), '*', MUST_EXIST);

// Update tracking.
$track = $DB->get_record('timedactivity_tracking', array('timedactivityid' => $timedactivity->id, 'userid' => $userid));
if (!$track) {
    $track = new stdClass();
    $track->timedactivityid = $timedactivity->id;
    $track->userid = $userid;
    $track->totaltimespent = $duration;
    if ($position !== null) {
        $track->videoposition = (int)$position;
    }
    $track->attempts = 1;
    $track->timemodified = time();
    $track->id = $DB->insert_record('timedactivity_tracking', $track);
} else {
    $track->totaltimespent += $duration;
    if ($position !== null) {
        $track->videoposition = (int)$position;
    }
    $track->timemodified = time();
    $DB->update_record('timedactivity_tracking', $track);
}

// Update visit session tracking.
if ($visitid > 0) {
    $visit = $DB->get_record('timedactivity_visits', array('id' => $visitid));
    if ($visit) {
        $visit->watchtime += $duration;
        $visit->lastaccess = time();
        $DB->update_record('timedactivity_visits', $visit);
    }
}

require_once($CFG->dirroot . '/mod/timedactivity/lib.php');

// Trigger dynamic grade and completion re-evaluation for this user
timedactivity_update_user_grade_and_completion($timedactivity, $userid);

// Retrieve actual course module completion status
$completion = new completion_info(get_course($cm->course));
$iscomplete = false;
if ($completion->is_enabled($cm)) {
    $cm_data = $completion->get_data($cm, false, $userid);
    $iscomplete = ($cm_data->completionstate == COMPLETION_COMPLETE);
}

echo json_encode(array(
    'success' => true,
    'totaltime' => $track->totaltimespent,
    'complete' => $iscomplete
));