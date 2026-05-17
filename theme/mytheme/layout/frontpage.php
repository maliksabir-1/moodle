<?php
defined('MOODLE_INTERNAL') || die();

global $CFG, $DB, $OUTPUT, $SITE, $PAGE;

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Get current user's initials (First letter of first name + First letter of last name)
 * @return string User initials (e.g., "JD" for John Doe)
 */
$logo_url = false;
if (method_exists($OUTPUT, 'get_logo_url')) {
    $logo_url = $OUTPUT->get_logo_url();
}
function get_user_initials() {
    global $USER;
    
    if (!isloggedin() || isguestuser()) {
        return '?';
    }
    
    $firstname = $USER->firstname ?? '';
    $lastname = $USER->lastname ?? '';
    $initials = '';
    
    if (!empty($firstname)) {
        $initials .= strtoupper(mb_substr($firstname, 0, 1));
    }
    
    if (!empty($lastname)) {
        $initials .= strtoupper(mb_substr($lastname, 0, 1));
    }
    
    if (empty($initials)) {
        $username = $USER->username ?? '';
        $initials = strtoupper(mb_substr($username, 0, 2));
    }
    
    return $initials;
}

// Get user initials
$user_initials = get_user_initials();

// Get settings with fallbacks
$herotagline = get_config('theme_mytheme', 'herotagline') ?: 'PROFESSIONAL COURSES';
$herotitle = get_config('theme_mytheme', 'herotitle') ?: 'Find Business Courses & Develop Your Skills';
$herodescription = get_config('theme_mytheme', 'herodescription') ?: "Free & Premium online courses from the world's best instructors. Join 17 million learners today.";
$abouttitle = get_config('theme_mytheme', 'abouttitle') ?: 'Professional Courses Taught By Industry Leaders';
$aboutdescription = get_config('theme_mytheme', 'aboutdescription') ?: "Groove's intuitive shared inbox makes it easy for team members to organize, prioritize and in this episode.";
$footercopyright = get_config('theme_mytheme', 'footercopyright') ?: '© ' . date('Y') . ' ' . format_string($SITE->fullname, true) . '. All rights reserved.';
$footeremail = get_config('theme_mytheme', 'footeremail') ?: (!empty($CFG->supportemail) ? $CFG->supportemail : 'info@example.com');
$footerphone = get_config('theme_mytheme', 'footerphone') ?: (!empty($CFG->supportphone) ? $CFG->supportphone : '+123 88 9900 456');
$footeraddress = get_config('theme_mytheme', 'footeraddress') ?: (!empty($CFG->supportaddress) ? $CFG->supportaddress : '201 S. Grand Ave., 1st Floor');
$footercity = get_config('theme_mytheme', 'footercity') ?: 'New York City, NY 28020';

// Stats settings
$stat1label = get_config('theme_mytheme', 'stat1label') ?: 'Learn Skills With';
$stat2label = get_config('theme_mytheme', 'stat2label') ?: 'Choose Courses';
$stat3label = get_config('theme_mytheme', 'stat3label') ?: 'Professional Tutors';
$stat4label = get_config('theme_mytheme', 'stat4label') ?: 'Online Degrees';

// Course section text settings
$coursesbutton = get_config('theme_mytheme', 'coursesbutton') ?: 'Discover All Courses';
$courseratingtext = get_config('theme_mytheme', 'courseratingtext') ?: 'Reviews';
$courselessonstext = get_config('theme_mytheme', 'courselessonstext') ?: 'Lessons';
$coursestudentstext = get_config('theme_mytheme', 'coursestudentstext') ?: 'Students';

// Get image URLs
$heroimage = false;
$aboutimage = false;

// Try to get uploaded hero image
if (method_exists($OUTPUT, 'get_hero_image_url')) {
    $heroimage = $OUTPUT->get_hero_image_url();
}
if (!$heroimage) {
    $heroimage = $CFG->wwwroot . '/theme/mytheme/pix/hero-image.jpg';
}

// Try to get uploaded about image
if (method_exists($OUTPUT, 'get_about_image_url')) {
    $aboutimage = $OUTPUT->get_about_image_url();
}
if (!$aboutimage) {
    $aboutimage = $CFG->wwwroot . '/theme/mytheme/pix/about-us.png';
}

// CTA Image
$ctaimage = false;
if (method_exists($OUTPUT, 'get_cta_image_url')) {
    $ctaimage = $OUTPUT->get_cta_image_url();
}

// Testimonial Image
$testimonialimage = false;
if (method_exists($OUTPUT, 'get_testimonial_image_url')) {
    $testimonialimage = $OUTPUT->get_testimonial_image_url();
}

// Categories BG Image
$categoriesbg = false;
if (method_exists($OUTPUT, 'get_categories_bg_image_url')) {
    $categoriesbg = $OUTPUT->get_categories_bg_image_url();
}

// Brand Logos
$brands = [];
for ($i = 1; $i <= 6; $i++) {
    $brandurl = false;
    if (method_exists($OUTPUT, 'get_brand_logo_url')) {
        $brandurl = $OUTPUT->get_brand_logo_url($i);
    }
    $brands['brand' . $i] = $brandurl;
}

// Build template context (ALL settings included)
$templatecontext = [
    'navhome' => get_config('theme_mytheme', 'navhome') ?: 'Home',
    'navcourses' => get_config('theme_mytheme', 'navcourses') ?: 'Courses',
    'navpages' => get_config('theme_mytheme', 'navpages') ?: 'Pages',
    'navdashboard' => get_config('theme_mytheme', 'navdashboard') ?: 'Dashboard',
    'navcategories' => get_config('theme_mytheme', 'navcategories') ?: 'Categories',
    'navsearch' => get_config('theme_mytheme', 'navsearch') ?: 'Search courses...',
    'navtryfree' => get_config('theme_mytheme', 'navtryfree') ?: 'Try For Free',
    'navlogin' => get_config('theme_mytheme', 'navlogin') ?: 'Log in',
    'logo_url' => $logo_url ?? false,
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'config' => $CFG,
    'isadmin' => is_siteadmin(),
    'isloggedin' => isloggedin() && !isguestuser(),
    'popularcourses' => get_popular_courses(),
    'total_users' => get_total_users(),
    'total_courses' => get_total_courses(),
    'total_tutors' => get_total_tutors(),
    'total_degrees' => get_total_degrees(),
    'categories' => get_categories_with_course_counts(),
    'instructors' => get_instructors(),
    'blogposts' => get_blog_posts(),
    'user_initials' => $user_initials,
    'edit_mode_button' => $OUTPUT->edit_switch(),
    'edit_mode_button_mobile' => $OUTPUT->edit_switch(),
    'user_can_edit' => $PAGE->user_allowed_editing(),
    'edit_mode_on' => $PAGE->user_is_editing(),
    
    // Footer settings from admin
    'footer_sitename' => format_string($SITE->fullname, true),
    'footer_shortname' => format_string($SITE->shortname, true),
    'footer_email' => $footeremail,
    'footer_phone' => $footerphone,
    'footer_address' => $footeraddress,
    'footer_city' => $footercity,
    'footer_copyright' => $footercopyright,
    'footer_useful' => get_config('theme_mytheme', 'footeruseful') ?: 'Useful Links',
    'footer_company' => get_config('theme_mytheme', 'footercompany') ?: 'Our Company',
    'footer_newsletter' => get_config('theme_mytheme', 'footernewsletter') ?: 'Newsletter SignUp!',
    'footer_newsletterdesc' => get_config('theme_mytheme', 'footernewsletterdesc') ?: 'Get the latest news delivered to your inbox',
    'footer_follow' => get_config('theme_mytheme', 'footerfollow') ?: 'Follow Us:',
    'footer_terms' => get_config('theme_mytheme', 'footerterms') ?: 'Terms',
    'footer_privacy' => get_config('theme_mytheme', 'footerprivacy') ?: 'Privacy',
    
    // Frontpage settings from admin
    'herotagline' => $herotagline,
    'herotitle' => $herotitle,
    'herodescription' => $herodescription,
    'heroimage' => $heroimage,
    'abouttitle' => $abouttitle,
    'aboutdescription' => $aboutdescription,
    'aboutimage' => $aboutimage,
    'ctaimage' => $ctaimage,
    'testimonialimage' => $testimonialimage,
    'categoriesbg' => $categoriesbg,

    // CTA Section settings
    'ctatitle' => get_config('theme_mytheme', 'ctatitle') ?: 'Finding Your Right Courses',
    'ctadescription' => get_config('theme_mytheme', 'ctadescription') ?: 'Intuitive Shared Inbox Makes It Easy For Team Member',
    'ctabutton' => get_config('theme_mytheme', 'ctabutton') ?: 'GET STARTED',

    // Testimonial Section settings
    'testimonialbadge' => get_config('theme_mytheme', 'testimonialbadge') ?: 'Testimonials',
    'testimonialtitle' => get_config('theme_mytheme', 'testimonialtitle') ?: "What's Our Client Say About Us",
    'testimonialtext' => get_config('theme_mytheme', 'testimonialtext') ?: 'Manage and streamline operations across multiple locations.',
    'testimonialname' => get_config('theme_mytheme', 'testimonialname') ?: 'Brooklyn Simmons',
    'testimonialrole' => get_config('theme_mytheme', 'testimonialrole') ?: 'Engineer',
    'testimonial_stars' => $testimonial_stars = (get_config('theme_mytheme', 'testimonial_stars') ?: '5'),
    'testimonial_stars_list' => array_map(function($i) use ($testimonial_stars) { 
        return ['active' => ($i <= $testimonial_stars)]; 
    }, range(1, 5)),
    'testimonialbg' => get_config('theme_mytheme', 'testimonialbg') ?: '#ffffff',
    'coursesbg' => get_config('theme_mytheme', 'coursesbg') ?: '#F9F9F9',
    'ctabg' => get_config('theme_mytheme', 'ctabg') ?: '#0B0B3B',
    'aboutbg' => get_config('theme_mytheme', 'aboutbg') ?: '#ffffff',
    'categoriesbg_color' => get_config('theme_mytheme', 'categoriesbg') ?: '#F9F9F9',
    'instructorsbg' => get_config('theme_mytheme', 'instructorsbg') ?: '#ffffff',
    'testimonialbg' => get_config('theme_mytheme', 'testimonialbg') ?: '#ffffff',
    'blogsectionbg' => get_config('theme_mytheme', 'blogsectionbg') ?: '#F8F8F8',
    'eventssectionbg' => get_config('theme_mytheme', 'eventssectionbg') ?: '#F8F8F8',
    'bottomctabg' => get_config('theme_mytheme', 'bottomctabg') ?: '#5751E1',
    'brandssectionbg' => get_config('theme_mytheme', 'brandssectionbg') ?: 'transparent',
    'categoriescardbg' => get_config('theme_mytheme', 'categoriescardbg') ?: '#ffffff',
    'categoriesiconcolor' => get_config('theme_mytheme', 'categoriesiconcolor') ?: '#1a56db',
    'instructorsbtncolor' => get_config('theme_mytheme', 'instructorsbtncolor') ?: '#1a56db',
    'categoriesbadgebg' => get_config('theme_mytheme', 'categoriesbadgebg') ?: '#ffffff',
    'categoriesbadgecolor' => get_config('theme_mytheme', 'categoriesbadgecolor') ?: '#1a56db',
    
    // Brand Logos
    'brand1' => $brands['brand1'],
    'brand2' => $brands['brand2'],
    'brand3' => $brands['brand3'],
    'brand4' => $brands['brand4'],
    'brand5' => $brands['brand5'],
    'brand6' => $brands['brand6'],
    
    // Social media links
    'facebook_url' => get_config('theme_mytheme', 'facebook') ?: '#',
    'instagram_url' => get_config('theme_mytheme', 'instagram') ?: '#',
    'linkedin_url' => get_config('theme_mytheme', 'linkedin') ?: '#',
    'pinterest_url' => get_config('theme_mytheme', 'pinterest') ?: '#',
    'calendar_events' => get_calendar_events(),
    
    // Dynamic text labels
    'stat1label' => $stat1label,
    'stat2label' => $stat2label,
    'stat3label' => $stat3label,
    'stat4label' => $stat4label,
    'coursesbutton' => $coursesbutton,
    'courseratingtext' => $courseratingtext,
    'courselessonstext' => $courselessonstext,
    'coursestudentstext' => $coursestudentstext,
    
    // Dynamic colors (passing them for inline styles if needed)
    'primarycolor' => get_config('theme_mytheme', 'primarycolor') ?: '#5751E1',
    'secondarycolor' => get_config('theme_mytheme', 'secondarycolor') ?: '#FFC224',
    
    // Additional text settings
    'coursessubtitle' => get_config('theme_mytheme', 'coursessubtitle') ?: '+ unique online courses',
    'coursestitle' => get_config('theme_mytheme', 'coursestitle') ?: 'Our Most Popular Courses',
    'abouttitle' => get_config('theme_mytheme', 'abouttitle') ?: 'Professional Courses Taught By Industry Leaders',
    'navbar_categories' => theme_mytheme_get_categories(),
];

    // Course Grid settings and Bootstrap class calculation
    $cpr = get_config('theme_mytheme', 'coursesperrow') ?: '4';
    $cprt = get_config('theme_mytheme', 'coursesperrowtablet') ?: '3';
    $cprm = get_config('theme_mytheme', 'coursesperrowmobile') ?: '1';
    
    $templatecontext['colclass'] = 'col-lg-' . (12 / (int)$cpr);
    $templatecontext['colclasstablet'] = 'col-md-' . (12 / (int)$cprt);
    $templatecontext['colclassmobile'] = 'col-' . (12 / (int)$cprm);

    // Category Grid settings
    $catcpr = get_config('theme_mytheme', 'categoriesperrow') ?: '4';
    $templatecontext['catcolclass'] = 'col-lg-' . (12 / (int)$catcpr);
    $templatecontext['catcolclasstablet'] = 'col-md-4'; 
    $templatecontext['catcolclassmobile'] = 'col-sm-6';
    
    // Blog Grid settings
    $blogcpr = get_config('theme_mytheme', 'blogperrow') ?: '3';
    $templatecontext['blogcolclass'] = 'col-md-' . (12 / (int)$blogcpr);
    $templatecontext['blogoverflow'] = get_config('theme_mytheme', 'blogoverflow') ?: 'scroll';
    
    // Events Grid settings
    $eventscpr = get_config('theme_mytheme', 'eventsperrow') ?: '3';
    $templatecontext['eventscolclass'] = 'col-md-' . (12 / (int)$eventscpr);
    $templatecontext['eventoverflow'] = get_config('theme_mytheme', 'eventsoverflow') ?: 'scroll';
    
    // Instructor Grid settings
    $inscpr = get_config('theme_mytheme', 'instructorsperrow') ?: '2';
    $templatecontext['inscolclass'] = 'col-md-' . (12 / (int)$inscpr);
    
    // Labels
    $templatecontext['blogbadge'] = get_config('theme_mytheme', 'blogbadge') ?: 'News & Blogs';
    $templatecontext['blogtitle'] = get_config('theme_mytheme', 'blogtitle') ?: 'Our Latest News Feed';
    $templatecontext['blogdescription'] = get_config('theme_mytheme', 'blogdescription') ?: 'when known printer took a gallery of type scramble edmake';
    $templatecontext['eventsbadge'] = get_config('theme_mytheme', 'eventsbadge') ?: '📅 Events';
    $templatecontext['eventstitle'] = get_config('theme_mytheme', 'eventstitle') ?: 'Our Latest Events';
    $templatecontext['categoriesbadge'] = get_config('theme_mytheme', 'categoriesbadge') ?: 'Our Top Categories';
    $templatecontext['categoriestitle'] = get_config('theme_mytheme', 'categoriestitle') ?: 'Your Creative And Passionate Business Coach';
    $templatecontext['instructorsbadge'] = get_config('theme_mytheme', 'instructorsbadge') ?: 'Skilled Introduce';
    $templatecontext['instructorstitle'] = get_config('theme_mytheme', 'instructorstitle') ?: 'Our Top Class & Expert Instructors In One Place';
    $templatecontext['instructorsbutton'] = get_config('theme_mytheme', 'instructorsbutton') ?: 'See All Instructors';
    $templatecontext['instructorsdescription'] = get_config('theme_mytheme', 'instructorsdescription') ?: "when an unknown printer took a galley of type and scrambled makespecimen book has survived not only five centuries";

    // Course Meta Labels
    $templatecontext['coursesbutton'] = get_config('theme_mytheme', 'coursesbutton') ?: 'Discover All Courses';
    $templatecontext['courseratingtext'] = get_config('theme_mytheme', 'courseratingtext') ?: 'Reviews';
    $templatecontext['courselessonstext'] = get_config('theme_mytheme', 'courselessonstext') ?: 'Lessons';
    $templatecontext['coursestudentstext'] = get_config('theme_mytheme', 'coursestudentstext') ?: 'Students';
    
    // Overflow Handling Logic Flags
    $sections = ['courses', 'categories', 'blog', 'events', 'instructors'];
    foreach ($sections as $s) {
        $setting = get_config('theme_mytheme', $s . 'overflow') ?: 'scroll';
        $templatecontext[$s . '_is_slider'] = ($setting == 'slider');
    }
    
    // Fix for events/cats naming inconsistency
    $templatecontext['events_is_slider'] = (get_config('theme_mytheme', 'eventsoverflow') == 'slider');
    $templatecontext['cats_is_slider'] = (get_config('theme_mytheme', 'categoriesoverflow') == 'slider');

    $templatecontext['maincontent'] = $OUTPUT->main_content();

echo $OUTPUT->render_from_template('theme_mytheme/frontpage', $templatecontext);

// ========== ALL YOUR EXISTING FUNCTIONS BELOW ==========
function get_popular_courses() {
    global $DB, $CFG;
    
    $count = get_config('theme_mytheme', 'coursescount') ?: 12;
    $courseids = get_config('theme_mytheme', 'courseids');
    
    $featured_courses_data = [];
    for ($i = 1; $i <= 4; $i++) {
        $cid = get_config('theme_mytheme', 'course' . $i . '_id');
        if (!empty($cid) && is_numeric($cid)) {
            $featured_courses_data[$cid] = [
                'price' => get_config('theme_mytheme', 'course' . $i . '_price') ?: '49.00',
                'stars' => get_config('theme_mytheme', 'course' . $i . '_stars') ?: '5'
            ];
        }
    }

    $where = "WHERE c.visible = 1 AND c.id != :siteid";
    $params = ['siteid' => SITEID];
    
    if (!empty($featured_courses_data)) {
        $where .= " AND c.id IN (" . implode(',', array_keys($featured_courses_data)) . ")";
    } else if (!empty($courseids)) {
        $ids = explode(',', $courseids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $where .= " AND c.id IN (" . implode(',', $ids) . ")";
        }
    }
    
    $sql = "SELECT c.id, c.fullname, c.summary, c.startdate, cc.name as categoryname
            FROM {course} c
            LEFT JOIN {course_categories} cc ON cc.id = c.category
            $where
            ORDER BY c.startdate DESC
            LIMIT $count";
    
    $params = ['siteid' => SITEID];
    $courses = $DB->get_records_sql($sql, $params);
    
    if (!$courses) {
        return [];
    }
    
    $prices = ['55.00', '70.00', '50.00', '62.00', '45.00', '80.00', '35.00', '90.00', '48.00', '65.00', '72.00', '58.00'];
    $price_index = 0;
    
    $showrating = get_config('theme_mytheme', 'courses_showrating');
    $showprice = get_config('theme_mytheme', 'courses_showprice');
    
    foreach ($courses as $course) {
        $course->summary = strip_tags($course->summary);
        if (strlen($course->summary) > 100) {
            $course->summary = substr($course->summary, 0, 97) . '...';
        }
        
        $course->image = get_course_image($course->id);
        
        if (isset($featured_courses_data[$course->id])) {
            $course->price = $featured_courses_data[$course->id]['price'];
            $course->rating_stars = $featured_courses_data[$course->id]['stars'];
        } else {
            $course->price = isset($prices[$price_index]) ? $prices[$price_index] : '49.00';
            $course->rating_stars = '5';
        }
        
        // Create an array for the stars loop in mustache
        $course->stars_list = [];
        for ($s = 1; $s <= 5; $s++) {
            $course->stars_list[] = ['active' => ($s <= $course->rating_stars)];
        }
        
        $price_index++;
        
        $course->showrating = $showrating;
        $course->showprice = $showprice;
        
        // Get REAL lesson count from database
        $course->totallessons = get_course_lessons_count($course->id);
        if ($course->totallessons < 10) {
            $course->totallessons = '0' . $course->totallessons;
        }
        
        // Get REAL student count from database
        $course->totalstudents = get_course_students_count($course->id);
    }
    
    return array_values($courses);
}
function get_course_lessons_count($courseid) {
    global $DB;
    
    // Count all visible course modules (activities & resources)
    $sql = "SELECT COUNT(*) 
            FROM {course_modules} cm
            WHERE cm.course = :courseid 
            AND cm.visible = 1
            AND cm.deletioninprogress = 0";
    
    $count = $DB->count_records_sql($sql, ['courseid' => $courseid]);
    return $count ?: 0;
}
function get_course_students_count($courseid) {
    global $DB;
    
    $sql = "SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = :courseid
            AND e.status = 0
            AND u.deleted = 0 
            AND u.suspended = 0
            AND ue.status = 0";
    
    $count = $DB->count_records_sql($sql, ['courseid' => $courseid]);
    return $count ?: 0;
}
function get_course_image($courseid) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    
    try {
        $course = $DB->get_record('course', ['id' => $courseid]);
        if ($course) {
            $course_element = new core_course_list_element($course);
            $files = $course_element->get_course_overviewfiles();
            
            foreach ($files as $file) {
                if ($file->is_valid_image()) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    return $url->out(false);
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to default
    }
    
    global $OUTPUT;
    if (isset($OUTPUT)) {
        return $OUTPUT->image_url('course-placeholder', 'theme_mytheme')->out(false);
    }
    return $CFG->wwwroot . '/theme/mytheme/pix/course-placeholder.jpg';
}

function get_total_users() {
    global $DB;
    $sql = "SELECT COUNT(*) FROM {user} WHERE deleted = 0 AND suspended = 0";
    return $DB->count_records_sql($sql);
}

function get_total_courses() {
    global $DB;
    $sql = "SELECT COUNT(*) FROM {course} WHERE id != :siteid AND visible = 1";
    return $DB->count_records_sql($sql, ['siteid' => SITEID]);
}

function get_total_tutors() {
    global $DB;
    $sql = "SELECT COUNT(DISTINCT u.id)
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            WHERE u.deleted = 0 AND u.suspended = 0
            AND ra.roleid IN (SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher'))";
    return $DB->count_records_sql($sql);
}

function get_total_degrees() {
    global $DB;
    $sql = "SELECT COUNT(*) FROM {course} WHERE id != :siteid AND visible = 1 AND fullname LIKE '%degree%'";
    return $DB->count_records_sql($sql, ['siteid' => SITEID]) ?: 0;
}

function get_categories_with_course_counts() {
    global $DB;
    
    $count = get_config('theme_mytheme', 'categoriescount') ?: 8;
    $categoryids = get_config('theme_mytheme', 'categoryids');
    
    $where = "WHERE cc.visible = 1";
    $params = ['siteid' => SITEID];
    
    $specific_ids = [];
    for ($i = 1; $i <= 4; $i++) {
        $sid = get_config('theme_mytheme', 'cat' . $i . '_id');
        if (!empty($sid) && is_numeric($sid)) {
            $specific_ids[] = $sid;
        }
    }

    if (!empty($specific_ids)) {
        $where .= " AND cc.id IN (" . implode(',', $specific_ids) . ")";
    } else if (!empty($categoryids)) {
        $ids = explode(',', $categoryids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $where .= " AND cc.id IN (" . implode(',', $ids) . ")";
        }
    }
    
    $sql = "SELECT cc.id, cc.name, COUNT(c.id) as coursecount
            FROM {course_categories} cc
            LEFT JOIN {course} c ON c.category = cc.id AND c.visible = 1 AND c.id != :siteid
            $where
            GROUP BY cc.id, cc.name
            ORDER BY cc.name ASC
            LIMIT $count";
    
    $categories = $DB->get_records_sql($sql, $params);
    
    $icons = [
        'Business' => 'fa-briefcase',
        'Tax Advisory' => 'fa-file-invoice',
        'Finance' => 'fa-chart-line',
        'Law' => 'fa-gavel',
        'Technology' => 'fa-laptop-code',
        'Marketing' => 'fa-chart-simple',
        'Design' => 'fa-palette',
        'Health' => 'fa-heartbeat',
        'Language' => 'fa-language',
        'Science' => 'fa-flask'
    ];
    
    $result = [];
    foreach ($categories as $category) {
        $icon = isset($icons[$category->name]) ? $icons[$category->name] : 'fa-folder-open';
        $result[] = [
            'id' => $category->id,
            'name' => $category->name,
            'coursecount' => $category->coursecount,
            'icon' => $icon
        ];
    }
    
    return $result;
}

function get_instructors() {
    global $DB, $PAGE;
    
    $count = get_config('theme_mytheme', 'instructorscount') ?: 4;
    
    $specific_teachers = [];
    for ($i = 1; $i <= 4; $i++) {
        $tid = get_config('theme_mytheme', 'teacher' . $i . '_id');
        if (!empty($tid) && is_numeric($tid)) {
            $stars = get_config('theme_mytheme', 'teacher' . $i . '_stars') ?: '5';
            $specific_teachers[$tid] = $stars;
        }
    }

    $where = "WHERE u.deleted = 0 AND u.suspended = 0 AND r.shortname IN ('teacher', 'editingteacher')";
    if (!empty($specific_teachers)) {
        $where .= " AND u.id IN (" . implode(',', array_keys($specific_teachers)) . ")";
    } else {
        $where .= " AND ctx.contextlevel = 50";
    }

    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt, u.email
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            $where
            LIMIT $count";
    
    $instructors = $DB->get_records_sql($sql);
    
    $result = [];
    $skills = ['UX Design Lead', 'Web Design', 'Digital Marketing', 'Web Development'];
    $index = 0;
    
    foreach ($instructors as $instructor) {
        $userpicture = new user_picture($instructor);
        $userpicture->size = 100;
        $pictureurl = $userpicture->get_url($PAGE)->out(false);
        
        $rating = isset($specific_teachers[$instructor->id]) ? $specific_teachers[$instructor->id] : '4.8';
        
        // Create an array for the stars loop in mustache
        $stars_list = [];
        for ($s = 1; $s <= 5; $s++) {
            $stars_list[] = ['active' => ($s <= $rating)];
        }

        $result[] = [
            'id' => $instructor->id,
            'fullname' => fullname($instructor),
            'firstname' => $instructor->firstname,
            'lastname' => $instructor->lastname,
            'skill' => isset($skills[$index]) ? $skills[$index] : 'Expert Instructor',
            'pictureurl' => $pictureurl,
            'rating' => $rating,
            'stars_list' => $stars_list
        ];
        $index++;
    }
    
    if (empty($result)) {
        $result = [
            [
                'id' => 1,
                'fullname' => 'Mark Jukarberg',
                'firstname' => 'Mark',
                'lastname' => 'Jukarberg',
                'skill' => 'UX Design Lead',
                'pictureurl' => '',
                'rating' => '4.8'
            ],
            [
                'id' => 2,
                'fullname' => 'Olivia Mia',
                'firstname' => 'Olivia',
                'lastname' => 'Mia',
                'skill' => 'Web Design',
                'pictureurl' => '',
                'rating' => '4.8'
            ],
            [
                'id' => 3,
                'fullname' => 'William Hope',
                'firstname' => 'William',
                'lastname' => 'Hope',
                'skill' => 'Digital Marketing',
                'pictureurl' => '',
                'rating' => '4.8'
            ],
            [
                'id' => 4,
                'fullname' => 'Sophia Ava',
                'firstname' => 'Sophia',
                'lastname' => 'Ava',
                'skill' => 'Web Development',
                'pictureurl' => '',
                'rating' => '4.8'
            ]
        ];
    }
    
    return $result;
}

function get_blog_posts() {
    global $DB;
    
    $count = get_config('theme_mytheme', 'blogcount') ?: 3;
    $blogids = get_config('theme_mytheme', 'blogids');
    
    $tables = $DB->get_tables();
    if (!in_array('block_blogpost', $tables)) {
        return array_slice(get_demo_blog_posts(), 0, $count);
    }
    
    $admin_ids = get_admins();
    $admin_id_list = array_keys($admin_ids);
    
    if (empty($admin_id_list)) {
        return array_slice(get_demo_blog_posts(), 0, $count);
    }
    
    list($admin_sql, $admin_params) = $DB->get_in_or_equal($admin_id_list, SQL_PARAMS_NAMED);
    
    $where = "WHERE b.userid $admin_sql";
    if (!empty($blogids)) {
        $ids = explode(',', $blogids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $where .= " AND b.id IN (" . implode(',', $ids) . ")";
        }
    }
    
    $sql = "SELECT b.id, b.blog_heading, b.blog_text, b.timecreated, b.userid, 
                   u.id as userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt, u.email
            FROM {block_blogpost} b
            JOIN {user} u ON u.id = b.userid
            $where
            ORDER BY b.timecreated DESC
            LIMIT $count";
    
    $posts = $DB->get_records_sql($sql, $admin_params);
    
    if (!$posts) {
        return get_demo_blog_posts();
    }
    
    $result = [];
    $categories = ['Marketing', 'Agency', 'Play Ground', 'Technology', 'Education', 'News'];
    $index = 0;
    
    foreach ($posts as $post) {
        $user = new stdClass();
        $user->id = $post->userid;
        $user->firstname = $post->firstname ?? '';
        $user->lastname = $post->lastname ?? '';
        $user->firstnamephonetic = $post->firstnamephonetic ?? '';
        $user->lastnamephonetic = $post->lastnamephonetic ?? '';
        $user->middlename = $post->middlename ?? '';
        $user->alternatename = $post->alternatename ?? '';
        
        $author = fullname($user);
        $formatted_date = userdate($post->timecreated, '%d %B, %Y');
        
        $excerpt = strip_tags($post->blog_text);
        if (strlen($excerpt) > 100) {
            $excerpt = substr($excerpt, 0, 97) . '...';
        }
        
        $result[] = [
            'id' => $post->id,
            'category' => isset($categories[$index]) ? $categories[$index] : 'News',
            'title' => $post->blog_heading,
            'author' => $author,
            'date' => $formatted_date,
            'image' => '',
            'content' => $excerpt,
            'userid' => $post->userid
        ];
        $index++;
    }
    
    return $result;
}

function get_demo_blog_posts() {
    return [
        [
            'id' => 1,
            'category' => 'Marketing',
            'title' => 'Learn from Anywhere with Our eLearning Platform',
            'author' => 'Admin',
            'date' => date('d F, Y'),
            'image' => '',
            'content' => 'Discover how our platform makes learning accessible from anywhere in the world...',
            'userid' => 1
        ],
        [
            'id' => 2,
            'category' => 'Agency',
            'title' => 'Platform has given me the ability to learn on my own schedule',
            'author' => 'Admin',
            'date' => date('d F, Y'),
            'image' => '',
            'content' => 'Flexible learning options that fit your busy lifestyle...',
            'userid' => 1
        ],
        [
            'id' => 3,
            'category' => 'Play Ground',
            'title' => 'Learning platform where you can easily access course content',
            'author' => 'Admin',
            'date' => date('d F, Y'),
            'image' => '',
            'content' => 'Easy access to all your course materials in one place...',
            'userid' => 1
        ]
    ];
}
function get_calendar_events() {
    global $DB, $CFG;
    
    $count = get_config('theme_mytheme', 'eventscount') ?: 3;
    $eventids = get_config('theme_mytheme', 'eventids');
    
    require_once($CFG->dirroot . '/calendar/lib.php');
    
    $now = time();
    $end = $now + (90 * 24 * 60 * 60);
    
    $specific_event_cats = [];
    for ($i = 1; $i <= 4; $i++) {
        $ecid = get_config('theme_mytheme', 'event_cat' . $i . '_id');
        if (!empty($ecid) && is_numeric($ecid)) {
            $specific_event_cats[] = $ecid;
        }
    }

    $where = "WHERE e.timestart >= :now AND e.timestart <= :end AND e.visible = 1";
    $params = ['now' => $now, 'end' => $end];
    
    if (!empty($specific_event_cats)) {
        $where .= " AND e.categoryid IN (" . implode(',', $specific_event_cats) . ")";
    } else if (!empty($eventids)) {
        $ids = explode(',', $eventids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, 'is_numeric');
        if (!empty($ids)) {
            $where = "WHERE e.id IN (" . implode(',', $ids) . ")";
            $params = []; // Clear other params if specific IDs requested
        }
    }
    
    $sql = "SELECT e.id, e.name, e.description, e.timestart, e.eventtype,
                   u.firstname, u.lastname
            FROM {event} e
            LEFT JOIN {user} u ON u.id = e.userid
            $where
            ORDER BY e.timestart ASC
            LIMIT $count";
    
    $params = ['now' => $now, 'end' => $end];
    $events = $DB->get_records_sql($sql, $params);
    
    $result = [];
    $gradients = [
        'linear-gradient(135deg, #667eea, #764ba2)',
        'linear-gradient(135deg, #198754, #20c997)',
        'linear-gradient(135deg, #fd7e14, #ffc107)'
    ];
    $i = 0;
    
    foreach ($events as $event) {
        $desc = $event->description ?? '';
        $image = '';

if (preg_match('/@@PLUGINFILE@@\/([^"\']+)/', $desc, $matches)) {
    $filename = $matches[1];
    $filename = str_replace('&amp;', '&', $filename);
    // contextid=2, component=calendar, filearea=event_description, itemid=event->id
    $image = $CFG->wwwroot . '/pluginfile.php/2/calendar/event_description/' . $event->id . '/' . $filename;
}
        
        $type_label = 'Site Event';
        if ($event->eventtype == 'course') $type_label = 'Course Event';
        elseif ($event->eventtype == 'group') $type_label = 'Group Event';
        elseif ($event->eventtype == 'user') $type_label = 'Personal Event';
        
        $author = 'Admin';
        if (!empty($event->firstname)) {
            $author = $event->firstname . ' ' . ($event->lastname ?? '');
        }
        
        $result[] = [
            'id' => $event->id,
            'title' => $event->name,
            'image' => $image,
            'gradient' => $gradients[$i % 3],
            'type_label' => $type_label,
            'author' => trim($author),
            'date' => date('d F, Y', $event->timestart),
            'url' => $CFG->wwwroot . '/calendar/view.php?view=day&time=' . $event->timestart,
        ];
        $i++;
    }
    
    return $result;
}