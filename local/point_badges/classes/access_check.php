<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class access_check {

    /**
     * Check if user can access an activity.
     */
    public static function can_access_activity($userid, $cmid) {
        global $DB;

        $ispremium = $DB->get_record('local_pb_premium_restrictions', ['cmid' => $cmid]);
        if ($ispremium) {
            return self::has_valid_premium_access($userid, $cmid, $ispremium);
        }

        $isvip = $DB->get_record('local_pb_vip_restrictions', ['cmid' => $cmid]);
        if ($isvip) {
            return self::has_valid_vip_access($userid, $cmid, $isvip);
        }

        return true;
    }

    /**
     * Premium access is valid only if the unlock happened after the restriction was last applied.
     */
    protected static function has_valid_premium_access(int $userid, int $cmid, \stdClass $restriction): bool {
        global $DB;

        // If user has the global 'Premium' bundle unlocked, they get access regardless of when the activity was modified.
        if ((int)coupon_redemption::get_user_preference('premium_content_unlocked', 0, $userid)) {
            return true;
        }

        $epoch = (int)($restriction->timemodified ?? 0);
        $activityunlock = $DB->get_record('local_pb_unlocked_activities', [
            'userid' => $userid,
            'cmid' => $cmid,
        ]);
        
        if ($activityunlock && (int)$activityunlock->unlocked_at >= $epoch) {
            return true;
        }

        return false;
    }

    /**
     * VIP access is valid only if access was granted after the restriction was last applied.
     */
    protected static function has_valid_vip_access(int $userid, int $cmid, \stdClass $restriction): bool {
        global $DB;

        // If user is a VIP member, they get access regardless of when the activity was modified.
        if (coupon_manager::has_vip_access($userid)) {
            return true;
        }

        $epoch = (int)($restriction->timemodified ?? 0);
        $activityunlock = $DB->get_record('local_pb_unlocked_activities', [
            'userid' => $userid,
            'cmid' => $cmid,
        ]);
        
        if ($activityunlock && (int)$activityunlock->unlocked_at >= $epoch) {
            return true;
        }

        return false;
    }

    /**
     * Get restriction message for an activity.
     */
    public static function get_restriction_message($cmid) {
        global $DB, $USER;

        $ispremium = $DB->get_record('local_pb_premium_restrictions', ['cmid' => $cmid]);
        if ($ispremium && !self::has_valid_premium_access($USER->id, $cmid, $ispremium)) {
            return '🔒 This is premium content. Purchase <strong>Premium Content</strong> to access.';
        }

        $isvip = $DB->get_record('local_pb_vip_restrictions', ['cmid' => $cmid]);
        if ($isvip && !self::has_valid_vip_access($USER->id, $cmid, $isvip)) {
            return '👑 This is VIP-only content. Purchase <strong>Special VIP Access</strong> to unlock.';
        }

        return 'Access restricted';
    }

    /**
     * Check if user has early access to content.
     */
    public static function has_early_access_to_activity($userid, $cmid) {
        global $DB;

        $vipactivity = $DB->get_record('local_pb_vip_restrictions', ['cmid' => $cmid]);
        if ($vipactivity && coupon_manager::has_early_access($userid)) {
            return true;
        }

        return false;
    }

    /**
     * Debug method to check user's unlock status.
     */
    public static function debug_user_unlock_status($userid) {
        return [
            'premium_unlocked' => (int)coupon_redemption::get_user_preference('premium_content_unlocked', 0, $userid),
            'has_vip_access' => coupon_manager::has_vip_access($userid),
            'premium_unlocked_at' => coupon_redemption::get_user_preference('premium_unlocked_at', 0, $userid),
            'vip_expiry' => coupon_redemption::get_user_preference('vip_expiry', 0, $userid),
        ];
    }
}
