<?php
defined('MOODLE_INTERNAL') || die();

function timedactivity_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO: return true;
        case FEATURE_COMPLETION_HAS_RULES: return true;
        case FEATURE_BACKUP_MOODLE2: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_COLLABORATION;
        case FEATURE_GRADE_HAS_GRADE: return true;
        case FEATURE_GRADE_OUTCOMES: return true;
        default: return null;
    }
}

function timedactivity_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $data->requiredtime = (int)($data->timevalue ?? 0) * (int)($data->timeunit ?? 1);
    
    $data->id = $DB->insert_record('timedactivity', $data);

    if (isset($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->videofile, $context->id, 'mod_timedactivity', 'video', $data->id);
    }

    if (!empty($data->quizdata)) {
        timedactivity_save_quiz($data->id, $data->quizdata);
    }
    
    timedactivity_grade_item_update($data);
    return $data->id;
}

function timedactivity_update_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    $data->requiredtime = (int)($data->timevalue ?? 0) * (int)($data->timeunit ?? 1);

    $DB->update_record('timedactivity', $data);

    if (isset($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->videofile, $context->id, 'mod_timedactivity', 'video', $data->id);
    }

    if (!empty($data->quizdata)) {
        timedactivity_save_quiz($data->id, $data->quizdata);
    }

    timedactivity_grade_item_update($data);
    return true;
}

function timedactivity_delete_instance($id) {
    global $DB;
    $DB->delete_records('timedactivity_quiz_attempts', array('quizid' => $id));
    $DB->delete_records('timedactivity_quiz', array('timedactivityid' => $id));
    $DB->delete_records('timedactivity_tracking', array('timedactivityid' => $id));
    $DB->delete_records('timedactivity', array('id' => $id));
    timedactivity_grade_item_delete($id);
    return true;
}

function timedactivity_save_quiz($id, $json) {
    global $DB;
    $DB->delete_records('timedactivity_quiz', array('timedactivityid' => $id));
    $questions = json_decode($json, true);
    if (!is_array($questions)) return;
    foreach ($questions as $q) {
        $record = new stdClass();
        $record->timedactivityid = $id;
        $record->timeposition = (int)$q['time'];
        $record->questiontext = $q['text'];
        $record->options = json_encode($q['options']);
        $record->correctanswer = (int)$q['correct'];
        $record->explanation = $q['explanation'] ?? '';
        $DB->insert_record('timedactivity_quiz', $record);
    }
}

function timedactivity_grade_item_update($timedactivity, $grades = null) {
    global $CFG;
    if (!is_object($timedactivity)) {
        $timedactivity = (object)$timedactivity;
    }
    
    $instanceid = $timedactivity->id ?? ($timedactivity->instance ?? null);
    if (!$instanceid) {
        return false;
    }

    require_once($CFG->libdir . '/gradelib.php');
    $item = array(
        'itemname' => $timedactivity->name ?? '',
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => 100,
        'grademin' => 0
    );
    
    return grade_update('mod/timedactivity', $timedactivity->course, 'mod', 'timedactivity', $instanceid, 0, $grades, $item);
}

function timedactivity_grade_item_delete($id) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    $t = $DB->get_record('timedactivity', array('id' => $id));
    if ($t) return grade_update('mod/timedactivity', $t->course, 'mod', 'timedactivity', $id, 0, null, array('deleted' => 1));
}

function mod_timedactivity_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel != CONTEXT_MODULE) return false;
    require_login($course, true, $cm);
    if ($filearea !== 'video') return false;
    $itemid = array_shift($args);
    $filename = array_shift($args);
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_timedactivity', 'video', $itemid, '/', $filename);
    if (!$file) return false;
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}