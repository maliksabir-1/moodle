<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/point_badges/mystats.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_title('My Stats');
$PAGE->set_heading('My Point Badges Stats');

echo $OUTPUT->header();

$level_info = \local_point_badges\manager::get_user_level_info($USER->id);
$streak = \local_point_badges\manager::get_user_streak($USER->id);

echo '<div class="card">';
echo '<div class="card-body">';
echo '<h3>Your Progress</h3>';
echo '<p>Total XP: <strong>' . number_format($level_info['total_xp']) . '</strong></p>';
echo '<p>Current Level: <strong>' . $level_info['level_name'] . '</strong></p>';
echo '<p>Progress to next level: ' . $level_info['progress_percent'] . '%</p>';
echo '<p>Current Streak: <strong>' . ($streak ? $streak->current_streak : 0) . '</strong> days</p>';
echo '<p>Best Streak: <strong>' . ($streak ? $streak->max_streak : 0) . '</strong> days</p>';
echo '</div></div>';

echo $OUTPUT->footer();