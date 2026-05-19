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
$is_teacher = has_capability('mod/timedactivity:viewreports', $context);
$attempts_exceeded = false;

if ($track) {
    $is_complete = ($track->totaltimespent >= $timedactivity->requiredtime);
    if (!$is_complete && $timedactivity->allowedattempts > 0 && $track->attempts >= $timedactivity->allowedattempts && !$is_teacher) {
        $attempts_exceeded = true;
    }
}

require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');
timedactivity_check_visits_table();

$visitid = 0;
if (!$attempts_exceeded) {
    if (!$track) {
        $track = new stdClass();
        $track->timedactivityid = $timedactivity->id;
        $track->userid = $userid;
        $track->totaltimespent = 0;
        $track->videoposition = 0;
        $track->attempts = $is_teacher ? 0 : 1;
        $track->timemodified = time();
        $track->id = $DB->insert_record('timedactivity_tracking', $track);
    } else {
        // Increment attempts counter only on a new visit (not page reloads/refreshes) AND only for students
        if ($track->totaltimespent < $timedactivity->requiredtime && !$is_teacher) {
            $is_refresh = false;
            if (isset($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
                $current_url = new moodle_url('/mod/timedactivity/view.php', ['id' => $cm->id]);
                if (strpos($referer, $current_url->out(false)) !== false) {
                    $is_refresh = true;
                }
            }
            if (!$is_refresh) {
                $track->attempts = ($track->attempts ?? 0) + 1;
            }
        }
        $track->timemodified = time();
        $DB->update_record('timedactivity_tracking', $track);
    }

    // Insert a new visit record for students
    if (!$is_teacher) {
        $visit = new stdClass();
        $visit->timedactivityid = $timedactivity->id;
        $visit->userid = $userid;
        $visit->sessionstarted = time();
        $visit->watchtime = 0;
        $visit->lastaccess = time();
        $visitid = $DB->insert_record('timedactivity_visits', $visit);
    }
}

$videofileurl = '';
if ($timedactivity->videosource == 'local') {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_timedactivity', 'video', $timedactivity->id, 'id DESC', false);
    if ($files) {
        $file = reset($files);
        $videofileurl = moodle_url::make_pluginfile_url($context->id, 'mod_timedactivity', 'video', $timedactivity->id, '/', $file->get_filename())->out();
    }
}

// Get interactive quizzes
$quizquestions = $DB->get_records('timedactivity_quiz', array('timedactivityid' => $timedactivity->id), 'timeposition ASC');
$quizdata = array();
foreach ($quizquestions as $q) {
    // Check if user already answered this question correctly, if so, skip it
    $correct_attempts = $DB->get_records('timedactivity_quiz_attempts', 
        array('quizid' => $q->id, 'userid' => $userid, 'iscorrect' => 1), 
        'id DESC', '*', 0, 1);
    $answered_correctly = !empty($correct_attempts);
    
    if ($answered_correctly && empty($timedactivity->retakesallowed)) {
        continue;
    }

    // Check if user has exceeded the max allowed attempts for this specific quiz popup
    $attempts_count = $DB->count_records('timedactivity_quiz_attempts', array('quizid' => $q->id, 'userid' => $userid));
    if ($timedactivity->maxquizattempts > 0 && $attempts_count >= $timedactivity->maxquizattempts) {
        continue;
    }

    $quizdata[] = array(
        'id' => $q->id,
        'timeposition' => (int)$q->timeposition,
        'questiontext' => $q->questiontext,
        'options' => $q->options,
        'correctanswer' => (int)$q->correctanswer,
        'explanation' => $q->explanation ?? ''
    );
}

// Calculate answered quizzes for JS parameters
$js_quiz_answered = 0;
$answered_quiz_ids = array();
foreach ($quizquestions as $qq) {
    $correct_attempts = $DB->get_records('timedactivity_quiz_attempts', 
        array('quizid' => $qq->id, 'userid' => $userid, 'iscorrect' => 1), 
        'id DESC', '*', 0, 1);
    $correct = !empty($correct_attempts);
    
    if ($correct) {
        $js_quiz_answered++;
        $answered_quiz_ids[] = (int)$qq->id;
    } else {
        // Check if user has made any attempt (even incorrect) so they don't get duplicate popups
        $any_attempt = $DB->get_records('timedactivity_quiz_attempts', 
            array('quizid' => $qq->id, 'userid' => $userid), 
            'id DESC', '*', 0, 1);
        if (!empty($any_attempt)) {
            $answered_quiz_ids[] = (int)$qq->id;
        }
    }
}
$js_total_quizzes = count($quizquestions);

$js_params = array(
    'cmid' => $cm->id,
    'userid' => $userid,
    'visitId' => $visitid,
    'required' => (int)$timedactivity->requiredtime,
    'current' => (int)$track->totaltimespent,
    'savedPosition' => (float)$track->videoposition,
    'ajaxUrl' => (new moodle_url('/mod/timedactivity/track.php'))->out(false),
    'savePositionUrl' => (new moodle_url('/mod/timedactivity/save_position.php'))->out(false),
    'durationUrl' => (new moodle_url('/mod/timedactivity/track_duration.php'))->out(false),
    'videoSource' => $timedactivity->videosource,
    'videoFileUrl' => $videofileurl,
    'youtubeUrl' => $timedactivity->youtubeurl,
    'matchDuration' => (bool)$timedactivity->matchduration,
    'quizQuestions' => $quizdata,
    'sesskey' => sesskey(),
    'timelimitperquestion' => (int)$timedactivity->timelimitperquestion,
    'quizAnswered' => $js_quiz_answered,
    'totalQuizzes' => $js_total_quizzes,
    'answeredQuizIds' => $answered_quiz_ids,
    'passingGrade' => (int)($timedactivity->passinggrade ?? 70),
    'retakesallowed' => !empty($timedactivity->retakesallowed),
     'preventSeeking' => true  // Add this line to enable seeking prevention
);

$PAGE->set_url('/mod/timedactivity/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($timedactivity->name));
$PAGE->set_heading($course->fullname);

// Load AMD module for timedactivity tracking if not attempts exceeded
if (!$attempts_exceeded) {
    $PAGE->requires->js_call_amd('mod_timedactivity/video_timer', 'init', array($js_params));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($timedactivity->name);

if ($timedactivity->intro) {
    echo $OUTPUT->box(format_module_intro('timedactivity', $timedactivity, $cm->id), 'generalbox', 'intro');
}

echo html_writer::start_div('timedactivity-timer-wrapper alert alert-info text-center');
echo html_writer::tag('h4', get_string('activityprogress', 'mod_timedactivity'));
echo html_writer::tag('div', get_string('timespent', 'mod_timedactivity') . ': <span id="timespent">' . format_time($track->totaltimespent) . '</span>');
echo html_writer::tag('div', get_string('timeremaining', 'mod_timedactivity') . ': <span id="timeremaining">' . format_time(max(0, $timedactivity->requiredtime - $track->totaltimespent)) . '</span>');

if ($is_teacher) {
    echo html_writer::tag('div', 'Attempts to Complete: <span class="badge badge-secondary" style="font-size: 0.9em; background-color: #6c757d; color: white; padding: 4px 8px; border-radius: 4px;">Teacher Preview</span>');
} else {
    $display_attempts = $track->attempts ?? 1;
    if ($timedactivity->allowedattempts > 0 && $display_attempts > $timedactivity->allowedattempts) {
        $display_attempts = $timedactivity->allowedattempts;
    }
    $attempts_limit_str = $timedactivity->allowedattempts > 0 ? ' / ' . $timedactivity->allowedattempts : ' (Unlimited)';
    echo html_writer::tag('div', 'Attempts to Complete: <span id="attempts">' . $display_attempts . '</span>' . $attempts_limit_str);
}

echo html_writer::tag('div', '', array('id' => 'completion-status', 'class' => $track->totaltimespent >= $timedactivity->requiredtime ? 'complete' : 'incomplete'));
echo html_writer::end_div();

if ($attempts_exceeded) {
    echo $OUTPUT->notification(get_string('error_maxattemptsreached', 'mod_timedactivity', $timedactivity->allowedattempts), 'error');
} else {
    echo html_writer::start_div('video-container');
    echo html_writer::tag('div', '', array('id' => 'video-player'));
    echo html_writer::end_div();
}

// Display completion requirements - only show if admin has configured at least one requirement
$has_requirements = ($timedactivity->requiredtime > 0)
    || ($timedactivity->grademethod > 0 && $timedactivity->passinggrade > 0)
    || (!empty($quizquestions));

if ($has_requirements) {
    // Evaluate current student status for each requirement
    $time_met = $track->totaltimespent >= $timedactivity->requiredtime;

    $quiz_answered = 0;
    foreach ($quizquestions as $qq) {
        $correct_attempts = $DB->get_records('timedactivity_quiz_attempts', 
            array('quizid' => $qq->id, 'userid' => $userid, 'iscorrect' => 1), 
            'id DESC', '*', 0, 1);
        if (!empty($correct_attempts)) $quiz_answered++;
    }
    $total_q     = count($quizquestions);
    $quizzes_met = $total_q > 0 && ($quiz_answered >= $total_q);
    $is_locked   = $attempts_exceeded;

    // Card border style: red if locked, neutral otherwise
    $card_style = 'margin-top:20px; border-radius:10px; padding:16px 20px;'
        . ($is_locked ? ' border:2px solid #dc3545; background:#fff5f5;' : ' border:1px solid #dee2e6; background:#f8f9fa;');

    echo html_writer::start_div('completion-requirements card', ['style' => $card_style]);
    echo html_writer::start_div('card-body p-0');

    // Header row
    $header_color = $is_locked ? '#dc3545' : '#495057';
    echo html_writer::start_tag('div', ['style' => 'display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;']);
    echo html_writer::tag('h5', 'Completion Requirements', ['style' => "margin:0; font-weight:700; color:{$header_color};"]);
    if ($is_locked) {
        echo html_writer::tag('span', '🔒 Not Completed — Activity Locked', [
            'style' => 'font-size:0.85em; font-weight:700; background:#dc3545; color:#fff; padding:4px 10px; border-radius:20px;'
        ]);
    }
    echo html_writer::end_tag('div');

    // ── Required time ──
    if ($timedactivity->requiredtime > 0) {
        $icon  = $time_met ? '✅' : '❌';
        $color = $time_met ? '#155724' : '#721c24';
        $bg    = $time_met ? '#d4edda' : '#f8d7da';
        echo html_writer::tag('div',
            $icon . '&nbsp; Watch at least <strong>' . format_time($timedactivity->requiredtime) . '</strong>',
            [
                'id' => 'req-time',
                'style' => "background:{$bg}; color:{$color}; border-radius:6px; padding:8px 12px; margin-bottom:8px; font-size:0.95em;"
            ]
        );
    }

    // ── Quiz questions ──
    if ($total_q > 0) {
        $icon  = $quizzes_met ? '✅' : '❌';
        $color = $quizzes_met ? '#155724' : '#721c24';
        $bg    = $quizzes_met ? '#d4edda' : '#f8d7da';
        $plural = $total_q > 1 ? 's' : '';
        echo html_writer::tag('div',
            $icon . '&nbsp; Answer all <strong>' . $total_q . '</strong> quiz question' . $plural . ' (' . $quiz_answered . '/' . $total_q . ' answered correctly)',
            [
                'id' => 'req-quizzes',
                'style' => "background:{$bg}; color:{$color}; border-radius:6px; padding:8px 12px; margin-bottom:8px; font-size:0.95em;"
            ]
        );
    }

    // ── Passing grade ──
    if ($timedactivity->grademethod > 0 && $timedactivity->passinggrade > 0) {
        require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');
        $user_grade = timedactivity_get_user_grade($timedactivity, $userid);
        $grade_met  = ($user_grade !== null && $user_grade >= $timedactivity->passinggrade);
        $icon  = $grade_met ? '✅' : '❌';
        $color = $grade_met ? '#155724' : '#721c24';
        $bg    = $grade_met ? '#d4edda' : '#f8d7da';
        $grade_label = $user_grade !== null ? " (Your grade: {$user_grade}%)" : ' (Not yet graded)';
        echo html_writer::tag('div',
            $icon . '&nbsp; Achieve a passing grade of <strong>' . $timedactivity->passinggrade . '%</strong>' . $grade_label,
            [
                'id' => 'req-grade',
                'style' => "background:{$bg}; color:{$color}; border-radius:6px; padding:8px 12px; margin-bottom:8px; font-size:0.95em;"
            ]
        );
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
}

// After the video-container div - Quiz Results & Grade Section
if ($timedactivity->grademethod > 0) {
    echo html_writer::start_div('grade-report card mt-4', ['style' => 'border-radius:10px; border:1px solid #dee2e6; background:#fff;']);
    echo html_writer::start_div('card-header', ['style' => 'background:#f8f9fa; border-bottom:1px solid #dee2e6; padding:12px 20px; display: flex; justify-content: space-between; align-items: center;']);
    echo html_writer::tag('h5', '📊 Your Quiz Results & Grade', ['style' => 'margin:0; font-weight:700; color:#495057;']);
    
    // Add toggle button
    echo html_writer::tag('button', 'Show Details', [
        'id' => 'toggle-quiz-results-btn',
        'class' => 'btn btn-sm btn-primary',
        'style' => 'font-weight:500;',
        'onclick' => 'toggleQuizResults()'
    ]);
    
    echo html_writer::end_div();
    
    echo html_writer::start_div('card-body', ['id' => 'quiz-results-body', 'style' => 'padding:20px; display: none;']);
    
    // Get user's quiz results
    require_once($CFG->dirroot . '/mod/timedactivity/locallib.php');
    $quiz_results = timedactivity_get_user_quiz_results($timedactivity, $userid);
    $user_grade = timedactivity_get_user_grade($timedactivity, $userid);
    $passing_grade = (int)$timedactivity->passinggrade;
    
    if (!empty($quiz_results)) {
        $grade_class = ($user_grade !== null && $user_grade >= $passing_grade) ? 'text-success' : 'text-danger';
        
        echo html_writer::start_div('grade-summary mb-4', ['style' => 'background:#f0f7ff; border-left:4px solid #007bff; padding:15px; border-radius:8px;']);
        echo html_writer::tag('h3', 'Current Grade: <span id="current-grade">' . ($user_grade !== null ? $user_grade . '%' : 'Not graded yet') . '</span>', 
            ['class' => $grade_class, 'style' => 'margin:0 0 10px 0;', 'id' => 'grade-heading']);
        if ($timedactivity->passinggrade > 0) {
            $status = ($user_grade !== null && $user_grade >= $passing_grade) ? '✅ PASSING' : '❌ NOT PASSING';
            $status_class = ($user_grade !== null && $user_grade >= $passing_grade) ? 'text-success' : 'text-danger';
            echo html_writer::tag('p', "Required Passing Grade: {$passing_grade}% - <span id='grade-status' class='{$status_class}'>{$status}</span>", 
                ['style' => 'margin:0; font-weight:500;']);
        }
        echo html_writer::end_div();
        
        // Build grade table with ID for dynamic updates
        echo '<table id="quiz-results-table" class="generaltable table table-striped table-hover" width="100%">
                <thead>
                    <tr><th>#</th><th>Question</th><th>Your Answer</th><th>Correct?</th></tr>
                </thead>
                <tbody>';
        
        $question_num = 1;
        foreach ($quiz_results as $result) {
            $options = $result->options;
            $user_answer_text = ($result->useranswer >= 0 && isset($options[$result->useranswer])) 
                ? $options[$result->useranswer] 
                : ($result->useranswer == -1 ? 'Not answered' : 'Invalid');
            
            $correct_text = $result->iscorrect ? '✓ Correct' : '✗ Incorrect';
            $correct_class = $result->iscorrect ? 'text-success' : 'text-danger';
            
            echo '<tr>
                    <td>' . $question_num++ . '</td>
                    <td>' . htmlspecialchars($result->questiontext) . '</td>
                    <td>' . htmlspecialchars($user_answer_text) . '</td>
                    <td><span class="' . $correct_class . '" style="font-weight:bold;">' . $correct_text . '</span></td>
                  </tr>';
        }
        
        echo '</tbody>
            </table>';
        
        // Add retake all quizzes button if retakes allowed
        if (!empty($timedactivity->retakesallowed)) {
            echo html_writer::start_div('text-center mt-4');
            echo html_writer::tag('button', '🔄 Retake All Quizzes', [
                'id' => 'retake-all-quizzes-btn',
                'class' => 'btn btn-warning btn-lg',
                'style' => 'font-weight:bold;',
                'onclick' => 'retakeAllQuizzes()'
            ]);
            echo html_writer::end_div();
        }
        
    } else {
        echo html_writer::tag('p', 'No quizzes available for this activity.', ['class' => 'text-muted text-center']);
    }
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

/// Add JavaScript for toggle and retake functionality
echo html_writer::start_tag('script');
echo '
// Ensure jQuery is loaded before executing
(function() {
    // Function to check if jQuery is ready
    function executeWhenReady() {
        if (typeof jQuery !== "undefined") {
            // jQuery is loaded, initialize functionality
            initQuizFunctions();
        } else {
            // Wait a bit and try again
            setTimeout(executeWhenReady, 100);
        }
    }
    
    function initQuizFunctions() {
        var $ = jQuery;
        
        // Toggle Quiz Results Function
        window.toggleQuizResults = function() {
            var body = document.getElementById("quiz-results-body");
            var btn = document.getElementById("toggle-quiz-results-btn");
            
            if (body && btn) {
                if (body.style.display === "none" || body.style.display === "") {
                    body.style.display = "block";
                    btn.innerHTML = "Hide Details";
                    btn.className = "btn btn-sm btn-secondary";
                } else {
                    body.style.display = "none";
                    btn.innerHTML = "Show Details";
                    btn.className = "btn btn-sm btn-primary";
                }
            }
        };
        
        // Retake All Quizzes Function
        window.retakeAllQuizzes = function() {
            if (!confirm("Are you sure you want to retake all quizzes? Your previous answers will be reset.")) {
                return false;
            }
            
            // Disable the button to prevent multiple clicks
            var retakeBtn = document.getElementById("retake-all-quizzes-btn");
            var originalText = "";
            if (retakeBtn) {
                originalText = retakeBtn.innerHTML;
                retakeBtn.disabled = true;
                retakeBtn.innerHTML = "⏳ Processing...";
            }
            
            // Get Moodle session key from page
            var sesskey = "' . sesskey() . '";
            var cmid = ' . $cm->id . ';
            
            // Make AJAX request using Moodle\'s core/ajax or jQuery
            $.ajax({
                url: M.cfg.wwwroot + "/mod/timedactivity/retake_all_quizzes.php",
                type: "POST",
                data: {
                    cmid: cmid,
                    sesskey: sesskey
                },
                dataType: "json",
                success: function(response) {
                    if (response && response.success) {
                        // Show success message
                        var $notification = $(\'<div class="alert alert-success alert-dismissible fade show" style="position:fixed; top:20px; right:20px; z-index:9999; padding:15px; border-radius:5px; box-shadow:0 2px 10px rgba(0,0,0,0.2);">\n\' +
                            \'<strong>✓ Success!</strong> Quizzes reset successfully! Refreshing page...\n\' +
                            \'</div>\');
                        $("body").append($notification);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var errorMsg = response && response.error ? response.error : "Unknown error";
                        alert("Failed to reset quizzes: " + errorMsg);
                        if (retakeBtn) {
                            retakeBtn.disabled = false;
                            retakeBtn.innerHTML = originalText;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error - Status:", status, "Error:", error);
                    console.error("Response Text:", xhr.responseText);
                    alert("An error occurred while resetting quizzes. Please refresh the page and try again.");
                    if (retakeBtn) {
                        retakeBtn.disabled = false;
                        retakeBtn.innerHTML = originalText;
                    }
                }
            });
            
            return false;
        };
        
        // Store grade and passing grade for JS updates
        window.userGrade = ' . ($user_grade !== null ? $user_grade : 'null') . ';
        window.passingGrade = ' . (int)$timedactivity->passinggrade . ';
        
        // Attach event handler to retake button
        var retakeBtn = document.getElementById("retake-all-quizzes-btn");
        if (retakeBtn) {
            // Remove any existing event listeners
            var newBtn = retakeBtn.cloneNode(true);
            retakeBtn.parentNode.replaceChild(newBtn, retakeBtn);
            newBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.retakeAllQuizzes();
                return false;
            };
        }
        
        console.log("Quiz functionality initialized successfully");
    }
    
    // Start the initialization process
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", executeWhenReady);
    } else {
        executeWhenReady();
    }
})();
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
?>