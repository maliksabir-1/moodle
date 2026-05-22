<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Security check
require_login();

$query = optional_param('q', '', PARAM_TEXT);
$response = [];

global $DB;
if (strlen($query) >= 0) {
    // Search by username, firstname, or lastname
    if (strlen($query) > 0) {
        $q = '%' . strtolower($query) . '%';
        $sql = "SELECT id, username, firstname, lastname 
                FROM {user} 
                WHERE deleted = 0 AND suspended = 0 AND id > 1
                AND (LOWER(username) LIKE ? OR LOWER(firstname) LIKE ? OR LOWER(lastname) LIKE ?)
                LIMIT 15";
        $users = $DB->get_records_sql($sql, [$q, $q, $q]);
    } else {
        // Just @ typed, show recent/active users
        $sql = "SELECT id, username, firstname, lastname 
                FROM {user} 
                WHERE deleted = 0 AND suspended = 0 AND id > 1
                ORDER BY lastaccess DESC
                LIMIT 15";
        $users = $DB->get_records_sql($sql);
    }
    
    foreach ($users as $user) {
        $response[] = [
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => fullname($user)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
die();
