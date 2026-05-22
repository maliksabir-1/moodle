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

// Check sesskey but with better error message
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
    $userid = required_param('userid', PARAM_INT);
    $username = required_param('username', PARAM_TEXT);
    $blog_heading = required_param('blog_heading', PARAM_TEXT);
    $blog_text = required_param('blog_text', PARAM_RAW);
    $tags = optional_param('tags', '', PARAM_TEXT);

    if (empty($blog_heading) || empty($blog_text)) {
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
    $record->userid = $userid;
    $record->blog_heading = $blog_heading;
    $record->blog_text = $blog_text;
    $record->tags = $tags;
    $record->timecreated = time();
    $record->email_sent = 0;

    $insertid = $DB->insert_record('block_blogpost', $record);
    
    // Generate the HTML for the new post to return it for dynamic insertion
    $record->id = $insertid;
    $record->firstname = $USER->firstname;
    $record->lastname = $USER->lastname;
    $record->username = $USER->username;
    
    $author = fullname($record);
    $time = userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    
    // Format text (Basic reproduction of helper logic to avoid cross-file dependencies)
    $formatted_text = s($record->blog_text);
    $formatted_text = preg_replace_callback('/@([a-zA-Z0-9_.-]+)/', function($matches) use ($DB) {
        $username = strtolower($matches[1]);
        $user = $DB->get_record_select('user', 'LOWER(username) = :username AND deleted = 0 AND suspended = 0', ['username' => $username], 'id, firstname, lastname');
        if ($user) {
            return '<a href="#" class="mention-link badge badge-info p-1">@' . s($username) . '</a>';
        }
        return '@' . $matches[1];
    }, $formatted_text);
    $formatted_text = nl2br($formatted_text);

    $cardtags = '';
    $visible_tags_html = '';
    if (!empty($record->tags)) {
        $tags_arr = explode(',', $record->tags);
        foreach ($tags_arr as $tag) {
            $t = trim($tag);
            if (!empty($t)) {
                $visible_tags_html .= '<span class="badge badge-secondary cursor-pointer" style="cursor:pointer;" data-tag-click="' . s(strtolower($t)) . '">' . s($t) . '</span> ';
            }
        }
        $cardtags = implode(',', array_map('trim', array_map('strtolower', $tags_arr)));
    }

    $new_post_html = '
    <div class="blog-card card mb-2" data-tags="' . s($cardtags) . '">
      <div class="card-body p-3">
        <h6 class="card-title mb-1">' . s($record->blog_heading) . '</h6>
        <p class="card-subtitle mb-2 text-muted small">
          <i class="fa fa-user"></i> ' . s($author) . ' | <i class="fa fa-clock-o"></i> ' . $time . '
        </p>
        <p class="card-text">' . $formatted_text . '</p>' . 
        (!empty($visible_tags_html) ? '<p class="card-subtitle mb-2"><i class="fa fa-tags"></i> ' . $visible_tags_html . '</p>' : '') . '
        <div class="replies-section mt-3 pt-2 border-top">
            <div class="mt-2 text-left">
                <span class="reply-footer-link show-main-reply" data-postid="' . $insertid . '">Comment</span>
            </div>
            <div class="reply-form" id="main-reply-form-' . $insertid . '" style="display:none;">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control reply-input" data-postid="' . $insertid . '" data-parentid="0" placeholder="Write a comment...">
                    <div class="input-group-append">
                        <button class="btn btn-primary reply-submit" data-postid="' . $insertid . '" data-parentid="0">Send</button>
                        <button class="btn btn-link text-muted cancel-main-reply" data-postid="' . $insertid . '" style="font-size:0.75rem;">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>';

    $response->status = 'success';
    $response->message = get_string('success', 'block_blogpost');
    $response->new_post_html = $new_post_html;
    $response->is_admin = is_siteadmin($USER->id);
    
    // ---------------------------------------------------------
    // CRITICAL PART: Send success response and CLOSE connection
    // so the user doesn't wait while emails are sending.
    // ---------------------------------------------------------
    
    // 1. Clear all previous buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // 2. Prepare JSON
    $output = json_encode($response);
    
    // 3. Send headers
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($output));
    header('Connection: close');
    
    // 4. Echo and flush
    echo $output;
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // Close request but keep script alive
    } else {
        ob_start();
        echo $output;
        ob_end_flush();
        flush();
    }
    
    // ---------------------------------------------------------
    // NOW START SENDING EMAILS (USER IS ALREADY NOT WAITING)
    // ---------------------------------------------------------
    if (class_exists('\block_blogpost\task\send_blog_emails') && !empty($insertid)) {
        try {
            // Increase time limit as sending many emails can be slow
            @set_time_limit(300);
            
            $task = new \block_blogpost\task\send_blog_emails();
            $task->set_custom_data((object)['postid' => $insertid]);
            
            // Execute the task logic now
            $task->execute();
        } catch (\Throwable $e) {
            error_log('Blog background notification error: ' . $e->getMessage());
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