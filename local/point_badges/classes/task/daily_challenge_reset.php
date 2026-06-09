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

namespace local_point_badges\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to reset daily challenges at midnight.
 */
class daily_challenge_reset extends \core\task\scheduled_task {
    
    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_point_badges') . ' - Daily Challenge Reset';
    }
    
    /**
     * Executes the task to assign new daily challenges to active users.
     */
    public function execute() {
        global $DB;
        
        mtrace("Starting daily challenge reset...");
        
        // Check if challenges are enabled
        $enabled = get_config('local_point_badges', 'enable_challenges');
        if ($enabled === false || $enabled == 0) {
            mtrace("Daily challenges are disabled in settings. Skipping...");
            return true;
        }
        
        // Get all active users (who have logged in within last 30 days)
        // Also include users who have XP records
        $sql = "SELECT DISTINCT userid 
                FROM (
                    SELECT userid FROM {local_pb_xp_log} WHERE timecreated > :cutoff
                    UNION
                    SELECT userid FROM {local_pb_streak}
                    UNION
                    SELECT userid FROM {local_pb_user_xp}
                ) AS active_users";
        
        $params = ['cutoff' => strtotime('-30 days')];
        $users = $DB->get_records_sql($sql, $params);
        
        $count = 0;
        foreach ($users as $user) {
            \local_point_badges\manager::assign_daily_challenges($user->userid);
            $count++;
            if ($count % 100 == 0) {
                mtrace("Assigned challenges to {$count} users...");
            }
        }
        
        mtrace("Daily challenge reset completed. Assigned challenges to {$count} users.");
        
        return true;
    }
}