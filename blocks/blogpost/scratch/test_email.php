<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/moodlelib.php');

$user = $DB->get_record('user', ['id' => 2]); // Usually admin
if (!$user) {
    die("User id 2 not found\n");
}

$subject = "Test Blog Email";
$message = "This is a test to verify Gmail SMTP configuration.";

echo "Attempting to send email to {$user->email} via {$CFG->smtphosts}...\n";

$result = email_to_user($user, core_user::get_support_user(), $subject, $message);

if ($result) {
    echo "SUCCESS: Email sent!\n";
} else {
    echo "ERROR: Email failed to send. Check your SMTP settings in Moodle.\n";
    echo "Current SMTP Hosts: {$CFG->smtphosts}\n";
    echo "Current SMTP User: " . (isset($CFG->smtpuser) ? $CFG->smtpuser : 'NOT SET') . "\n";
}
