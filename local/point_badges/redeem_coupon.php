<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$coupon_code = required_param('coupon_code', PARAM_ALPHANUMEXT);
$sesskey = required_param('sesskey', PARAM_ALPHA);

require_sesskey();

$result = \local_point_badges\coupon_redemption::process_redemption($coupon_code, $USER->id);

if ($result['success']) {
    \core\notification::success($result['message']);
} else {
    \core\notification::error($result['message']);
}

redirect(new moodle_url('/local/point_badges/shop.php'));