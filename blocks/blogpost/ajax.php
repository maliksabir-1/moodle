<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Security check
require_login();

// Set system context to prevent Moodle's core format_string from throwing PAGE->context warnings during email dispatch
global $PAGE;
$PAGE->set_context(\context_system::instance());

// Check sesskey but with better error message
if (!confirm_sesskey()) {
    $response = new stdClass();
    $response->status = 'error';
    $response->message = 'Invalid session key. Please refresh the page and try again.';
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

$response = new stdClass();

try {
    $userid = required_param('userid', PARAM_INT);
    $username = required_param('username', PARAM_TEXT);
    $blog_heading = required_param('blog_heading', PARAM_TEXT);
    $blog_text = required_param('blog_text', PARAM_RAW);
    $tags = optional_param('tags', '', PARAM_TEXT);

    if (empty($blog_heading) || empty($blog_text)) {
        $response->status = 'error';
        $response->message = get_string('emptyfields', 'block_blogpost');
        echo json_encode($response);
        die();
    }

    $record = new stdClass();
    $record->userid = $userid;
    $record->blog_heading = $blog_heading;
    $record->blog_text = $blog_text;
    $record->tags = $tags;
    $record->timecreated = time();
    $record->email_sent = 0;

    $insertid = $DB->insert_record('block_blogpost', $record);
    
    // Trigger notification email dispatch instantly and queue ad-hoc task
    if (class_exists('\block_blogpost\task\send_blog_emails')) {
        try {
            $task = new \block_blogpost\task\send_blog_emails();
            $task->set_custom_data((object)['postid' => $insertid]);
            
            // Temporarily suppress Moodle and PHP debugging to guarantee a clean JSON response
            global $CFG;
            $olddebug = isset($CFG->debug) ? $CFG->debug : 0;
            $olddebugdisplay = isset($CFG->debugdisplay) ? $CFG->debugdisplay : 0;
            $CFG->debug = 0;
            $CFG->debugdisplay = 0;
            $olderrorreporting = error_reporting(0);
            $olddisplayerrors = ini_set('display_errors', '0');

            // Execute instantly for real-time delivery, capturing any direct output buffer
            ob_start();
            $task->execute();
            ob_end_clean();
            
            // Restore original debugging settings
            $CFG->debug = $olddebug;
            $CFG->debugdisplay = $olddebugdisplay;
            error_reporting($olderrorreporting);
            ini_set('display_errors', $olddisplayerrors);

            // Also queue as ad-hoc task for standard Moodle processing backup
            \core\task\manager::queue_adhoc_task($task);
        } catch (Exception $e) {
            // Log but don't fail the AJAX request
            error_log('Blog notification task error: ' . $e->getMessage());
        }
    }
    
    $response->status = 'success';
    $response->message = get_string('success', 'block_blogpost');
    $response->author_name = $username;
    $response->time_formatted = userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    $response->heading = $blog_heading;
    $response->text = nl2br(s($blog_text));
    $response->is_admin = is_siteadmin($userid);
    $response->tags = $tags;
    
} catch (Exception $e) {
    $response->status = 'error';
    $response->message = get_string('error', 'block_blogpost') . ': ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
die();