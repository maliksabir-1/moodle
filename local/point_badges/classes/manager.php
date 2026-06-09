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



namespace local_point_badges;



defined('MOODLE_INTERNAL') || die();



class manager {

    

    // Default XP values (overridden by settings if configured)

    const XP_QUIZ_COMPLETE = 50;

    const XP_DAILY_LOGIN = 10;

    const XP_ASSIGNMENT_SUBMIT = 100;

    const XP_LESSON_COMPLETE = 30;

    const XP_FORUM_POST = 15;

    const XP_SCORM_COMPLETE = 40;

    const XP_STREAK_7 = 50;

    const XP_STREAK_30 = 200;

    

    /**

     * Get configured XP value for an activity

     */

    private static function get_configured_xp($default, $setting_name) {

        $configured = get_config('local_point_badges', $setting_name);

        return ($configured !== false && $configured !== '') ? (int)$configured : $default;

    }

    

    /**

     * Award XP to a user for an activity

     */

    public static function award_xp($userid, $courseid, $xp_amount, $reason) {

        global $DB, $USER;

        

        // Ensure courseid is an integer and not null

        $courseid = (int)$courseid;

        

        // Use configured XP values instead of hardcoded

        $xp_values = [

            'quiz_completed' => self::get_configured_xp(self::XP_QUIZ_COMPLETE, 'xp_quiz'),

            'daily_login' => self::get_configured_xp(self::XP_DAILY_LOGIN, 'xp_daily_login'),

            'assignment_submitted' => self::get_configured_xp(self::XP_ASSIGNMENT_SUBMIT, 'xp_assignment'),

            'forum_post' => self::get_configured_xp(self::XP_FORUM_POST, 'xp_forum'),

            'lesson_completed' => self::get_configured_xp(self::XP_LESSON_COMPLETE, 'xp_lesson'),

            'scorm_completed' => self::get_configured_xp(self::XP_SCORM_COMPLETE, 'xp_scorm'),

            'streak_7' => self::get_configured_xp(self::XP_STREAK_7, 'streak_7_bonus'),

            'streak_30' => self::get_configured_xp(self::XP_STREAK_30, 'streak_30_bonus'),

        ];

        

        // Override XP amount if reason matches a configured value

        if (isset($xp_values[$reason])) {

            $xp_amount = $xp_values[$reason];

        }

        

        // Get or create user xp record

        $record = $DB->get_record('local_pb_user_xp', 

            ['userid' => $userid, 'courseid' => $courseid]);

        

        if (!$record) {

            $record = new \stdClass();

            $record->userid = $userid;

            $record->courseid = $courseid;

            $record->total_xp = 0;

            $record->current_level = 1;

            $record->id = $DB->insert_record('local_pb_user_xp', $record);

        }

        

        $old_xp = $record->total_xp;

        $old_level = $record->current_level;

        

        // Add XP

        $record->total_xp += $xp_amount;

        

        // Calculate new level

        $new_level = self::calculate_level($record->total_xp);

        $record->current_level = $new_level;

        

        $DB->update_record('local_pb_user_xp', $record);

        

        // Log the XP earning

        $log = new \stdClass();

        $log->userid = $userid;

        $log->courseid = $courseid;

        $log->xp_amount = $xp_amount;

        $log->reason = $reason;

        $log->timecreated = time();

        $DB->insert_record('local_pb_xp_log', $log);

        

        // Check for daily challenge completion

        self::check_daily_challenge_progress($userid, $reason);

        

        // Trigger events

        self::trigger_xp_event($userid, $courseid, $xp_amount, $reason, $old_xp, $record->total_xp);

        

        if ($new_level > $old_level) {

            self::trigger_level_up_event($userid, $courseid, $old_level, $new_level);

            

            // Issue certificate for reaching new level

            certificate_manager::issue_level_certificate($userid, $new_level);

        }

        

        return true;

    }

    

    /**

     * Deduct XP from user (for purchasing rewards)

     */

    public static function deduct_xp($userid, $courseid, $xp_amount, $reason) {

        global $DB;

        

        // Ensure courseid is an integer and not null

        $courseid = (int)$courseid;

        

        $record = $DB->get_record('local_pb_user_xp', 

            ['userid' => $userid, 'courseid' => $courseid]);

        

        if (!$record) {

            $record = new \stdClass();

            $record->userid = $userid;

            $record->courseid = $courseid;

            $record->total_xp = 0;

            $record->current_level = 1;

            $record->id = $DB->insert_record('local_pb_user_xp', $record);

        }

        

        // Verify global total is enough before allowing negative balance on course 0

        $global_xp = $DB->get_field_sql("SELECT SUM(total_xp) FROM {local_pb_user_xp} WHERE userid = ?", [$userid]) ?: 0;

        if ($global_xp < $xp_amount) {

            return false;

        }

        

        $old_xp = $record->total_xp;

        $old_level = $record->current_level;

        

        $record->total_xp -= $xp_amount;

        $record->current_level = self::calculate_level($record->total_xp);

        

        $DB->update_record('local_pb_user_xp', $record);

        

        // Log the deduction

        $log = new \stdClass();

        $log->userid = $userid;

        $log->courseid = $courseid;

        $log->xp_amount = -$xp_amount;

        $log->reason = $reason;

        $log->timecreated = time();

        $DB->insert_record('local_pb_xp_log', $log);

        

        // Trigger event for XP deduction

        self::trigger_xp_event($userid, $courseid, -$xp_amount, $reason, $old_xp, $record->total_xp);

        

        // Check if level changed

        if ($record->current_level < $old_level) {

            self::trigger_level_down_event($userid, $courseid, $old_level, $record->current_level);

        }

        

        return true;

    }

    

    /**

     * Calculate level based on XP using database levels

     */

    public static function calculate_level($xp) {

        global $DB;

        

        $levels = $DB->get_records('local_pb_levels', [], 'min_xp DESC');

        

        if (empty($levels)) {

            // Fallback if no levels in database

            if ($xp >= 701) return 4;

            if ($xp >= 301) return 3;

            if ($xp >= 101) return 2;

            return 1;

        }

        

        foreach ($levels as $level) {

            if ($xp >= $level->min_xp) {

                return $level->level_number;

            }

        }

        

        return 1;

    }

    

    /**

     * Get level details for a specific level

     */

    public static function get_level_details($level_number) {

        global $DB;

        

        $level = $DB->get_record('local_pb_levels', ['level_number' => $level_number]);

        

        if ($level) {

            return [

                'name' => $level->level_name,

                'badge_color' => $level->badge_color,

                'badge_url' => $level->badge_url,

                'min_xp' => $level->min_xp,

                'max_xp' => $level->max_xp,

                'level_number' => $level->level_number,

            ];

        }

        

        // Dynamic fallback colors if level not in DB
        $fallback_colors = [
            1 => '#cd7f32', // Bronze
            2 => '#c0c0c0', // Silver
            3 => '#ffd700', // Gold
            4 => '#9c27b0', // Master (Purple)
            5 => '#e91e63'  // Grandmaster (Pink)
        ];
        $fallback_color = isset($fallback_colors[$level_number]) ? $fallback_colors[$level_number] : '#4caf50';

        // Dynamic fallback icons if level not in DB
        $fallback_icons = [
            1 => 'fa-award',
            2 => 'fa-medal',
            3 => 'fa-trophy',
            4 => 'fa-crown',
            5 => 'fa-gem'
        ];
        $fallback_icon = isset($fallback_icons[$level_number]) ? $fallback_icons[$level_number] : 'fa-star';

        return [
            'name' => 'Level ' . $level_number,
            'badge_color' => $fallback_color,
            'badge_url' => null,
            'fallback_icon' => $fallback_icon,
            'min_xp' => ($level_number - 1) * 100,
            'max_xp' => $level_number * 100,
            'level_number' => $level_number,
        ];

    }

    

    public static function get_user_level_info($userid, $courseid = null) {

        global $DB;

        


        $total_course_xp = 0;

        if ($courseid) {

            $record = $DB->get_record('local_pb_user_xp', ['userid' => $userid, 'courseid' => $courseid]);

            if ($record) {

                $total_course_xp = $record->total_xp;

            }

        }

        

        // Get total across all courses to determine true global level

        $sql = "SELECT SUM(total_xp) as total_xp FROM {local_pb_user_xp} WHERE userid = ?";

        $global_total = $DB->get_field_sql($sql, [$userid]) ?: 0;

        

        $current_level = self::calculate_level($global_total);

        $level_info = self::get_level_details($current_level);

        

        // The XP returned depends on context (course specific or global)

        $display_xp = $courseid ? $total_course_xp : $global_total;

        

        // Calculate progress to next level using GLOBAL xp

        $next_level = self::get_level_details($current_level + 1);

        $progress = 0;

        $xp_needed = 0;

        

        if ($next_level && isset($next_level['min_xp']) && $next_level['min_xp'] > 0) {

            $xp_for_current = $level_info['min_xp'];

            $xp_for_next = $next_level['min_xp'];

            $xp_earned_in_level = max(0, $global_total - $xp_for_current);

            $xp_required_for_level = max(1, $xp_for_next - $xp_for_current);

            

            $progress = min(100, round(($xp_earned_in_level / $xp_required_for_level) * 100));

            $xp_needed = max(0, $xp_for_next - $global_total);

        } else {

            $progress = 100; // max level

        }

        

        return [

            'total_xp' => $display_xp,

            'current_level' => $current_level,

            'level_name' => $level_info['name'],

            'badge_color' => $level_info['badge_color'],
            'badge_url' => $level_info['badge_url'],
            'fallback_icon' => isset($level_info['fallback_icon']) ? $level_info['fallback_icon'] : 'fa-medal',

            'progress_percent' => $progress,

            'xp_needed_next_level' => $xp_needed,

        ];

    }

    

    /**

     * Update streak on user login

     */

    public static function update_login_streak($userid) {

        global $DB;

        

        $today = time();

        $today_date = date('Y-m-d', $today);

        

        $streak = $DB->get_record('local_pb_streak', ['userid' => $userid]);

        

        if (!$streak) {

            $streak = new \stdClass();

            $streak->userid = $userid;

            $streak->current_streak = 1;

            $streak->max_streak = 1;

            $streak->last_login_date = $today;

            $DB->insert_record('local_pb_streak', $streak);

            

            // Award daily login XP

            self::award_xp($userid, 0, self::XP_DAILY_LOGIN, 'daily_login');

            return;

        }

        

        $last_date = date('Y-m-d', $streak->last_login_date);

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        

        if ($today_date == $last_date) {

            // Already logged in today - do nothing

            return;

        } elseif ($last_date == $yesterday) {

            // Consecutive day - increase streak

            $streak->current_streak++;

            if ($streak->current_streak > $streak->max_streak) {

                $streak->max_streak = $streak->current_streak;

            }

            

            // Bonus XP for streaks

            if ($streak->current_streak == 7) {

                self::award_xp($userid, 0, self::XP_STREAK_7, 'streak_7');

            } elseif ($streak->current_streak == 30) {

                self::award_xp($userid, 0, self::XP_STREAK_30, 'streak_30');

            } elseif ($streak->current_streak == 100) {

                self::award_xp($userid, 0, 500, 'streak_100');

            }

        } else {

            // Streak broken

            $streak->current_streak = 1;

        }

        

        $streak->last_login_date = $today;

        $DB->update_record('local_pb_streak', $streak);

        

        // Award daily login XP

        self::award_xp($userid, 0, self::XP_DAILY_LOGIN, 'daily_login');

    }

    

    /**

     * Get user's streak info

     */

    public static function get_user_streak($userid) {

        global $DB;

        return $DB->get_record('local_pb_streak', ['userid' => $userid]);

    }

    

    /**

     * Get leaderboard data - FIXED with proper user fields

     */

    public static function get_leaderboard($courseid = null, $limit = 10, $offset = 0) {

        global $DB, $CFG;

        

        $params = [];

        

        if ($courseid && $courseid > 0) {

            $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.picture, u.imagealt,

                           u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,

                           ux.total_xp, ux.current_level

                    FROM {user} u

                    INNER JOIN {local_pb_user_xp} ux ON ux.userid = u.id

                    WHERE ux.courseid = :courseid

                    AND u.deleted = 0

                    ORDER BY ux.total_xp DESC";

            $params['courseid'] = $courseid;

        } else {

            // Global leaderboard - sum XP across all courses

            $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.picture, u.imagealt,

                           u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,

                           SUM(ux.total_xp) as total_xp

                    FROM {user} u

                    INNER JOIN {local_pb_user_xp} ux ON ux.userid = u.id

                    WHERE u.deleted = 0

                    GROUP BY u.id, u.firstname, u.lastname, u.username, u.email, u.picture, u.imagealt,

                             u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename

                    ORDER BY total_xp DESC";

        }

        

        $users = $DB->get_records_sql($sql, $params, $offset, $limit);

        

        // Add rank and level details to each user

        $rank = $offset + 1;

        $result = [];

        

        foreach ($users as $user) {

            // Dynamically calculate level to avoid split-XP bugs

            $user->current_level = self::calculate_level($user->total_xp);

            $level_info = self::get_level_details($user->current_level);

            $user->rank = $rank++;

            $user->level_name = $level_info['name'];

            $user->badge_color = $level_info['badge_color'];

            $user->fullname = fullname($user);

            $result[] = $user;

        }

        

        return $result;

    }

    

    /**

     * Get weekly champion (user with most XP this week)

     */

    public static function get_weekly_champion($courseid = null) {

        global $DB;

        

        $week_start = strtotime('monday this week', time());

        

        $params = ['week_start' => $week_start];

        

        $sql = "SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.picture, u.imagealt,

                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,

                       SUM(l.xp_amount) as weekly_xp

                FROM {user} u

                INNER JOIN {local_pb_xp_log} l ON l.userid = u.id

                WHERE l.timecreated >= :week_start

                AND l.xp_amount > 0

                AND u.deleted = 0";

        

        if ($courseid && $courseid > 0) {

            $sql .= " AND l.courseid = :courseid";

            $params['courseid'] = $courseid;

        }

        

        $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.username, u.email, u.picture, u.imagealt,

                         u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename

                  ORDER BY weekly_xp DESC

                  LIMIT 1";

        

        $champion = $DB->get_record_sql($sql, $params);

        

        if ($champion) {

            $champion->fullname = fullname($champion);

        }

        

        return $champion;

    }

    

    /**

     * Assign daily challenges to a user

     */

    public static function assign_daily_challenges($userid) {

        global $DB;

        

        $today = time();

        $today_start = strtotime(date('Y-m-d', $today));

        

        // Delete old challenges for today

        $DB->delete_records('local_pb_user_challenge', [

            'userid' => $userid,

            'date_assigned' => $today_start

        ]);

        

        // Get active challenges

        $challenges = $DB->get_records('local_pb_challenge', ['active' => 1]);

        

        if (empty($challenges)) {

            // If the table is completely empty, insert default challenges for the user

            self::bootstrap_default_challenges();

            $challenges = $DB->get_records('local_pb_challenge', ['active' => 1]);

            

            if (empty($challenges)) {

                mtrace("WARNING: No active challenges found in database even after bootstrap!");

                return true;

            }

        }

        

        // We want to assign all active challenges for the day

        foreach ($challenges as $challenge) {

            $user_challenge = new \stdClass();

            $user_challenge->userid = $userid;

            $user_challenge->challengeid = $challenge->id;

            $user_challenge->progress = 0;

            $user_challenge->completed = 0;

            $user_challenge->date_assigned = $today_start;

            $DB->insert_record('local_pb_user_challenge', $user_challenge);

        }

        

        return true;

    }

    

    /**

     * Bootstrap default challenges into the database if none exist

     */

    public static function bootstrap_default_challenges() {

        global $DB;

        

        $defaults = [

            [

                'name' => 'Knowledge Seeker',

                'description' => 'Complete 2 quizzes.',

                'event_name' => 'quiz_completed',

                'required_count' => 2,

                'xp_reward' => 100,

                'active' => 1

            ],

            [

                'name' => 'Consistent Learner',

                'description' => 'Submit 1 assignment today.',

                'event_name' => 'assignment_submitted',

                'required_count' => 1,

                'xp_reward' => 50,

                'active' => 1

            ],

            [

                'name' => 'Social Butterfly',

                'description' => 'Create 3 forum posts.',

                'event_name' => 'forum_post',

                'required_count' => 3,

                'xp_reward' => 75,

                'active' => 1

            ],

            [

                'name' => 'Lesson Master',

                'description' => 'Complete 1 interactive lesson.',

                'event_name' => 'lesson_completed',

                'required_count' => 1,

                'xp_reward' => 50,

                'active' => 1

            ]

        ];

        

        foreach ($defaults as $def) {

            $DB->insert_record('local_pb_challenge', (object)$def);

        }

    }

    

    /**

     * Check and update daily challenge progress

     */

    public static function check_daily_challenge_progress($userid, $event_name) {

        global $DB;

        

        // Check if challenges are enabled

        $enabled = get_config('local_point_badges', 'enable_challenges');

        if (empty($enabled)) {

            return true;

        }

        

        // Ensure challenges are generated for today if they don't exist

        self::get_user_daily_challenges($userid);

        

        $today_start = strtotime(date('Y-m-d', time()));

        

        // Get incomplete challenges for today that match this event

        $sql = "SELECT uc.*, c.required_count, c.xp_reward, c.event_name, c.name, c.id as challenge_id

                FROM {local_pb_user_challenge} uc

                INNER JOIN {local_pb_challenge} c ON c.id = uc.challengeid

                WHERE uc.userid = :userid

                AND uc.completed = 0

                AND uc.date_assigned = :date_assigned

                AND c.event_name = :event_name

                AND c.active = 1";

        

        $params = [

            'userid' => $userid,

            'date_assigned' => $today_start,

            'event_name' => $event_name

        ];

        

        $challenges = $DB->get_records_sql($sql, $params);

        

        foreach ($challenges as $challenge) {

            // Increment progress

            $challenge->progress++;

            

            // Prepare update object

            $update_obj = new \stdClass();

            $update_obj->id = $challenge->id;

            $update_obj->progress = $challenge->progress;

            

            // Check if completed

            if ($challenge->progress >= $challenge->required_count && !$challenge->completed) {

                // Challenge completed!

                $update_obj->completed = 1;

                $DB->update_record('local_pb_user_challenge', $update_obj);

                

                // Award XP for completing challenge

                self::award_xp($userid, 0, $challenge->xp_reward, 'challenge_completed_' . $challenge->challenge_id);

                

                // Send notification

                self::send_challenge_completion_notification($userid, $challenge);

            } else {

                // Just update progress

                $DB->update_record('local_pb_user_challenge', $update_obj);

            }

        }

        

        return true;

    }

    

    /**

     * Send notification for challenge completion

     */

    private static function send_challenge_completion_notification($userid, $challenge) {

        global $DB;

        

        $user = $DB->get_record('user', ['id' => $userid]);

        if (!$user) {

            return;

        }

        

        $eventdata = new \core\message\message();

        $eventdata->component = 'local_point_badges';

        $eventdata->name = 'challenge_completed';

        $eventdata->userfrom = \core_user::get_noreply_user();

        $eventdata->userto = $user;

        $eventdata->subject = 'Daily Challenge Completed! 🎉';

        $eventdata->fullmessage = "Congratulations! You completed the challenge '{$challenge->name}' and earned {$challenge->xp_reward} XP!";

        $eventdata->fullmessageformat = FORMAT_PLAIN;

        $eventdata->fullmessagehtml = "<p>Congratulations! 🎉</p><p>You completed the challenge <strong>'{$challenge->name}'</strong> and earned <strong>{$challenge->xp_reward} XP</strong>!</p>";

        $eventdata->smallmessage = "Challenge completed! +{$challenge->xp_reward} XP";

        $eventdata->notification = 1;

        

        message_send($eventdata);

    }

    

    /**

     * Get user's daily challenges

     */

    public static function get_user_daily_challenges($userid, $is_retry = false) {

        global $DB;

        

        $today_start = strtotime(date('Y-m-d', time()));

        

        $sql = "SELECT uc.*, c.name, c.description, c.required_count, c.xp_reward

                FROM {local_pb_user_challenge} uc

                INNER JOIN {local_pb_challenge} c ON c.id = uc.challengeid

                WHERE uc.userid = :userid

                AND uc.date_assigned = :date_assigned";

        

        $params = [

            'userid' => $userid,

            'date_assigned' => $today_start

        ];

        

        $challenges = $DB->get_records_sql($sql, $params);

        

        // If no challenges assigned today, assign them

        if (empty($challenges) && !$is_retry) {

            self::assign_daily_challenges($userid);

            return self::get_user_daily_challenges($userid, true);

        }

        

        return $challenges;

    }

    

    /**

     * Trigger XP earned event

     */

    private static function trigger_xp_event($userid, $courseid, $xp, $reason, $old_xp, $new_xp) {

        $context = ($courseid && $courseid > 0) ? \context_course::instance($courseid) : \context_system::instance();

        $event = \local_point_badges\event\xp_earned::create([

            'context' => $context,

            'relateduserid' => $userid,

            'other' => [

                'xp_amount' => $xp,

                'reason' => $reason,

                'old_total' => $old_xp,

                'new_total' => $new_xp

            ]

        ]);

        $event->trigger();

    }

    

    /**

     * Trigger level up event and send notification

     */

private static function trigger_level_up_event($userid, $courseid, $old_level, $new_level) {
    global $DB;

    $context = ($courseid && $courseid > 0) ? \context_course::instance($courseid) : \context_system::instance();
    
    // Trigger the event
    $event = \local_point_badges\event\level_up::create([
        'context' => $context,
        'relateduserid' => $userid,
        'other' => [
            'old_level' => $old_level,
            'new_level' => $new_level
        ]
    ]);
    $event->trigger();
    
    // ===== NEW: Auto-award special access and premium content when reaching Expert level =====
    $expert_level_number = get_config('local_point_badges', 'expert_level_number') ?: 4;
    
    if ($new_level >= (int)$expert_level_number && $old_level < (int)$expert_level_number) {
        // Check if user already has special access and premium content
        $has_special_access = \local_point_badges\coupon_manager::has_vip_access($userid);
        $has_premium_unlocked = (bool)\local_point_badges\coupon_redemption::get_user_preference('premium_content_unlocked', 0, $userid);
        
        $unlock_messages = [];
        
        // Award Special Access (VIP) if not already granted
        if (!$has_special_access) {
            $result = \local_point_badges\coupon_manager::grant_vip_special_access($userid);
            $unlock_messages[] = $result;
            
            // Log the auto-award
            $log = new \stdClass();
            $log->userid = $userid;
            $log->courseid = $courseid;
            $log->xp_amount = 0;
            $log->reason = 'auto_special_access_expert_level';
            $log->timecreated = time();
            $DB->insert_record('local_pb_xp_log', $log);
        }
        
        // Award Premium Content Unlock if not already granted
        if (!$has_premium_unlocked) {
            $result = \local_point_badges\coupon_manager::unlock_premium_activities($userid);
            $unlock_messages[] = $result;
            
            // Log the auto-award
            $log = new \stdClass();
            $log->userid = $userid;
            $log->courseid = $courseid;
            $log->xp_amount = 0;
            $log->reason = 'auto_premium_content_expert_level';
            $log->timecreated = time();
            $DB->insert_record('local_pb_xp_log', $log);
        }
        
        // Send combined notification about unlocks
        if (!empty($unlock_messages)) {
            self::send_expert_level_unlock_notification($userid, $unlock_messages);
        }
    }
    
    // Send a notification to the user
    $level_info = self::get_level_details($new_level);
    $user = $DB->get_record('user', ['id' => $userid]);
    
    if ($user) {
        $eventdata = new \core\message\message();
        $eventdata->component = 'local_point_badges';
        $eventdata->name = 'level_up';
        $eventdata->userfrom = \core_user::get_noreply_user();
        $eventdata->userto = $user;
        $eventdata->subject = get_string('level_up', 'local_point_badges');
        $eventdata->fullmessage = "Congratulations! You've reached {$level_info['name']} level!";
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = "<p>Congratulations! You've reached <strong>{$level_info['name']}</strong> level!</p>";
        $eventdata->smallmessage = "Level up! You're now a {$level_info['name']}!";
        $eventdata->notification = 1;
        
        message_send($eventdata);
    }
}

/**
 * Send notification for Expert level unlocks
 */
private static function send_expert_level_unlock_notification($userid, $unlock_messages) {
    global $DB, $USER;
    
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        return;
    }
    
    $unlock_text = implode("\n", $unlock_messages);
    $unlock_html = implode("<br>", array_map(function($msg) {
        return "• " . $msg;
    }, $unlock_messages));
    
    $eventdata = new \core\message\message();
    $eventdata->component = 'local_point_badges';
    $eventdata->name = 'level_up';
    $eventdata->userfrom = \core_user::get_noreply_user();
    $eventdata->userto = $user;
    $eventdata->subject = "👑 Congratulations! You've reached EXPERT Level!";
    $eventdata->fullmessage = "Congratulations on reaching EXPERT level!\n\nAs a reward, you have received:\n" . $unlock_text;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = "
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 16px; color: white; text-align: center;'>
            <h2>🏆 CONGRATULATIONS! 🏆</h2>
            <h3>You've reached EXPERT Level!</h3>
            <div style='background: white; color: #333; padding: 15px; border-radius: 12px; margin-top: 15px; text-align: left;'>
                <p><strong>🎁 Your Rewards:</strong></p>
                <div>" . $unlock_html . "</div>
            </div>
            <p style='margin-top: 15px;'>Thank you for your dedication to learning!</p>
        </div>
    ";
    $eventdata->smallmessage = "👑 EXPERT Level unlocked! Check your rewards!";
    $eventdata->notification = 1;
    
    message_send($eventdata);
}

    

    /**

     * Trigger level down event (when XP is deducted)

     */

    private static function trigger_level_down_event($userid, $courseid, $old_level, $new_level) {

        $context = ($courseid && $courseid > 0) ? \context_course::instance($courseid) : \context_system::instance();

        $event = \local_point_badges\event\level_down::create([

            'context' => $context,

            'relateduserid' => $userid,

            'other' => [

                'old_level' => $old_level,

                'new_level' => $new_level

            ]

        ]);

        $event->trigger();

    }

}