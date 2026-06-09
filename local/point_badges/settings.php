<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    
    $settings = new admin_settingpage('local_point_badges', get_string('pluginname', 'local_point_badges'));
    
    // ========== XP AWARDS SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/xp_settings', 
        '📊 XP Awards Settings', 'Configure how many XP each activity awards to students'));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_quiz',
        'Quiz Completion XP', 'XP awarded when a student completes a quiz', 50, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_daily_login',
        'Daily Login XP', 'XP awarded for daily login', 10, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_assignment',
        'Assignment Submission XP', 'XP awarded when a student submits an assignment', 100, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_forum',
        'Forum Post XP', 'XP awarded for creating a forum post', 15, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_lesson',
        'Lesson Completion XP', 'XP awarded for completing a lesson', 30, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_scorm',
        'SCORM Completion XP', 'XP awarded for completing a SCORM package', 40, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_attendance',
        'Attendance XP', 'XP awarded for marking attendance', 20, PARAM_INT));
    
    // ========== STREAK BONUS SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/streak_settings',
        '🔥 Streak Bonus Settings', 'Configure bonus XP for maintaining login streaks'));
    
    $settings->add(new admin_setting_configtext('local_point_badges/streak_7_bonus',
        '7-Day Streak Bonus', 'Bonus XP for 7 consecutive days of login', 50, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/streak_30_bonus',
        '30-Day Streak Bonus', 'Bonus XP for 30 consecutive days of login', 200, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/streak_100_bonus',
        '100-Day Streak Bonus', 'Bonus XP for 100 consecutive days of login', 500, PARAM_INT));
    
    // ========== LEVEL CONFIGURATION ==========
    $settings->add(new admin_setting_heading('local_point_badges/level_settings',
        '🎯 Level Configuration', 'Configure XP thresholds and names for each level'));
    
    // Level 1 - Beginner
    $settings->add(new admin_setting_configtext('local_point_badges/level1_name',
        'Level 1 Name', 'Name for the first level', 'Beginner', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_point_badges/level1_max',
        'Level 1 Max XP', 'Maximum XP for Level 1 (0 to this value)', 100, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level1_color',
        'Level 1 Badge Color', 'Hex color code for Level 1 badge', '#cd7f32', PARAM_TEXT));
    
    // Level 2 - Intermediate
    $settings->add(new admin_setting_configtext('local_point_badges/level2_name',
        'Level 2 Name', 'Name for the second level', 'Intermediate', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_point_badges/level2_min',
        'Level 2 Min XP', 'Minimum XP required for Level 2', 101, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level2_max',
        'Level 2 Max XP', 'Maximum XP for Level 2', 300, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level2_color',
        'Level 2 Badge Color', 'Hex color code for Level 2 badge', '#c0c0c0', PARAM_TEXT));
    
    // Level 3 - Advanced
    $settings->add(new admin_setting_configtext('local_point_badges/level3_name',
        'Level 3 Name', 'Name for the third level', 'Advanced', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_point_badges/level3_min',
        'Level 3 Min XP', 'Minimum XP required for Level 3', 301, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level3_max',
        'Level 3 Max XP', 'Maximum XP for Level 3', 700, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level3_color',
        'Level 3 Badge Color', 'Hex color code for Level 3 badge', '#ffd700', PARAM_TEXT));
    
    // Level 4 - Expert
    $settings->add(new admin_setting_configtext('local_point_badges/level4_name',
        'Level 4 Name', 'Name for the fourth level', 'Expert', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_point_badges/level4_min',
        'Level 4 Min XP', 'Minimum XP required for Level 4', 701, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_point_badges/level4_color',
        'Level 4 Badge Color', 'Hex color code for Level 4 badge', '#e5e4e2', PARAM_TEXT));
    
    // ========== LEADERBOARD SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/leaderboard_settings',
        '🏆 Leaderboard Settings', 'Configure how the leaderboard displays'));
    
    $settings->add(new admin_setting_configtext('local_point_badges/leaderboard_limit',
        'Default Leaderboard Limit', 'Number of users shown on leaderboard by default', 50, PARAM_INT));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/show_avatars',
        'Show User Avatars', 'Display user avatars in the leaderboard', 1));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/show_streaks',
        'Show Streak Information', 'Display user streak information in leaderboard', 1));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/show_department_ranking',
        'Enable Department Ranking', 'Allow users to see department-based rankings', 1));
    
    // ========== DAILY CHALLENGE SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/challenge_settings',
        '📋 Daily Challenge Settings', 'Configure the daily challenge system'));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/enable_challenges',
        'Enable Daily Challenges', 'Turn the daily challenge system on or off', 1));
    
    $settings->add(new admin_setting_configtext('local_point_badges/challenges_per_day',
        'Challenges Per Day', 'Number of daily challenges assigned to each user', 3, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/challenge_reset_hour',
        'Challenge Reset Hour', 'Hour of day when challenges reset (0-23)', 0, PARAM_INT));
    
    // ========== REWARD SHOP SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/shop_settings',
        '🎁 Reward Shop Settings', 'Configure XP costs for rewards in the shop'));
    
    $settings->add(new admin_setting_configtext('local_point_badges/certificate_cost',
        'Certificate Cost', 'XP required to purchase an achievement certificate', 500, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/extra_attempt_cost',
        'Extra Quiz Attempt Cost', 'XP required to purchase an extra quiz attempt', 100, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/coupon_discount_cost',
        'Discount Coupon Cost', 'XP required to purchase a discount coupon', 200, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/coupon_attempts_cost',
        'Extra Attempts Coupon Cost', 'XP required to purchase extra attempts coupon', 150, PARAM_INT));
    
    $settings->add(new admin_setting_configtext('local_point_badges/coupon_xpboost_cost',
        'XP Boost Coupon Cost', 'XP required to purchase XP boost coupon', 250, PARAM_INT));
        
    $settings->add(new admin_setting_configtext('local_point_badges/premium_content_cost',
        'Premium Content Unlock Cost', 'XP required to unlock premium courses', 1000, PARAM_INT));
        
    $settings->add(new admin_setting_configtext('local_point_badges/special_access_cost',
        'Special Access Cost', 'XP required to unlock special features & VIP sections', 800, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_point_badges/expert_level_number',
        'Expert Level Number', 'The level at which users automatically unlock VIP and Premium content', 4, PARAM_INT));
    
    // ========== CERTIFICATE SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/certificate_settings',
        '📜 Certificate Settings', 'Configure certificate generation options'));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/enable_certificates',
        'Enable Certificates', 'Allow users to earn certificates for leveling up', 1));
    
    $settings->add(new admin_setting_configtext('local_point_badges/certificate_background',
        'Certificate Background URL', 'URL to background image for certificates', '', PARAM_URL));
    
    // ========== NOTIFICATION SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/notification_settings',
        '🔔 Notification Settings', 'Configure when users receive notifications'));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/notify_level_up',
        'Notify on Level Up', 'Send a notification when a user levels up', 1));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/notify_streak_milestone',
        'Notify on Streak Milestones', 'Send a notification for 7, 30, and 100 day streaks', 1));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/notify_challenge_complete',
        'Notify on Challenge Completion', 'Send a notification when a daily challenge is completed', 1));
    
    // ========== ADVANCED SETTINGS ==========
    $settings->add(new admin_setting_heading('local_point_badges/advanced_settings',
        '⚙️ Advanced Settings', 'Advanced configuration options'));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/global_xp_mode',
        'Global XP Mode', 'If enabled, XP is tracked globally across all courses instead of per course', 0));
    
    $settings->add(new admin_setting_configtext('local_point_badges/xp_log_retention_days',
        'XP Log Retention Days', 'Number of days to keep XP log records (0 = forever)', 365, PARAM_INT));
    
    $settings->add(new admin_setting_configcheckbox('local_point_badges/allow_xp_deduction',
        'Allow XP Deduction', 'Allow XP to be deducted for purchasing rewards (if disabled, users cannot spend XP)', 1));
    
    // ========== INFO SECTION ==========
    $settings->add(new admin_setting_heading('local_point_badges/info',
        'ℹ️ About Point Badges System',
        'This plugin gamifies your Moodle site by awarding XP for activities, tracking levels, streaks, and daily challenges.'));
    // Signature Settings
$settings->add(new admin_setting_heading('local_point_badges/signature_settings',
    '✍️ Certificate Signature Settings',
    'Configure the signature that appears on certificates'));

$settings->add(new admin_setting_configtext('local_point_badges/signature_name',
    'Signatory Name', 
    'Name of the person authorizing the certificates', 
    'Dr. Muhammad Ahmed', 
    PARAM_TEXT));

$settings->add(new admin_setting_configtext('local_point_badges/signature_title',
    'Signatory Title',
    'Job title of the signatory',
    'Director of Academic Affairs',
    PARAM_TEXT));
    $ADMIN->add('localplugins', $settings);
}