<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$duration = required_param('duration', PARAM_INT);

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
    $track->timemodified = time();
    $track->id = $DB->insert_record('timedactivity_tracking', $track);
} else {
    $track->totaltimespent += $duration;
    $track->timemodified = time();
    $DB->update_record('timedactivity_tracking', $track);
}

// Check completion.
$iscomplete = false;
if ($timedactivity->requiredtime > 0 && $track->totaltimespent >= $timedactivity->requiredtime) {
    $iscomplete = true;
    
    // Update Moodle completion state.
    $completion = new completion_info(get_course($cm->course));
    if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    }
}

echo json_encode(array(
    'success' => true,
    'totaltime' => $track->totaltimespent,
    'complete' => $iscomplete
));