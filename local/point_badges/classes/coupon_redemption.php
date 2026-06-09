<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class coupon_redemption {
    
    /**
     * Get user preference value
     */
    public static function get_user_preference($name, $default = null, $userid = null) {
        global $DB, $USER;
        
        if ($userid === null) {
            $userid = $USER->id;
        }
        
        $value = $DB->get_field('user_preferences', 'value', [
            'userid' => $userid,
            'name' => $name
        ]);
        
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * Set user preference value
     */
    public static function set_user_preference($name, $value, $userid = null) {
        global $DB, $USER;
        
        if ($userid === null) {
            $userid = $USER->id;
        }
        
        $record = $DB->get_record('user_preferences', [
            'userid' => $userid,
            'name' => $name
        ]);
        
        if ($record) {
            $record->value = $value;
            return $DB->update_record('user_preferences', $record);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->name = $name;
            $record->value = $value;
            return $DB->insert_record('user_preferences', $record);
        }
    }
    
    /**
     * Show coupon redemption form in shop
     */
    public static function render_redemption_form() {
        $form = '<div class="coupon-redemption-box" style="background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%); border: 2px solid #ffd700; border-radius: 16px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #333;">🎫 Redeem a Coupon Code</h3>
            <p>Have a coupon code? Enter it below to claim your reward!</p>
            <form method="post" action="' . new \moodle_url('/local/point_badges/redeem_coupon.php') . '">
                <input type="hidden" name="sesskey" value="' . sesskey() . '">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="coupon_code" placeholder="Enter coupon code" 
                           style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px;" required>
                    <button type="submit" class="btn-purchase" style="background: linear-gradient(135deg, #4caf50, #45a049); color: white; border: none; padding: 10px 25px; border-radius: 25px; cursor: pointer; font-weight: bold;">
                        🎁 Redeem Coupon
                    </button>
                </div>
            </form>
        </div>';
        
        return $form;
    }
    
    /**
     * Process coupon redemption
     */
    public static function process_redemption($code, $userid) {
        global $DB;
        
        $coupon = $DB->get_record('local_pb_coupons', ['code' => trim($code)]);
        
        if (!$coupon) {
            return ['success' => false, 'message' => 'Invalid coupon code.'];
        }
        
        if ($coupon->used) {
            return ['success' => false, 'message' => 'This coupon has already been used.'];
        }
        
        if ($coupon->expires_at < time()) {
            return ['success' => false, 'message' => 'This coupon has expired.'];
        }
        
        // Apply benefit based on coupon type
        $result = self::apply_benefit($coupon, $userid);
        
        if ($result['success']) {
            // Mark coupon as used
            $coupon->used = 1;
            $coupon->used_by = $userid;
            $coupon->used_at = time();
            $DB->update_record('local_pb_coupons', $coupon);
        }
        
        return $result;
    }
    
    /**
     * Apply coupon benefit
     */
    private static function apply_benefit($coupon, $userid) {
        global $DB;
        
        switch ($coupon->type) {
            case 'discount':
                self::set_user_preference('coupon_discount', 20, $userid);
                self::set_user_preference('coupon_discount_expiry', strtotime('+30 days'), $userid);
                self::set_user_preference('coupon_discount_consumed', 0, $userid);
                return ['success' => true, 'message' => '✅ 20% discount coupon applied! Your next purchase will be discounted.'];
                
            case 'extra_attempts':
                // Grant 3 extra quiz attempts (generic - quizid = 0 means "any quiz")
                $extra = $DB->get_record('local_pb_extra_attempts', ['userid' => $userid, 'quizid' => 0]);
                if (!$extra) {
                    $extra = new \stdClass();
                    $extra->userid = $userid;
                    $extra->quizid = 0;  // 0 = generic attempts for any quiz
                    $extra->extra_attempts = 3;
                    $extra->used_attempts = 0;
                    $DB->insert_record('local_pb_extra_attempts', $extra);
                } else {
                    $extra->extra_attempts += 3;
                    $DB->update_record('local_pb_extra_attempts', $extra);
                }
                return ['success' => true, 'message' => '✅ 3 extra quiz attempts granted! These can be used on any quiz.'];
                
            case 'xp_boost':
                manager::award_xp($userid, 0, 100, 'coupon_xp_boost_redeemed');
                return ['success' => true, 'message' => '✅ 100 bonus XP added to your account!'];
                
            case 'premium_course':
                // Unlock premium content
                $result = coupon_manager::unlock_premium_activities($userid);
                return ['success' => true, 'message' => '✅ ' . $result];
                
            case 'special_access':
                // Grant VIP special access
                $result = coupon_manager::grant_vip_special_access($userid);
                return ['success' => true, 'message' => '✅ ' . $result];
                
            default:
                return ['success' => false, 'message' => 'Unknown coupon type: ' . $coupon->type];
        }
    }
    
    /**
     * Apply discount to a purchase price
     */
    public static function apply_discount($userid, $original_price) {
        $discount_percent = (int)self::get_user_preference('coupon_discount', 0, $userid);
        $discount_expiry = (int)self::get_user_preference('coupon_discount_expiry', 0, $userid);
        $discount_consumed = (int)self::get_user_preference('coupon_discount_consumed', 0, $userid);
        
        // Check if discount is valid
        $has_valid_discount = ($discount_percent > 0 && $discount_expiry > time() && $discount_consumed == 0);
        
        if ($has_valid_discount) {
            $discounted_price = $original_price * (1 - $discount_percent / 100);
            
            // Mark discount as consumed for this purchase
            self::set_user_preference('coupon_discount_consumed', 1, $userid);
            
            return [
                'applied' => true,
                'original' => $original_price,
                'discounted' => (int)$discounted_price,
                'saved' => $original_price - (int)$discounted_price,
                'percent' => $discount_percent
            ];
        }
        
        return [
            'applied' => false,
            'original' => $original_price,
            'discounted' => $original_price,
            'saved' => 0,
            'percent' => 0
        ];
    }
    
    /**
     * Get active discount info for display
     */
    public static function get_active_discount($userid) {
        $discount_percent = (int)self::get_user_preference('coupon_discount', 0, $userid);
        $discount_expiry = (int)self::get_user_preference('coupon_discount_expiry', 0, $userid);
        $discount_consumed = (int)self::get_user_preference('coupon_discount_consumed', 0, $userid);
        
        $has_active = ($discount_percent > 0 && $discount_expiry > time() && $discount_consumed == 0);
        
        if ($has_active) {
            return [
                'has_discount' => true,
                'percent' => $discount_percent,
                'expiry' => $discount_expiry,
                'expiry_date' => userdate($discount_expiry, get_string('strftimedate'))
            ];
        }
        
        return ['has_discount' => false];
    }
}
?>