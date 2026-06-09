<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

global $DB;

echo "--- CHALLENGES ---\n";
$challenges = $DB->get_records('local_pb_challenge');
foreach ($challenges as $c) {
    echo "ID: {$c->id}, Name: {$c->name}, Event: {$c->event_name}, Active: {$c->active}\n";
}

echo "\n--- USER CHALLENGES ---\n";
$user_challenges = $DB->get_records('local_pb_user_challenge');
foreach ($user_challenges as $uc) {
    echo "ID: {$uc->id}, User: {$uc->userid}, ChallengeID: {$uc->challengeid}, Progress: {$uc->progress}, Completed: {$uc->completed}, Date: {$uc->date_assigned}\n";
}

$today = strtotime(date('Y-m-d', time()));
echo "\nToday Start Timestamp: {$today}\n";
