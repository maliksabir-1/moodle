<?php
require_once('../../config.php');
$cmid = required_param('cmid', PARAM_INT);
$duration = required_param('duration', PARAM_INT);
require_sesskey();
$cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', ['id' => $cm->instance], '*', MUST_EXIST);
if ($timedactivity->matchduration) {
    $DB->set_field('timedactivity', 'requiredtime', $duration, ['id' => $timedactivity->id]);
}
echo json_encode(['success' => true]);
