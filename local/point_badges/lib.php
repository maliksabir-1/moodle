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


/**
 * Serve the files from the local_point_badges file areas
 */
function local_point_badges_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;
    
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    
    require_login();
    
    if ($filearea === 'badges') {
        $itemid = array_shift($args);
        $filename = array_pop($args);
        
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'local_point_badges', $filearea, $itemid, '/', $filename);
        
        if (!$file) {
            return false;
        }
        
        send_stored_file($file, null, 0, $forcedownload, $options);
    }
    
    return false;
}

/**
 * Extend the user navigation to add point badges links
 */
function local_point_badges_extend_navigation_user_settings($navigation, $user) {
    if (has_capability('local/point_badges:viewleaderboard', \context_user::instance($user->id))) {
        $node = $navigation->add(get_string('pluginname', 'local_point_badges'));
        $node->add(get_string('leaderboard', 'local_point_badges'), 
                   new \moodle_url('/local/point_badges/leaderboard.php'));
        $node->add(get_string('mystats', 'local_point_badges'), 
                   new \moodle_url('/local/point_badges/mystats.php'));
    }
}

/**
 * Block direct access to premium/VIP activities when the user has not unlocked them.
 */
function local_point_badges_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect) {
    global $USER;

    if (!$cm || is_siteadmin()) {
        return;
    }

    $context = \context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if (!\local_point_badges\access_check::can_access_activity($USER->id, $cm->id)) {
        $url = new \moodle_url('/local/point_badges/shop.php');
        redirect(
            $url,
            \local_point_badges\access_check::get_restriction_message($cm->id),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

/**
 * Check if a course module is marked as premium
 */
function local_point_badges_is_premium_activity($cmid) {
    global $DB;
    return $DB->record_exists('local_pb_premium_restrictions', ['cmid' => $cmid, 'is_premium' => 1]);
}

/**
 * Check if a course module is marked as VIP only
 */
function local_point_badges_is_vip_activity($cmid) {
    global $DB;
    return $DB->record_exists('local_pb_vip_restrictions', ['cmid' => $cmid, 'is_vip_only' => 1]);
}

/**
 * Hook handler for CSS
 */
class local_point_badges_hook_handler {
    public static function before_http_headers(\core\hook\output\before_http_headers $hook) {
        global $PAGE;
        if (!empty($PAGE)) {
            $PAGE->requires->css(new \moodle_url('/local/point_badges/styles.css'));
        }
    }

    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ) {
        global $PAGE;
        if (!empty($PAGE) && $PAGE->context && $PAGE->context->contextlevel == CONTEXT_COURSE) {
            $PAGE->requires->js_call_amd('local_point_badges/restrictions', 'init');
        }
    }
}