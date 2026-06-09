<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();

$PAGE->set_url(new moodle_url('/local/point_badges/shop.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('shop_title', 'local_point_badges'));
$PAGE->set_heading(get_string('shop_title', 'local_point_badges'));

// Read both POST and GET to support both form methods
$action      = optional_param('action', '', PARAM_ALPHA);
$item        = optional_param('item', '', PARAM_ALPHAEXT);
$quizid      = optional_param('quizid', 0, PARAM_INT);
$coupon_type = optional_param('coupon_type', '', PARAM_ALPHAEXT);

// Get user's XP
$level_info = \local_point_badges\manager::get_user_level_info($USER->id);
$user_xp    = $level_info['total_xp'];
// Data correction: Ensure legacy 1-year expirations are capped at 7 days
$corrections = ['vip_expiry', 'premium_expiry', 'extra_attempt_expiry_0'];
foreach ($corrections as $pref) {
    $current_exp = (int)\local_point_badges\coupon_redemption::get_user_preference($pref, 0, $USER->id);
    if ($current_exp > strtotime('+8 days')) {
        \local_point_badges\coupon_redemption::set_user_preference($pref, strtotime('+7 days'), $USER->id);
    }
}

// Handle purchases
if ($action == 'buy' && confirm_sesskey()) {
    $success = false;
    $message = '';
    
    switch ($item) {
        case 'certificate':
            global $DB;
            $cost = get_config('local_point_badges', 'certificate_cost') ?: 1;
            
            // Apply discount if available
            $discount_info = \local_point_badges\coupon_redemption::apply_discount($USER->id, $cost);
            $final_cost = $discount_info['discounted'];
            
            if ($user_xp >= $final_cost) {
                // Get ALL available certificate templates from tool_certificate
                $available_templates = \local_point_badges\certificate_manager::get_available_templates();
                
                if (empty($available_templates)) {
                    // Fallback: Create local certificate
                    $result = \local_point_badges\certificate_manager::issue_local_certificate($USER->id, 'Achievement Certificate');
                    if ($result) {
                        \local_point_badges\manager::deduct_xp($USER->id, 0, $final_cost, 'purchased_certificate');
                        $message = '🏅 Certificate issued successfully! Check "Your Certificates" section below.';
                        if ($discount_info['applied']) {
                            $message .= ' (Discount applied: saved ' . number_format($discount_info['saved']) . ' XP)';
                        }
                        $success = true;
                    } else {
                        $message = '❌ Certificate could not be issued. Please make sure certificate templates exist.';
                    }
                } else {
                    // Get the first available template
                    $template = reset($available_templates);
                    $template_name = $template->name;
                    
                    $result = \local_point_badges\certificate_manager::issue_custom_certificate($USER->id, $template_name);
                    
                    if ($result) {
                        \local_point_badges\manager::deduct_xp($USER->id, 0, $final_cost, 'purchased_certificate');
                        $message = '🏅 Certificate issued successfully! Check "Your Certificates" section below.';
                        if ($discount_info['applied']) {
                            $message .= ' (Discount applied: saved ' . number_format($discount_info['saved']) . ' XP)';
                        }
                        $success = true;
                    } else {
                        $message = '❌ Certificate could not be issued. Please make sure certificate templates exist.';
                    }
                }
            } else {
                $message = '❌ Insufficient XP! You need ' . number_format($final_cost) . ' XP but have ' . number_format($user_xp) . ' XP.';
                if ($discount_info['applied']) {
                    $message .= ' Original price was ' . number_format($cost) . ' XP, but you have a ' . $discount_info['percent'] . '% discount.';
                }
            }
            break;
            
        case 'extra_attempt':
            $cost = get_config('local_point_badges', 'extra_attempt_cost') ?: 1;
            
            // Apply discount if available
            $discount_info = \local_point_badges\coupon_redemption::apply_discount($USER->id, $cost);
            $final_cost = $discount_info['discounted'];
            
            if (!$quizid) {
                $message = '❌ Please select a quiz first.';
            } elseif ($user_xp < $final_cost) {
                $message = '❌ Insufficient XP! You need ' . number_format($final_cost) . ' XP but only have ' . number_format($user_xp) . ' XP.';
                if ($discount_info['applied']) {
                    $message .= ' Original price was ' . number_format($cost) . ' XP, but you have a ' . $discount_info['percent'] . '% discount.';
                }
            } else {
                $result = \local_point_badges\quiz_manager::grant_extra_attempt($USER->id, $quizid, $final_cost);
                if ($result['success']) {
                    $success = true;
                    $message = '📝 Extra quiz attempt granted successfully!';
                    if ($discount_info['applied']) {
                        $message .= ' (Discount applied: saved ' . number_format($discount_info['saved']) . ' XP)';
                    }
                } else {
                    $message = '❌ ' . $result['message'];
                }
            }
            break;
            
        case 'coupon':
            $available_coupon_types = \local_point_badges\coupon_manager::get_available_coupon_types();
            
            if (empty($coupon_type) || !isset($available_coupon_types[$coupon_type])) {
                $message = '❌ Please select a valid coupon type.';
            } else {
                $cost = $available_coupon_types[$coupon_type]['cost'];
                
                // Check if user already has these unlocks (for direct types)
                if ($coupon_type == 'premium_course' && \local_point_badges\coupon_manager::has_premium_unlocked($USER->id)) {
                    $message = '❌ You already have Premium Content unlocked!';
                } else if ($coupon_type == 'special_access' && \local_point_badges\coupon_manager::has_vip_access($USER->id)) {
                    $message = '❌ You already have Special VIP Access!';
                } else {
                    // Apply discount if available
                    $discount_info = \local_point_badges\coupon_redemption::apply_discount($USER->id, $cost);
                    $final_cost = $discount_info['discounted'];
                    
                    if ($user_xp < $final_cost) {
                        $message = '❌ Insufficient XP! You need ' . number_format($final_cost) . ' XP but only have ' . number_format($user_xp) . ' XP.';
                        if ($discount_info['applied']) {
                            $message .= ' Original price was ' . number_format($cost) . ' XP, but you have a ' . $discount_info['percent'] . '% discount.';
                        }
                    } else {
                        // For VIP and Premium, we unlock DIRECTLY instead of just generating a coupon
                        if ($coupon_type == 'premium_course' || $coupon_type == 'special_access') {
                            \local_point_badges\manager::deduct_xp($USER->id, 0, $final_cost, 'purchased_' . $coupon_type);
                            if ($coupon_type == 'premium_course') {
                                $result_msg = \local_point_badges\coupon_manager::unlock_premium_activities($USER->id);
                            } else {
                                $result_msg = \local_point_badges\coupon_manager::grant_vip_special_access($USER->id);
                            }
                            $success = true;
                            $message = '🎉 Success! ' . $result_msg;
                        } else {
                            // Standard coupon generation for other types
                            $coupon = \local_point_badges\coupon_manager::generate_coupon($USER->id, $coupon_type, $final_cost, 0);
                            if ($coupon['success']) {
                                $success = true;
                                $message = '🎟️ Coupon generated! Your code: ' . $coupon['code'] . ' (expires ' . $coupon['expires'] . ')';
                            } else {
                                $message = '❌ ' . $coupon['message'];
                            }
                        }
                        
                        if ($success && $discount_info['applied']) {
                            $message .= ' (Discount applied: saved ' . number_format($discount_info['saved']) . ' XP)';
                        }
                    }
                }
            }
            break;
            
        default:
            $message = '❌ Invalid item selected.';
    }
    
    // Store message in session to show after redirect
    if ($success) {
        \core\notification::success($message);
    } else {
        \core\notification::error($message);
    }
    
    // Redirect to refresh the page completely
    redirect($PAGE->url);
    exit;
}

echo $OUTPUT->header();

$cert_cost = get_config('local_point_badges', 'certificate_cost') ?: 500;
$extra_attempt_cost = get_config('local_point_badges', 'extra_attempt_cost') ?: 100;
$coupon_types = \local_point_badges\coupon_manager::get_available_coupon_types();

// Get fresh data after potential purchase (post-redirect)
$certificates = \local_point_badges\certificate_manager::get_user_certificates($USER->id);
$extra_attempts_list = \local_point_badges\quiz_manager::get_user_all_extra_attempts($USER->id);
$usercoupons = \local_point_badges\coupon_manager::get_user_coupons($USER->id);

// Refresh XP info after potential purchase
$level_info = \local_point_badges\manager::get_user_level_info($USER->id);
$user_xp = $level_info['total_xp'];

// Check if user has an active discount
$discount_info = \local_point_badges\coupon_redemption::get_active_discount($USER->id);
$has_active_discount = $discount_info['has_discount'];
$active_discount = $discount_info['has_discount'] ? $discount_info['percent'] : 0;
$discount_expiry = $discount_info['has_discount'] ? $discount_info['expiry'] : 0;

?>

<style>
.shop-container {
    max-width: 1200px;
    margin: 0 auto;
}
.xp-balance {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 16px;
    margin-bottom: 30px;
    text-align: center;
}
.xp-balance h3 {
    margin: 0 0 10px 0;
}
.xp-amount {
    font-size: 2.5rem;
    font-weight: bold;
}
.shop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}
.shop-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
    text-align: center;
}
.shop-card:hover {
    transform: translateY(-5px);
}
.shop-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}
.shop-title {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 10px;
}
.shop-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
}
.shop-price {
    font-size: 1.5rem;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 20px;
}
.shop-price small {
    font-size: 0.8rem;
    color: #999;
}
.shop-price-original {
    text-decoration: line-through;
    color: #999;
    font-size: 0.9rem;
    margin-right: 8px;
}
.shop-price-discount {
    display: inline-block;
    background: #4caf50;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
    vertical-align: middle;
}
.btn-purchase {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
}
.btn-purchase:hover {
    opacity: 0.9;
    color: white;
    text-decoration: none;
}
.progress {
    height: 10px;
    background: rgba(255,255,255,0.3);
    border-radius: 5px;
    overflow: hidden;
    margin-top: 15px;
}
.progress-bar {
    background: #ffd700;
    height: 100%;
    border-radius: 5px;
}
.xp-needed {
    font-size: 0.8rem;
    margin-top: 8px;
    opacity: 0.8;
}
.coupon-select {
    margin: 15px 0;
    padding: 8px;
    border-radius: 8px;
    border: 1px solid #ddd;
    width: 100%;
}
.btn-download {
    display: inline-block;
    padding: 6px 15px;
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    color: white;
    text-decoration: none;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}
.btn-download:hover {
    background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
}
.certificate-code {
    font-family: monospace;
    font-size: 11px;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 4px;
}
.section-title {
    font-size: 1.5rem;
    margin-top: 30px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
    color: #333;
}
.empty-state {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 12px;
    color: #666;
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}
.generaltable {
    width: 100%;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.generaltable th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}
.generaltable td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}
.generaltable tr:hover {
    background: #fafafa;
}
.coupon-redemption-box {
    background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%);
    border: 2px solid #ffd700;
    border-radius: 16px;
    padding: 20px;
    margin: 20px 0;
}
.coupon-redemption-box h3 {
    margin-top: 0;
    color: #333;
}
.discount-banner {
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    color: white;
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}
</style>

<div class="shop-container">
    
    <!-- XP Balance Card -->
    <div class="xp-balance">
        <h3>💰 Your XP Balance</h3>
        <div class="xp-amount"><?php echo number_format($user_xp); ?> XP</div>
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $level_info['progress_percent']; ?>%;"></div>
        </div>
        <div class="xp-needed">
            <?php echo $level_info['progress_percent']; ?>% to next level 
            (<?php echo $level_info['xp_needed_next_level']; ?> XP needed)
        </div>
    </div>
    
    <!-- Active Discount Banner -->
    <?php if ($has_active_discount): ?>
    <div class="discount-banner">
        🎫 You have an active <?php echo $active_discount; ?>% discount on your next purchase! 
        Expires: <?php echo userdate($discount_expiry, get_string('strftimedate')); ?>
    </div>
    <?php endif; ?>
    
    <!-- Coupon Redemption Form -->
    <?php echo \local_point_badges\coupon_redemption::render_redemption_form(); ?>
    
    <h2 class="section-title">🎁 Available Rewards
        <?php if (has_capability('moodle/site:config', context_system::instance())): ?>
            <a href="manage_restrictions.php" class="btn btn-sm btn-outline-primary float-right" style="font-size: 14px; border-radius: 20px;">
                ⚙️ Manage Premium Content
            </a>
        <?php endif; ?>
    </h2>
    <p>Spend your hard-earned XP on these awesome rewards!</p>
    
    <div class="shop-grid">
        
        <?php 
        $cert_cost = get_config('local_point_badges', 'certificate_cost') ?: 1; 
        $has_cert = $DB->record_exists('local_pb_certificates', ['userid' => $USER->id, 'certificate_name' => 'Achievement Certificate']);
        ?>
        <div class="shop-card">
            <div class="shop-icon">🏅</div>
            <div class="shop-title">Achievement Certificate</div>
            <div class="shop-description">Official certificate recognizing your achievement. Printable and shareable!</div>
            <?php if (!$has_cert): ?>
            <div class="shop-price">
                <?php echo $cert_cost; ?> <small>XP</small>
            </div>
            <a href="?action=buy&item=certificate&sesskey=<?php echo sesskey(); ?>" class="btn-purchase">Purchase</a>
            <?php else: ?>
            <div class="shop-price" style="color: #3498db; font-size: 1.2rem;">✅ Issued</div>
            <div style="margin-top: 10px;">
                <span class="badge badge-info" style="padding: 8px 15px; border-radius: 20px; background-color: #3498db; color: white;">Owned & Verified</span>
            </div>
            <?php endif; ?>
        </div>
        
            <?php
            // Check if user already owns unexpired extra attempts
            $all_extras = \local_point_badges\quiz_manager::get_user_all_extra_attempts($USER->id);
            $has_unexpired_extras = false;
            foreach ($all_extras as $extra) {
                $expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('extra_attempt_expiry_' . $extra->quizid, 0, $USER->id);
                if ($expiry == 0 || $expiry > time()) {
                    $has_unexpired_extras = true;
                    break;
                }
            }
            
            // Global 7-day cooldown check
            $last_purchase = (int)\local_point_badges\coupon_redemption::get_user_preference('last_extra_attempt_purchase', 0, $USER->id);
            $next_purchase_time = $last_purchase + (7 * 24 * 60 * 60);
            $cooldown_active = (time() < $next_purchase_time);
            
            $is_locked = ($has_unexpired_extras || $cooldown_active);
            ?>
            <div class="shop-card">
                <div class="shop-icon">📝</div>
                <div class="shop-title">Extra Quiz Attempt</div>
                <div class="shop-description">Get one extra attempt on any quiz. Perfect for improving your score! (7 Days)</div>
                
                <?php if (!$is_locked): ?>
                <div class="shop-price">
                    1 <small>XP</small>
                </div>
                
                <?php
                // Get available quizzes for extra attempts
                $quizzes = $DB->get_records_sql("
                    SELECT q.id, q.name, c.id as courseid, c.fullname as coursename
                    FROM {quiz} q
                    JOIN {course} c ON c.id = q.course
                    WHERE c.visible = 1
                    LIMIT 10
                ");
                ?>
                
                <form method="post" action="" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="item" value="extra_attempt">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <select name="quizid" class="coupon-select" required>
                        <option value="">Select a quiz...</option>
                        <?php foreach ($quizzes as $quiz): ?>
                            <option value="<?php echo $quiz->id; ?>">
                                <?php echo format_string($quiz->name); ?> 
                                (<?php echo format_string($quiz->coursename); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-purchase" style="width: 100%; margin-top: 10px; background: #e74c3c;">
                        Purchase Attempt
                    </button>
                </form>
                <?php else: ?>
                <div class="shop-price" style="color: #e74c3c; font-size: 1.1rem;">
                    <?php echo $has_unexpired_extras ? '✅ Already Owned' : '⏳ Cooldown Active'; ?>
                </div>
                <div style="margin-top: 10px;">
                    <span class="badge" style="padding: 8px 15px; border-radius: 20px; background-color: #e74c3c; color: white;">
                        <?php 
                        if ($cooldown_active) {
                            $diff = $next_purchase_time - time();
                            $days = ceil($diff / (24 * 60 * 60));
                            echo "Next Purchase in " . $days . " Days";
                        } else {
                            echo "Expires in 7 Days";
                        }
                        ?>
                    </span>
                    <?php if ($cooldown_active): ?>
                        <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                            Available: <?php echo date('Y-m-d H:i', $next_purchase_time); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 10px; font-size: 0.8rem; color: #666;">
                    <?php echo $has_unexpired_extras ? 'You have unused extra attempts. Use them before purchasing more!' : 'To prevent abuse, you can only purchase one extra attempt every 7 days.'; ?>
                </div>
                <?php endif; ?>
            </div>
        
        <!-- Premium Content Card -->
        <?php 
        $premium_cost = isset($coupon_types['premium_course']['cost']) ? $coupon_types['premium_course']['cost'] : 1000; 
        $has_premium = \local_point_badges\coupon_manager::has_premium_unlocked($USER->id);
        $premium_count = $DB->count_records('local_pb_premium_restrictions', ['is_premium' => 1]);
        ?>
        <div class="shop-card">
            <div class="shop-icon">⭐</div>
            <div class="shop-title"><?php echo $coupon_types['premium_course']['name']; ?></div>
            <div class="shop-description">
                <?php echo $coupon_types['premium_course']['description']; ?><br>
                <small style="color: #666;">(Currently: <strong><?php echo $premium_count; ?></strong> activities)</small>
            </div>
            <?php if (!$has_premium): ?>
            <div class="shop-price">
                <?php if ($has_active_discount): ?>
                    <span class="shop-price-original"><?php echo $premium_cost; ?> XP</span>
                    <?php $discounted_premium = round($premium_cost * (1 - $active_discount / 100)); ?>
                    <span><?php echo $discounted_premium; ?> XP</span>
                    <span class="shop-price-discount">-<?php echo $active_discount; ?>%</span>
                <?php else: ?>
                    <?php echo $premium_cost; ?> <small>XP</small>
                <?php endif; ?>
            </div>
            <form method="post" action="" style="margin-top: 15px;">
                <input type="hidden" name="action" value="buy">
                <input type="hidden" name="item" value="coupon">
                <input type="hidden" name="coupon_type" value="premium_course">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <button type="submit" class="btn-purchase" style="background: linear-gradient(135deg, #ff9800, #f57c00);">
                    Unlock Now
                </button>
            </form>
            <?php else: ?>
            <div class="shop-price" style="color: #4caf50; font-size: 1.2rem;">✅ Unlocked</div>
            <div style="margin-top: 10px;">
                <span class="badge badge-success" style="padding: 8px 15px; border-radius: 20px; background-color: #4caf50; color: white;">Owned & Active</span>
                <?php 
                $premium_expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('premium_expiry', 0, $USER->id);
                if ($premium_expiry): ?>
                    <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                        Expires: <?php echo date('Y-m-d H:i', $premium_expiry); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Special Access Card -->
        <?php 
        $special_cost = isset($coupon_types['special_access']['cost']) ? $coupon_types['special_access']['cost'] : 800; 
        $has_vip = \local_point_badges\coupon_manager::has_vip_access($USER->id);
        $vip_count = $DB->count_records('local_pb_vip_restrictions', ['is_vip_only' => 1]);
        ?>
        <div class="shop-card">
            <div class="shop-icon">🗝️</div>
            <div class="shop-title"><?php echo $coupon_types['special_access']['name']; ?></div>
            <div class="shop-description">
                <?php echo $coupon_types['special_access']['description']; ?><br>
                <small style="color: #666;">(Currently: <strong><?php echo $vip_count; ?></strong> VIP sections)</small>
            </div>
            <?php if (!$has_vip): ?>
            <div class="shop-price">
                <?php if ($has_active_discount): ?>
                    <span class="shop-price-original"><?php echo $special_cost; ?> XP</span>
                    <?php $discounted_special = round($special_cost * (1 - $active_discount / 100)); ?>
                    <span><?php echo $discounted_special; ?> XP</span>
                    <span class="shop-price-discount">-<?php echo $active_discount; ?>%</span>
                <?php else: ?>
                    <?php echo $special_cost; ?> <small>XP</small>
                <?php endif; ?>
            </div>
            <form method="post" action="" style="margin-top: 15px;">
                <input type="hidden" name="action" value="buy">
                <input type="hidden" name="item" value="coupon">
                <input type="hidden" name="coupon_type" value="special_access">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <button type="submit" class="btn-purchase" style="background: linear-gradient(135deg, #9c27b0, #7b1fa2);">
                    Unlock Now
                </button>
            </form>
            <?php else: ?>
            <div class="shop-price" style="color: #9c27b0; font-size: 1.2rem;">🌟 VIP Status</div>
            <div style="margin-top: 10px;">
                <span class="badge badge-primary" style="padding: 8px 15px; border-radius: 20px; background-color: #9c27b0; color: white;">👑 Member</span>
                <?php 
                $vip_expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('vip_expiry', 0, $USER->id);
                if ($vip_expiry): ?>
                    <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                        Expires: <?php echo date('Y-m-d H:i', $vip_expiry); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Other Coupon Types (Discount, XP Boost) -->
        <?php foreach ($coupon_types as $key => $type): ?>
            <?php if ($key != 'premium_course' && $key != 'special_access' && $key != 'extra_attempts'): ?>
            <?php 
            $type_cost = 1; // Default to 1 XP as requested
            $has_unused = $DB->record_exists('local_pb_coupons', ['userid' => $USER->id, 'type' => $key, 'used' => 0]);
            ?>
            <div class="shop-card">
                <div class="shop-icon">🎟️</div>
                <div class="shop-title"><?php echo $type['name']; ?></div>
                <div class="shop-description"><?php echo $type['description']; ?></div>
                
                <?php if (!$has_unused): ?>
                <div class="shop-price">
                    <?php echo $type_cost; ?> <small>XP</small>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="item" value="coupon">
                    <input type="hidden" name="coupon_type" value="<?php echo $key; ?>">
                    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                    <button type="submit" class="btn-purchase">Generate Coupon</button>
                </form>
                <?php else: ?>
                <div class="shop-price" style="color: #e67e22; font-size: 1.1rem;">✅ Available</div>
                <div style="margin-top: 10px;">
                    <span class="badge" style="padding: 8px 15px; border-radius: 20px; background-color: #e67e22; color: white;">Owned & Unused</span>
                    <?php 
                    $coupon_rec = $DB->get_record('local_pb_coupons', ['userid' => $USER->id, 'type' => $key, 'used' => 0], 'expires_at', IGNORE_MULTIPLE);
                    if ($coupon_rec): ?>
                        <div style="margin-top: 5px; font-size: 0.8rem; color: #666;">
                            Expires: <?php echo date('Y-m-d H:i', $coupon_rec->expires_at); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
    </div>
    
    <!-- Your Certificates Section -->
    <h2 class="section-title">📜 Your Certificates (<?php echo count($certificates); ?>)</h2>

    <?php if (!empty($certificates)): ?>
        <table class="generaltable">
            <thead>
                <tr>
                    <th>Certificate Name</th>
                    <th>Code</th>
                    <th>Issued Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $cert): ?>
                    <?php
                    $viewurl = \local_point_badges\certificate_manager::get_view_url($cert);
                    $viewurl_str = $viewurl ? $viewurl->out() : '';
                    ?>
                    <tr id="cert-row-<?php echo $cert->id; ?>">
                        <td style="padding: 12px;">
                            <strong><?php echo s($cert->certificate_name); ?></strong>
                        </td>
                        <td style="padding: 12px;">
                            <code class="certificate-code"><?php echo s($cert->certificate_code); ?></code>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo userdate($cert->issued_date, get_string('strftimedate')); ?>
                        </td>
                        <td style="padding: 12px;">
                            <a href="<?php echo $viewurl->out(false); ?>"
                               class="btn-download">
                                📥 <?php echo get_string('viewcertificate', 'local_point_badges'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📜</div>
            <p>You haven't purchased any certificates yet.</p>
            <p>Purchase your first certificate from the shop above!</p>
        </div>
    <?php endif; ?>
    
    <!-- Your Coupons Section -->
    <?php if (!empty($usercoupons)): ?>
        <h2 class="section-title">🎫 Your Active Coupons</h2>
        <table class="generaltable">
            <thead>
                <tr>
                    <th>Coupon Code</th>
                    <th>Type</th>
                    <th>Expires</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usercoupons as $coupon): ?>
                    <tr>
                        <td><code><?php echo s($coupon->code); ?></code></td>
                        <td><?php echo ucfirst(s($coupon->type)); ?></td>
                        <td><?php echo userdate($coupon->expires_at, get_string('strftimedate')); ?></td>
                        <td><span style="color: #4caf50;">✓ Active</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Your Extra Quiz Attempts Section -->
    <?php if (!empty($extra_attempts_list)): ?>
        <h2 class="section-title">🎯 Your Extra Quiz Attempts</h2>
        <table class="generaltable">
            <thead>
                <tr>
                    <th>Quiz Name</th>
                    <th>Course</th>
                    <th>Available Attempts</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extra_attempts_list as $attempt): ?>
                    <?php
                    $cm = get_coursemodule_from_instance('quiz', $attempt->quizid, $attempt->course);
                    $quiz = $DB->get_record('quiz', ['id' => $attempt->quizid]);
                    $default_attempts = $quiz ? $quiz->attempts : 1;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo format_string($attempt->quiz_name); ?></strong><br>
                            <small style="color: #666;">Default attempts: <?php echo $default_attempts; ?></small>
                        </td>
                        <td><?php echo format_string($attempt->course_name); ?></td>
                        <td>
                            <span style="background: #4caf50; color: white; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold;">
                                🎯 <?php echo $attempt->remaining; ?> left
                            </span>
                        </td>
                        <td>
                            <?php if ($cm): ?>
                                <a href="use_extra_attempt.php?quizid=<?php echo $attempt->quizid; ?>" 
                                   class="btn-purchase"
                                   style="background: linear-gradient(135deg, #ff9800, #f57c00); padding: 6px 18px; font-size: 12px;">
                                    🚀 Use Extra Attempt
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top: 10px; font-size: 12px; color: #666; padding: 10px; background: #f8f9fa; border-radius: 8px;">
            💡 <strong>How it works:</strong> Use your default attempt(s) first. Extra attempts will automatically be used after that.
        </div>
    <?php endif; ?>
    
</div>

<?php
echo $OUTPUT->footer();
?>