<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class coupon_manager {
    
    /**
     * Generate coupon code
     */
    public static function generate_coupon($userid, $coupon_type, $xp_cost, $courseid = 0) {
        global $DB;
        
        // Only check XP balance if cost > 0 (automatic rewards have 0 cost)
        if ($xp_cost > 0) {
            $user_xp = manager::get_user_level_info($userid);
            if ($user_xp['total_xp'] < $xp_cost) {
                return ['success' => false, 'message' => 'Not enough XP'];
            }
        }
        
        // Generate unique coupon code
        $code = strtoupper(substr(md5(uniqid($userid . $coupon_type . time(), true)), 0, 12));
        
        $coupon = new \stdClass();
        $coupon->code = $code;
        $coupon->userid = $userid;
        $coupon->type = $coupon_type;
        $coupon->xp_cost = $xp_cost;
        $coupon->used = 0;
        $coupon->used_by = 0;
        $coupon->used_at = 0;
        $coupon->created_at = time();
        $coupon->expires_at = strtotime('+7 days');
        
        $id = $DB->insert_record('local_pb_coupons', $coupon);
        
        // Deduct XP only if cost > 0 (automatic rewards are free)
        if ($xp_cost > 0) {
            manager::deduct_xp($userid, $courseid, $xp_cost, 'coupon_purchase_' . $coupon_type);
        }
        
        return [
            'success' => true, 
            'code' => $code,
            'expires' => date('Y-m-d', $coupon->expires_at)
        ];
    }
    
    /**
     * Redeem coupon
     */
    public static function redeem_coupon($code, $redeeming_userid) {
        global $DB;
        
        $coupon = $DB->get_record('local_pb_coupons', ['code' => $code]);
        
        if (!$coupon) {
            return ['success' => false, 'message' => 'Invalid coupon code'];
        }
        
        if ($coupon->used) {
            return ['success' => false, 'message' => 'Coupon already used'];
        }
        
        if ($coupon->expires_at < time()) {
            return ['success' => false, 'message' => 'Coupon expired'];
        }
        
        // Apply coupon benefits
        $benefit = self::apply_coupon_benefit($coupon->type, $redeeming_userid);
        
        $coupon->used = 1;
        $coupon->used_by = $redeeming_userid;
        $coupon->used_at = time();
        $DB->update_record('local_pb_coupons', $coupon);
        
        return ['success' => true, 'benefit' => $benefit];
    }
    
    /**
     * Apply coupon benefit based on type
     */
    private static function apply_coupon_benefit($type, $userid) {
        global $DB;
        
        switch ($type) {
            case 'premium_course':
                // Unlock locked activities for the user
                return self::unlock_premium_activities($userid);
                
            case 'special_access':
                // Grant VIP access with early content access
                return self::grant_vip_special_access($userid);
                
            case 'discount':
                // Apply discount to user account using coupon_redemption helper
                \local_point_badges\coupon_redemption::set_user_preference('coupon_discount', 20, $userid);
                \local_point_badges\coupon_redemption::set_user_preference('coupon_discount_expiry', strtotime('+30 days'), $userid);
                \local_point_badges\coupon_redemption::set_user_preference('coupon_discount_consumed', 0, $userid);
                return '20% discount applied for your next purchase!';
                
            case 'extra_attempts':
                // Grant extra quiz attempts (generic - quizid = 0 means "any quiz")
                $extra = $DB->get_record('local_pb_extra_attempts', ['userid' => $userid, 'quizid' => 0]);
                if (!$extra) {
                    $extra = new \stdClass();
                    $extra->userid = $userid;
                    $extra->quizid = 0;
                    $extra->extra_attempts = 3;
                    $extra->used_attempts = 0;
                    $DB->insert_record('local_pb_extra_attempts', $extra);
                } else {
                    $extra->extra_attempts += 3;
                    $DB->update_record('local_pb_extra_attempts', $extra);
                }
                return '3 extra quiz attempts granted (usable on any quiz)!';
                
            case 'xp_boost':
                // Grant bonus XP
                manager::award_xp($userid, 0, 100, 'coupon_xp_boost_redeemed');
                return '100 bonus XP granted!';
                
            default:
                return 'Coupon redeemed successfully!';
        }
    }
    
    /**
     * Unlock premium activities that were previously locked
     * This gives the user access to all activities marked as "premium"
     */
    public static function unlock_premium_activities($userid) {
        global $DB;
        
        // Set user preference that premium content is unlocked
        \local_point_badges\coupon_redemption::set_user_preference('premium_content_unlocked', 1, $userid);
        \local_point_badges\coupon_redemption::set_user_preference('premium_unlocked_at', time(), $userid);
        \local_point_badges\coupon_redemption::set_user_preference('premium_expiry', strtotime('+7 days'), $userid);
        
        // Get all activities that have premium restrictions
        $sql = "SELECT cm.id, cm.course, cm.instance, m.name as modname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {local_pb_premium_restrictions} pr ON pr.cmid = cm.id
                WHERE pr.is_premium = 1";
        
        $premium_activities = $DB->get_records_sql($sql);
        
        $unlocked_count = 0;
        foreach ($premium_activities as $activity) {
            // Store which premium activities this user has unlocked
            $unlocked = new \stdClass();
            $unlocked->userid = $userid;
            $unlocked->cmid = $activity->id;
            $unlocked->unlocked_at = time();
            
            if (!$DB->record_exists('local_pb_unlocked_activities', ['userid' => $userid, 'cmid' => $activity->id])) {
                $DB->insert_record('local_pb_unlocked_activities', $unlocked);
                $unlocked_count++;
            }
        }
        
        return "✅ Premium Content Unlocked! You now have access to {$unlocked_count} premium activities.";
    }
    /**
 * Check if user already has premium content unlocked
 */
public static function has_premium_unlocked($userid) {
    $is_unlocked = (bool)\local_point_badges\coupon_redemption::get_user_preference('premium_content_unlocked', 0, $userid);
    $expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('premium_expiry', 0, $userid);
    
    if ($is_unlocked && ($expiry == 0 || $expiry > time())) {
        return true;
    }
    return false;
}

/**
 * Check if user already has special access
 */
public static function has_special_access($userid) {
    return self::has_vip_access($userid);
}
    /**
     * Grant VIP special access with early content unlocking
     */
    public static function grant_vip_special_access($userid) {
        global $DB;
        
        // Set VIP preferences
        \local_point_badges\coupon_redemption::set_user_preference('has_special_access', 1, $userid);
        \local_point_badges\coupon_redemption::set_user_preference('vip_access_granted_at', time(), $userid);
        \local_point_badges\coupon_redemption::set_user_preference('vip_expiry', strtotime('+7 days'), $userid);
        \local_point_badges\coupon_redemption::set_user_preference('early_access_enabled', 1, $userid);
        
        // Unlock VIP-only course sections/activities
        $sql = "SELECT cm.id, cm.course, cm.instance, m.name as modname
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {local_pb_vip_restrictions} vr ON vr.cmid = cm.id
                WHERE vr.is_vip_only = 1";
        
        $vip_activities = $DB->get_records_sql($sql);
        
        $vip_count = 0;
        foreach ($vip_activities as $activity) {
            $unlocked = new \stdClass();
            $unlocked->userid = $userid;
            $unlocked->cmid = $activity->id;
            $unlocked->unlocked_at = time();
            $unlocked->is_vip = 1;
            
            if (!$DB->record_exists('local_pb_unlocked_activities', ['userid' => $userid, 'cmid' => $activity->id])) {
                $DB->insert_record('local_pb_unlocked_activities', $unlocked);
                $vip_count++;
            }
        }
        
        // Also grant early access to upcoming content (set flag for future content)
        \local_point_badges\coupon_redemption::set_user_preference('early_access_beta', 1, $userid);
        
        return "🗝️ VIP Special Access Granted! You now have access to {$vip_count} VIP sections + Early access to new content!";
    }
    
    /**
     * Check if user has unlocked a specific activity
     */
    public static function is_activity_unlocked($userid, $cmid) {
        global $DB;
        return $DB->record_exists('local_pb_unlocked_activities', ['userid' => $userid, 'cmid' => $cmid]);
    }
    
    /**
     * Check if user has VIP access
     */
    public static function has_vip_access($userid) {
        // First check if user has reached the Expert level (earned VIP)
        $level_info = manager::get_user_level_info($userid);
        $expert_level = get_config('local_point_badges', 'expert_level_number') ?: 4;
        if ($level_info['current_level'] >= (int)$expert_level) {
            return true;
        }

        // Then check if they purchased/were granted VIP access manually
        $vip_expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('vip_expiry', 0, $userid);
        $has_access = (int)\local_point_badges\coupon_redemption::get_user_preference('has_special_access', 0, $userid);
        
        // Check if VIP hasn't expired
        if ($has_access && ($vip_expiry == 0 || $vip_expiry > time())) {
            return true;
        }
        return false;
    }
    
    /**
     * Check if user has early access to new content
     */
    public static function has_early_access($userid) {
        return (int)\local_point_badges\coupon_redemption::get_user_preference('early_access_enabled', 0, $userid) == 1;
    }
    
    /**
     * Get user's coupons
     */
    public static function get_user_coupons($userid) {
        global $DB;
        return $DB->get_records('local_pb_coupons', ['userid' => $userid, 'used' => 0], 'expires_at ASC');
    }
    
    /**
     * Get available coupon types for purchase
     */
    public static function get_available_coupon_types() {
        return [
            'discount' => ['name' => 'Discount Coupon', 'cost' => 1, 'description' => '20% off on next purchase'],
            'extra_attempts' => ['name' => 'Extra Attempts', 'cost' => 1, 'description' => '3 extra quiz attempts (any quiz)'],
            'xp_boost' => ['name' => 'XP Boost', 'cost' => 1, 'description' => '100 bonus XP'],
            'premium_course' => ['name' => 'Premium Content', 'cost' => 1, 'description' => 'Unlock premium course material (7 Days Access)'],
            'special_access' => ['name' => 'Special VIP Access', 'cost' => 1, 'description' => 'Unlock VIP features (7 Days Access)'],
        ];
    }
}
?>