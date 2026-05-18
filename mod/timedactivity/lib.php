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
    $data->timelimitperquestion = 0;
    $data->maxquizattempts = 0;
    
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
    $data->timelimitperquestion = 0;
    $data->maxquizattempts = 0;

    $DB->update_record('timedactivity', $data);

    if (isset($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->videofile, $context->id, 'mod_timedactivity', 'video', $data->id);
    }

    if (!empty($data->quizdata)) {
        timedactivity_save_quiz($data->id, $data->quizdata);
    }

    timedactivity_grade_item_update($data);

    // Fetch the complete DB record to ensure all columns are available
    $timedactivity = $DB->get_record('timedactivity', array('id' => $data->id), '*', MUST_EXIST);
    timedactivity_update_all_users_grades_and_completion($timedactivity);

    return true;
}

function timedactivity_update_user_grade_and_completion($timedactivity, $userid) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/completionlib.php');
    require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');

    $cm = get_coursemodule_from_instance('timedactivity', $timedactivity->id);
    if (!$cm) {
        return;
    }
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $completion = new completion_info($course);

    // Calculate and collect grade
    $grade = timedactivity_get_user_grade($timedactivity, $userid);
    
    $grades = [];
    if ($grade !== null) {
        $grades[$userid] = [
            'userid' => $userid,
            'rawgrade' => $grade
        ];
    } else {
        $grades[$userid] = [
            'userid' => $userid,
            'rawgrade' => null
        ];
    }
    
    // Update grades in gradebook
    timedactivity_grade_item_update($timedactivity, $grades);

    // Re-evaluate Moodle completion
    if ($completion->is_enabled($cm) && isset($cm->completion) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $is_complete = true;
        
        // Fetch raw course module record to get custom completion rules
        $raw_cm = $DB->get_record('course_modules', array('id' => $cm->id));
        
        // Failsafe: check raw DB columns, fallback to $cm properties, fallback to active activity requirements
        $check_time = $raw_cm && isset($raw_cm->completionrequiretime) ? !empty($raw_cm->completionrequiretime) : (!empty($cm->completionrequiretime) || $timedactivity->requiredtime > 0);
        $check_pass = $raw_cm && isset($raw_cm->completionpass) ? !empty($raw_cm->completionpass) : (!empty($cm->completionpass) || $timedactivity->passinggrade > 0);
        $check_quizzes = $raw_cm && isset($raw_cm->completionallquizzes) ? !empty($raw_cm->completionallquizzes) : (!empty($cm->completionallquizzes) || $DB->count_records('timedactivity_quiz', ['timedactivityid' => $timedactivity->id]) > 0);

        if ($check_time && !timedactivity_is_time_complete($timedactivity, $userid)) {
            $is_complete = false;
        }
        if ($check_pass && $timedactivity->grademethod > 0) {
            $passinggrade = $timedactivity->passinggrade ?? 70;
            if ($grade === null || $grade < $passinggrade) {
                $is_complete = false;
            }
        }
        if ($check_quizzes && !timedactivity_are_all_quizzes_complete($timedactivity, $userid)) {
            $is_complete = false;
        }

        $target_state = $is_complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

        // Force dynamic database sync for course_modules_completion to bypass core Moodle downgrade restrictions
        $completionrec = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $userid));
        if ($completionrec) {
            if ($completionrec->completionstate != $target_state) {
                $completionrec->completionstate = $target_state;
                $completionrec->timemodified = time();
                $DB->update_record('course_modules_completion', $completionrec);
            }
        } else {
            $completionrec = new stdClass();
            $completionrec->coursemoduleid = $cm->id;
            $completionrec->userid = $userid;
            $completionrec->completionstate = $target_state;
            $completionrec->timemodified = time();
            $DB->insert_record('course_modules_completion', $completionrec);
        }

        $completion->update_state($cm, $target_state, $userid);
    }
}

function timedactivity_update_all_users_grades_and_completion($timedactivity) {
    global $DB;

    $tracks = $DB->get_records('timedactivity_tracking', ['timedactivityid' => $timedactivity->id]);
    if (!$tracks) {
        return;
    }

    foreach ($tracks as $track) {
        timedactivity_update_user_grade_and_completion($timedactivity, $track->userid);
    }
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
    
    // Pre-process raw JSON data to resolve Moodle form filter sanitization and entity encoding
    $json = html_entity_decode($json, ENT_QUOTES, 'UTF-8');
    $json = stripslashes($json);
    $json = trim($json);
    
    // Replace smart/curly quotes with standard straight quotes
    $json = str_replace(array('“', '”', '‟', '″', '″'), '"', $json);
    $json = str_replace(array('‘', '’', '‛', '′', '′'), "'", $json);
    
    // Automatically wrap in square brackets if missing
    if (!empty($json) && $json[0] !== '[') {
        $json = '[' . $json . ']';
    }
    
    $questions = json_decode($json, true);
    if (!empty($questions)) {
        // Clear existing questions first
        $DB->delete_records('timedactivity_quiz', array('timedactivityid' => $id));
        foreach ($questions as $q) {
            $record = new stdClass();
            $record->timedactivityid = $id;
            $record->timeposition = $q['time'];
            $record->questiontext = $q['text'];
            $record->options = json_encode($q['options']);
            $record->correctanswer = $q['correct'];
            $record->explanation = $q['explanation'] ?? '';
            $DB->insert_record('timedactivity_quiz', $record);
        }
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

/**
 * Extends the settings navigation with module-specific settings (like report links).
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $module_node
 */
function timedactivity_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $module_node) {
    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }
    
    $context = context_module::instance($cm->id);
    
    // Check if the current user has the capability to view reports
    if (has_capability('mod/timedactivity:viewreports', $context)) {
        // Add a link to the report as a tab
        $url = new moodle_url('/mod/timedactivity/report.php', ['id' => $cm->id]);
        $reportnode = navigation_node::create(
            'Activity Completion Report',
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'timedactivity_report',
            new pix_icon('i/report', '')
        );
        $module_node->add_node($reportnode);
    }
}