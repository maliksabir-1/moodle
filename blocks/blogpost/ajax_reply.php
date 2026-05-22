<?php
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(0);
ob_start();
define('AJAX_SCRIPT', true);
ignore_user_abort(true);
require_once(__DIR__ . '/../../config.php');

// Security check
require_login();

// Set system context to prevent Moodle's core format_string from throwing PAGE->context warnings during email dispatch
global $PAGE;
$PAGE->set_context(\context_system::instance());

// Check sesskey
if (!confirm_sesskey()) {
    $response = new stdClass();
    $response->status = 'error';
    $response->message = 'Invalid session key. Please refresh the page and try again.';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

$response = new stdClass();

try {
    $postid = required_param('postid', PARAM_INT);
    $parentid = optional_param('parentid', 0, PARAM_INT);
    $reply_text = required_param('reply_text', PARAM_RAW);
    $userid = $USER->id;

    if (empty($reply_text)) {
        $response->status = 'error';
        $response->message = get_string('emptyfields', 'block_blogpost');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        die();
    }

    $record = new stdClass();
    $record->postid = $postid;
    $record->parentid = $parentid;
    $record->userid = $userid;
    $record->reply_text = $reply_text;
    $record->timecreated = time();
    $record->email_sent = 0;

    $insertid = $DB->insert_record('block_blogpost_replies', $record);
    
    // Generate HTML for the new reply
    $replytime = userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    $replyauthor_full = fullname($USER);
    $replyauthor_short = $USER->firstname;
    
    $parentinfo = '';
    if (!empty($parentid)) {
        $parent_reply = $DB->get_record_sql("
            SELECT r.*, u.firstname, u.lastname 
            FROM {block_blogpost_replies} r 
            JOIN {user} u ON r.userid = u.id 
            WHERE r.id = ?", [$parentid]);
        if ($parent_reply) {
            $pfullname = fullname($parent_reply);
            $parentinfo = ' <i class="fa fa-caret-right text-muted mx-1"></i> <span class="text-primary" style="font-weight:600; cursor:default;">' . s($pfullname) . '</span>';
        }
    }
    
    // Format text
    $formatted_text = s($reply_text);
    $formatted_text = preg_replace_callback('/@([a-zA-Z0-9_.-]+)/', function($matches) use ($DB) {
        $username = strtolower($matches[1]);
        $user = $DB->get_record_select('user', 'LOWER(username) = :username AND deleted = 0 AND suspended = 0', ['username' => $username], 'id, firstname, lastname');
        if ($user) {
            return '<a href="#" class="mention-link badge badge-info p-1">@' . s($username) . '</a>';
        }
        return '@' . $matches[1];
    }, $formatted_text);
    $formatted_text = nl2br($formatted_text);

    $new_reply_html = '
    <div class="reply-item ' . ($parentid > 0 ? 'ml-4' : '') . ' mt-1">
        <div class="reply-content-wrapper">
            <div class="reply-author-name">' . s($replyauthor_full) . $parentinfo . '</div>
            <div class="reply-text">' . $formatted_text . '</div>
        </div>
        <div class="reply-item-footer">
            <span class="reply-footer-link show-reply-input" data-replyid="' . $insertid . '">Reply</span> 
            <span class="reply-time">' . $replytime . '</span>
        </div>
        <div class="nested-reply-form" id="reply-form-' . $insertid . '" style="display:none;">
            <div class="input-group input-group-sm mt-1">
                <input type="text" class="form-control reply-input" id="reply-input-' . $insertid . '" data-postid="' . $postid . '" data-parentid="' . $insertid . '" placeholder="Reply to ' . s($replyauthor_short) . '...">
                <div class="input-group-append">
                    <button class="btn btn-primary reply-submit" data-postid="' . $postid . '" data-parentid="' . $insertid . '">Send</button>
                    <button class="btn btn-link text-muted cancel-reply" data-replyid="' . $insertid . '" style="font-size:0.7rem;">Cancel</button>
                </div>
            </div>
        </div>
    </div>';

    $response->status = 'success';
    $response->message = get_string('success', 'block_blogpost');
    $response->new_reply_html = $new_reply_html;
    $response->parentid = $parentid;
    $response->postid = $postid;
    
    // ---------------------------------------------------------
    // CRITICAL PART: Send success response and CLOSE connection
    // so the user doesn't wait while emails are sending.
    // ---------------------------------------------------------
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $output = json_encode($response);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($output));
    header('Connection: close');
    echo $output;
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); 
    } else {
        ob_start();
        echo $output;
        ob_end_flush();
        flush();
    }
    
    // ---------------------------------------------------------
    // NOW START SENDING EMAILS (USER IS ALREADY NOT WAITING)
    // ---------------------------------------------------------
    if (class_exists('\block_blogpost\task\send_reply_emails') && !empty($insertid)) {
        try {
            @set_time_limit(300);
            $task = new \block_blogpost\task\send_reply_emails();
            $task->set_custom_data((object)['replyid' => $insertid]);
            $task->execute();
        } catch (\Throwable $e) {
            error_log('Blog reply background notification error: ' . $e->getMessage());
        }
    }
    die();

} catch (\Throwable $e) {
    if (ob_get_length() !== false) ob_clean();
    $response->status = 'error';
    $response->message = get_string('error', 'block_blogpost') . ': ' . $e->getMessage();
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}
