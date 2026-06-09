<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class badge_manager {
    
    /**
     * Check and award achievement badges dynamically
     * @param int $userid
     * @param string $category (course_completion, high_score, attendance, skill, certification)
     * @param int $value Current metric value to evaluate badge level
     */
    public static function check_achievement_badge($userid, $category, $value) {
        global $DB;
        
        $badge_type = self::evaluate_badge_tier($category, $value);
        if (!$badge_type) {
            return false;
        }
        
        // Ensure table exists (temporary protection if upgrade hasn't run)
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_pb_user_badges')) {
            self::create_badge_table();
        }
        
        // Check if user already has this exact badge type for this category
        $exists = $DB->get_record('local_pb_user_badges', [
            'userid' => $userid,
            'category' => $category,
            'badge_type' => $badge_type
        ]);
        
        if (!$exists) {
            // Award the badge!
            $badge = new \stdClass();
            $badge->userid = $userid;
            $badge->badge_type = $badge_type;
            $badge->category = $category;
            $badge->timecreated = time();
            $DB->insert_record('local_pb_user_badges', $badge);
            
            // Additional XP for unlocking an achievement!
            $xp_bonus = [
                'Bronze' => 100,
                'Silver' => 250,
                'Gold' => 500,
                'Platinum' => 1000
            ];
            
            manager::award_xp($userid, 0, $xp_bonus[$badge_type], 'achievement_badge_' . strtolower($badge_type));
            
            // Send notification
            self::send_badge_notification($userid, $badge_type, $category);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Determine badge tier dynamically based on performance thresholds
     */
    private static function evaluate_badge_tier($category, $value) {
        switch ($category) {
            case 'course_completion':
                if ($value >= 20) return 'Platinum';
                if ($value >= 10) return 'Gold';
                if ($value >= 5) return 'Silver';
                if ($value >= 1) return 'Bronze';
                break;
                
            case 'high_score':
                if ($value >= 98) return 'Platinum';
                if ($value >= 90) return 'Gold';
                if ($value >= 80) return 'Silver';
                if ($value >= 70) return 'Bronze';
                break;
                
            case 'attendance':
                if ($value >= 300) return 'Platinum';
                if ($value >= 150) return 'Gold';
                if ($value >= 50) return 'Silver';
                if ($value >= 10) return 'Bronze';
                break;
                
            case 'skill':
                if ($value >= 100) return 'Platinum';
                if ($value >= 50) return 'Gold';
                if ($value >= 25) return 'Silver';
                if ($value >= 5) return 'Bronze';
                break;
                
            case 'certification':
                if ($value >= 5) return 'Platinum';
                if ($value >= 3) return 'Gold';
                if ($value >= 2) return 'Silver';
                if ($value >= 1) return 'Bronze';
                break;
        }
        return false;
    }
    
   // In send_badge_notification() method, update the eventdata object:
private static function send_badge_notification($userid, $badge_type, $category) {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) return;
    
    $cat_names = [
        'course_completion' => 'Course Completion',
        'high_score' => 'High Scores',
        'attendance' => 'Perfect Attendance',
        'skill' => 'Skill Mastery',
        'certification' => 'Certifications'
    ];
    
    $cat_name = isset($cat_names[$category]) ? $cat_names[$category] : 'Achievement';
    
    $eventdata = new \core\message\message();
    $eventdata->component = 'local_point_badges';  // Must match component name
    $eventdata->name = 'badge_earned';  // Must match the provider name in messages.php
    $eventdata->userfrom = \core_user::get_noreply_user();
    $eventdata->userto = $user;
    $eventdata->subject = "🏆 New Achievement Badge Unlocked!";
    $eventdata->fullmessage = "Congratulations! You earned the {$badge_type} Badge for {$cat_name}!";
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = "<p>Congratulations!</p><p>You earned the <strong>{$badge_type} Badge</strong> for <strong>{$cat_name}</strong>!</p>";
    $eventdata->smallmessage = "New {$badge_type} Badge unlocked!";
    $eventdata->notification = 1;
    
    message_send($eventdata);
}
    
    /**
     * Create table securely on the fly if needed
     */
    private static function create_badge_table() {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_pb_user_badges');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('badge_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }
    
    /**
     * Get user's custom achievement badges
     */
    public static function get_user_badges($userid) {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_pb_user_badges')) {
            return [];
        }
        return $DB->get_records('local_pb_user_badges', ['userid' => $userid], 'timecreated DESC');
    }
}
