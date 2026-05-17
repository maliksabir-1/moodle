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

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block', true) == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = false;

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

function get_user_initials() {
    global $USER;
    if (!isloggedin() || isguestuser()) { return '?'; }
    $firstname = $USER->firstname ?? '';
    $lastname = $USER->lastname ?? '';
    $initials = '';
    if (!empty($firstname)) { $initials .= strtoupper(mb_substr($firstname, 0, 1)); }
    if (!empty($lastname)) { $initials .= strtoupper(mb_substr($lastname, 0, 1)); }
    if (empty($initials)) { $username = $USER->username ?? ''; $initials = strtoupper(mb_substr($username, 0, 2)); }
    return $initials;
}

$user_initials = get_user_initials();

// Get logo URL
$logo_url = false;
if (method_exists($OUTPUT, 'get_logo_url')) {
    $logo_url = $OUTPUT->get_logo_url();
}

$templatecontext = [
     'navhome' => get_config('theme_mytheme', 'navhome') ?: 'Home',
    'navcourses' => get_config('theme_mytheme', 'navcourses') ?: 'Courses',
    'navpages' => get_config('theme_mytheme', 'navpages') ?: 'Pages',
    'navdashboard' => get_config('theme_mytheme', 'navdashboard') ?: 'Dashboard',
    'navcategories' => get_config('theme_mytheme', 'navcategories') ?: 'Categories',
    'navsearch' => get_config('theme_mytheme', 'navsearch') ?: 'Search courses...',
    'navtryfree' => get_config('theme_mytheme', 'navtryfree') ?: 'Try For Free',
    'navlogin' => get_config('theme_mytheme', 'navlogin') ?: 'Log in',
    'logo_url' => $logo_url,
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'isadmin' => is_siteadmin(),
    'isloggedin' => isloggedin() && !isguestuser(),
    'user_initials' => $user_initials,
    'edit_mode_button' => $OUTPUT->edit_switch(),
    'edit_mode_button_mobile' => $OUTPUT->edit_switch(),
    'user_can_edit' => $PAGE->user_allowed_editing(),
    'edit_mode_on' => $PAGE->user_is_editing(),
    'navbar_categories' => theme_mytheme_get_categories(),
    
    // NAVBAR TEXT SETTINGS
    'navhome' => get_config('theme_mytheme', 'navhome') ?: 'Home',
    'navcourses' => get_config('theme_mytheme', 'navcourses') ?: 'Courses',
    'navpages' => get_config('theme_mytheme', 'navpages') ?: 'Pages',
    'navdashboard' => get_config('theme_mytheme', 'navdashboard') ?: 'Dashboard',
    'navcategories' => get_config('theme_mytheme', 'navcategories') ?: 'Categories',
    'navsearch' => get_config('theme_mytheme', 'navsearch') ?: 'Search courses...',
    'navtryfree' => get_config('theme_mytheme', 'navtryfree') ?: 'Try For Free',
    'navlogin' => get_config('theme_mytheme', 'navlogin') ?: 'Log in',
    'logo_url' => $logo_url,
    
    // FOOTER SETTINGS
    'footer_sitename' => format_string($SITE->fullname, true),
    'footer_shortname' => format_string($SITE->shortname, true),
    'footer_email' => get_config('theme_mytheme', 'footeremail') ?: (!empty($CFG->supportemail) ? $CFG->supportemail : 'info@example.com'),
    'footer_phone' => get_config('theme_mytheme', 'footerphone') ?: (!empty($CFG->supportphone) ? $CFG->supportphone : '+123 88 9900 456'),
    'footer_address' => get_config('theme_mytheme', 'footeraddress') ?: (!empty($CFG->supportaddress) ? $CFG->supportaddress : '201 S. Grand Ave., 1st Floor'),
    'footer_city' => get_config('theme_mytheme', 'footercity') ?: 'New York City, NY 28020',
    'footer_copyright' => get_config('theme_mytheme', 'footercopyright') ?: ('© ' . date('Y') . ' ' . format_string($SITE->fullname, true) . '. All rights reserved.'),
    'footer_useful' => get_config('theme_mytheme', 'footeruseful') ?: 'Useful Links',
    'footer_company' => get_config('theme_mytheme', 'footercompany') ?: 'Our Company',
    'footer_newsletter' => get_config('theme_mytheme', 'footernewsletter') ?: 'Newsletter SignUp!',
    'footer_newsletterdesc' => get_config('theme_mytheme', 'footernewsletterdesc') ?: 'Get the latest news delivered to your inbox',
    'footer_terms' => get_config('theme_mytheme', 'footerterms') ?: 'Terms',
    'footer_privacy' => get_config('theme_mytheme', 'footerprivacy') ?: 'Privacy',
    
    // SOCIAL MEDIA
    'facebook_url' => get_config('theme_mytheme', 'facebook') ?: '#',
    'instagram_url' => get_config('theme_mytheme', 'instagram') ?: '#',
    'linkedin_url' => get_config('theme_mytheme', 'linkedin') ?: '#',
    'pinterest_url' => get_config('theme_mytheme', 'pinterest') ?: '#',
    'twitter_url' => get_config('theme_mytheme', 'twitter') ?: '#',
    'youtube_url' => get_config('theme_mytheme', 'youtube') ?: '#',


     'navhome' => get_config('theme_mytheme', 'navhome') ?: 'Home',
    'navcourses' => get_config('theme_mytheme', 'navcourses') ?: 'Courses',
    'navpages' => get_config('theme_mytheme', 'navpages') ?: 'Pages',
    'navdashboard' => get_config('theme_mytheme', 'navdashboard') ?: 'Dashboard',
    'navcategories' => get_config('theme_mytheme', 'navcategories') ?: 'Categories',
    'navsearch' => get_config('theme_mytheme', 'navsearch') ?: 'Search courses...',
    'navtryfree' => get_config('theme_mytheme', 'navtryfree') ?: 'Try For Free',
    'navlogin' => get_config('theme_mytheme', 'navlogin') ?: 'Log in',
];

echo $OUTPUT->render_from_template('theme_mytheme/drawers', $templatecontext);