<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Security check
require_login();
require_sesskey();

$userid = required_param('userid', PARAM_INT);
$username = required_param('username', PARAM_TEXT); // Though we could get this from $USER
$blog_heading = required_param('blog_heading', PARAM_TEXT);
$blog_text = required_param('blog_text', PARAM_RAW);

$response = new stdClass();

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
$record->timecreated = time();

try {
    $DB->insert_record('block_blogpost', $record);
    $response->status = 'success';
    $response->message = get_string('success', 'block_blogpost');
    $response->author_name = $username;
    $response->time_formatted = userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    $response->heading = $blog_heading;
    $response->text = nl2br(s($blog_text));
    $response->is_admin = is_siteadmin($userid);
} catch (Exception $e) {
    $response->status = 'error';
    $response->message = get_string('error', 'block_blogpost') . ': ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
die();
