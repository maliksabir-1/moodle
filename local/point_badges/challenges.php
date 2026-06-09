<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/point_badges/challenges.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('challenges', 'local_point_badges') ?: 'Daily Challenges');
$PAGE->set_heading(get_string('challenges', 'local_point_badges') ?: 'Daily Challenges');

// Get user's current challenges
$challenges = \local_point_badges\manager::get_user_daily_challenges($USER->id);

echo $OUTPUT->header();

echo '<div class="challenges-page-container" style="max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">';
echo '<h2 style="text-align: center; color: #333; margin-bottom: 25px;">🎯 Your Active Daily Challenges</h2>';
echo '<p style="text-align: center; color: #666; margin-bottom: 30px;">Complete these tasks before midnight to earn standard XP and bonus rewards!</p>';

if (!empty($challenges)) {
    echo '<div style="display: flex; flex-direction: column; gap: 15px;">';
    foreach ($challenges as $challenge) {
        $completed_class = $challenge->completed ? 'background: #e8f5e9; border: 2px solid #4caf50;' : 'background: #f8f9fa; border: 1px solid #e0e0e0;';
        $progress_percent = min(100, round(($challenge->progress / $challenge->required_count) * 100));
        
        echo '<div style="padding: 20px; border-radius: 12px; ' . $completed_class . '">';
        echo '  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '      <h3 style="margin: 0; font-size: 1.2rem; color: #333;">' . s($challenge->name) . '</h3>';
        echo '      <span style="background: #ff9800; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold;">+' . $challenge->xp_reward . ' XP</span>';
        echo '  </div>';
        echo '  <p style="color: #666; margin: 0 0 15px 0;">' . s($challenge->description) . '</p>';
        echo '  <div style="background: #e0e0e0; height: 10px; border-radius: 5px; overflow: hidden; position: relative;">';
        echo '      <div style="background: #4caf50; height: 100%; width: ' . $progress_percent . '%; transition: width 0.5s ease;"></div>';
        echo '  </div>';
        echo '  <div style="text-align: right; font-size: 0.85rem; color: #666; margin-top: 8px;">';
        echo '      Progress: <strong>' . $challenge->progress . ' / ' . $challenge->required_count . '</strong>';
        echo '  </div>';
        if ($challenge->completed) {
            echo '  <div style="text-align: center; margin-top: 10px; color: #4caf50; font-weight: bold;">✅ Challenge Completed!</div>';
        }
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 12px; color: #666;">';
    echo '  <div style="font-size: 3rem; margin-bottom: 15px;">🌟</div>';
    echo '  <h3 style="margin: 0 0 10px 0;">You\'re all caught up!</h3>';
    echo '  <p style="margin: 0;">You have no active challenges right now. Check back tomorrow for new tasks.</p>';
    echo '</div>';
}

echo '<div style="text-align: center; margin-top: 40px;">';
echo '  <a href="' . new \moodle_url('/my') . '" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 25px; font-weight: bold;">← Back to Dashboard</a>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer();
