<?php
require_once('../../config.php');
$cmid = required_param('cmid', PARAM_INT);
$duration = optional_param('duration', 0, PARAM_INT);
require_sesskey();
$cm = get_coursemodule_from_id('timedactivity', $cmid, 0, false, MUST_EXIST);
$timedactivity = $DB->get_record('timedactivity', ['id' => $cm->instance], '*', MUST_EXIST);
if ($timedactivity->matchduration) {
    $DB->set_field('timedactivity', 'requiredtime', $duration, ['id' => $timedactivity->id]);
    
    // Sync all user grades and completion based on the new matched video duration
    require_once($CFG->dirroot . '/mod/timedactivity/lib.php');
    $timedactivity->requiredtime = $duration;
    timedactivity_update_all_users_grades_and_completion($timedactivity);
}
echo json_encode(['success' => true]);
