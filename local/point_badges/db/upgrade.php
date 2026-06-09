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

function xmldb_local_point_badges_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2026021602) {
        $table = new xmldb_table('local_pb_xp_log');
        $index = new xmldb_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('user_time_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026021602, 'local', 'point_badges');
    }
    
    if ($oldversion < 2026021603) {
        $table = new xmldb_table('local_pb_user_xp');
        $index = new xmldb_index('course_level_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'current_level']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('user_course_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026021603, 'local', 'point_badges');
    }
    
    // // Create certificates table
    // if ($oldversion < 2026021604) {
    //     $table = new xmldb_table('local_pb_certificates');
    //     if (!$dbman->table_exists($table)) {
    //         $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    //         $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    //         $table->add_field('certificate_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('certificate_code', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    //         $table->add_field('issued_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    //         $table->add_field('downloaded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    //         $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    //         $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    //         $dbman->create_table($table);
    //     }
    //     upgrade_plugin_savepoint(true, 2026021604, 'local', 'point_badges');
    // }
    
    // Create restrictions table
    if ($oldversion < 2026021605) {
        $table = new xmldb_table('local_pb_restrictions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('restricted_level', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('cmid', XMLDB_KEY_UNIQUE, ['cmid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026021605, 'local', 'point_badges');
    }
    
    // Create coupons table
    if ($oldversion < 2026021606) {
        $table = new xmldb_table('local_pb_coupons');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('xp_cost', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('used', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('used_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('used_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('code', XMLDB_KEY_UNIQUE, ['code']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026021606, 'local', 'point_badges');
    }
    
    // Add used_attempts field to extra_attempts table
    if ($oldversion < 2026060305) {
        $table = new xmldb_table('local_pb_extra_attempts');
        
        if (!$dbman->table_exists($table)) {
            // Create table with both fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('extra_attempts', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('used_attempts', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('user_quiz', XMLDB_KEY_UNIQUE, ['userid', 'quizid']);
            $dbman->create_table($table);
        } else {
            // Add used_attempts field if missing
            $field = new xmldb_field('used_attempts', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'extra_attempts');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        
        upgrade_plugin_savepoint(true, 2026060305, 'local', 'point_badges');
    }
    // Add premium restrictions table
if ($oldversion < 2026060401) {
    $table = new xmldb_table('local_pb_premium_restrictions');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('is_premium', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid', XMLDB_KEY_UNIQUE, ['cmid']);
        $dbman->create_table($table);
    }
    upgrade_plugin_savepoint(true, 2026060401, 'local', 'point_badges');
}

// Add VIP restrictions table
if ($oldversion < 2026060402) {
    $table = new xmldb_table('local_pb_vip_restrictions');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('is_vip_only', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('early_access_days', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '7');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid', XMLDB_KEY_UNIQUE, ['cmid']);
        $dbman->create_table($table);
    }
    upgrade_plugin_savepoint(true, 2026060402, 'local', 'point_badges');
}

// Add unlocked activities table
if ($oldversion < 2026060403) {
    $table = new xmldb_table('local_pb_unlocked_activities');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('unlocked_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('is_vip', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_cm', XMLDB_KEY_UNIQUE, ['userid', 'cmid']);
        $dbman->create_table($table);
    }
    upgrade_plugin_savepoint(true, 2026060403, 'local', 'point_badges');
}

    if ($oldversion < 2026060901) {
        $table = new xmldb_table('local_pb_certificates');
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('tool_issue_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'issued_date');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        \local_point_badges\restriction_manager::sync_all_restrictions();
        upgrade_plugin_savepoint(true, 2026060901, 'local', 'point_badges');
    }

    if ($oldversion < 2026060902) {
        \local_point_badges\restriction_manager::sync_all_restrictions();
        upgrade_plugin_savepoint(true, 2026060902, 'local', 'point_badges');
    }

    if ($oldversion < 2026060903) {
        $now = time();

        $table = new xmldb_table('local_pb_premium_restrictions');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('local_pb_premium_restrictions', 'timemodified', $now);
        }

        $table = new xmldb_table('local_pb_vip_restrictions');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'early_access_days');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('local_pb_vip_restrictions', 'timemodified', $now);
        }

        \local_point_badges\restriction_manager::sync_all_restrictions();
        upgrade_plugin_savepoint(true, 2026060903, 'local', 'point_badges');
    }

    // In upgrade.php, add this at the end of the upgrade function
if ($oldversion < 2026060909) { // Use a version number higher than your current version
    global $DB;
    
    // Get all users at Expert level (level 4 or higher)
    $expert_level_number = 4;
    
    // Get all users who have reached level 4 or higher
    $sql = "SELECT DISTINCT ux.userid, ux.current_level
            FROM {local_pb_user_xp} ux
            WHERE ux.current_level >= :expert_level";
    
    $expert_users = $DB->get_records_sql($sql, ['expert_level' => $expert_level_number]);
    
    $awarded_special = 0;
    $awarded_premium = 0;
    
    foreach ($expert_users as $user) {
        // Check if user already has special access
        $has_special = \local_point_badges\coupon_manager::has_vip_access($user->userid);
        if (!$has_special) {
            \local_point_badges\coupon_manager::grant_vip_special_access($user->userid);
            $awarded_special++;
            
            // Log the award
            $log = new stdClass();
            $log->userid = $user->userid;
            $log->courseid = 0;
            $log->xp_amount = 0;
            $log->reason = 'auto_special_access_expert_level_upgrade';
            $log->timecreated = time();
            $DB->insert_record('local_pb_xp_log', $log);
        }
        
        // Check if user already has premium unlocked
        $has_premium = (bool)\local_point_badges\coupon_redemption::get_user_preference('premium_content_unlocked', 0, $user->userid);
        if (!$has_premium) {
            \local_point_badges\coupon_manager::unlock_premium_activities($user->userid);
            $awarded_premium++;
            
            // Log the award
            $log = new stdClass();
            $log->userid = $user->userid;
            $log->courseid = 0;
            $log->xp_amount = 0;
            $log->reason = 'auto_premium_content_expert_level_upgrade';
            $log->timecreated = time();
            $DB->insert_record('local_pb_xp_log', $log);
        }
    }
    
    mtrace("Expert level rewards awarded to: {$awarded_special} users (Special Access), {$awarded_premium} users (Premium Content)");
    
    upgrade_plugin_savepoint(true, 2026060909, 'local', 'point_badges');
}
    
    return true;
}