<?php
require_once('../../config.php');
$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$position = required_param('position', PARAM_FLOAT);
require_sesskey();
$cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
$DB->set_field('timedactivity_tracking', 'videoposition', $position, ['timedactivityid' => $cm->instance, 'userid' => $userid]);
echo json_encode(['success' => true]);
