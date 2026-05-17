<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/mytheme/lib.php');

$categories = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC', 'id, name');
echo "Categories found: " . count($categories) . "\n";
foreach ($categories as $cat) {
    echo " - ID: " . $cat->id . " Name: " . $cat->name . "\n";
}
