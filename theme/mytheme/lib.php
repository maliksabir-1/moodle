<?php
// theme/mytheme/lib.php
defined('MOODLE_INTERNAL') || die();

/**
 * Force the login layout for the signup page to match Boost behavior.
 */
function theme_mytheme_page_layout($page, $layout) {
    if ($page->pagetype == 'login-signup') {
        return 'login';
    }
    return $layout;
}

/**
 * Inject dynamic CSS variables from theme settings
 */
function theme_mytheme_get_main_scss_content($scss) {
    // Get theme settings
    $primarycolor = get_config('theme_mytheme', 'primarycolor') ?: '#5751E1';
    $secondarycolor = get_config('theme_mytheme', 'secondarycolor') ?: '#FFC224';
    
    // Navbar colors
    $navbarbg = get_config('theme_mytheme', 'navbarbg') ?: '#ffffff';
    $navbartextcolor = get_config('theme_mytheme', 'navbartextcolor') ?: '#5a5c69';
    $navbarhovercolor = get_config('theme_mytheme', 'navbarhovercolor') ?: '#1a56db';
    $navbaractivecolor = get_config('theme_mytheme', 'navbaractivecolor') ?: '#1a56db';
    $navbarheight = get_config('theme_mytheme', 'navbarheight') ?: '60px';
    
    // Footer colors
    $footerbg = get_config('theme_mytheme', 'footerbg') ?: '#0B0B3B';
    $footertextcolor = get_config('theme_mytheme', 'footertextcolor') ?: '#a8a8c8';
    $footerheadingcolor = get_config('theme_mytheme', 'footerheadingcolor') ?: '#ffffff';
    $footeraccent = get_config('theme_mytheme', 'footeraccent') ?: '#6259F3';
    $footerpadding = get_config('theme_mytheme', 'footerpadding') ?: '80px 0 10px 0';
    
    // Login page colors
    $loginbg = get_config('theme_mytheme', 'loginbg') ?: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)';
    $logincardbg = get_config('theme_mytheme', 'logincardbg') ?: '#ffffff';
    $loginbtnbg = get_config('theme_mytheme', 'loginbtnbg') ?: '#1a56db';
    $loginbtntext = get_config('theme_mytheme', 'loginbtntext') ?: '#ffffff';
    
    // Button settings
    $btnprimarybg = get_config('theme_mytheme', 'btnprimarybg') ?: '#1a56db';
    $btnprimaryhover = get_config('theme_mytheme', 'btnprimaryhover') ?: '#1e40af';
    $btntextcolor = get_config('theme_mytheme', 'btntextcolor') ?: '#ffffff';
    $btnborderradius = get_config('theme_mytheme', 'btnborderradius') ?: '8px';
    
    // Typography
    $fontfamily = get_config('theme_mytheme', 'fontfamily') ?: "'Inter', sans-serif";
    $headingfont = get_config('theme_mytheme', 'headingfont') ?: "'Inter', sans-serif";
    $basefontsize = get_config('theme_mytheme', 'basefontsize') ?: '16px';
    $textcolor = get_config('theme_mytheme', 'textcolor') ?: '#333333';
    $headingcolor = get_config('theme_mytheme', 'headingcolor') ?: '#000000';
    $linkcolor = get_config('theme_mytheme', 'linkcolor') ?: '#1a56db';
    $linkhovercolor = get_config('theme_mytheme', 'linkhovercolor') ?: '#1e40af';
    
    // User dropdown colors
    $userdropdownbg = get_config('theme_mytheme', 'userdropdownbg') ?: '#ffffff';
    $userdropdownlinkcolor = get_config('theme_mytheme', 'userdropdownlinkcolor') ?: '#333333';
    $userdropdownhoverbg = get_config('theme_mytheme', 'userdropdownhoverbg') ?: '#f0f2f5';
    $userdropdownhovercolor = get_config('theme_mytheme', 'userdropdownhovercolor') ?: '#1a56db';
    $useravatarbg = get_config('theme_mytheme', 'useravatarbg') ?: '#1a56db';
    $useravatartext = get_config('theme_mytheme', 'useravatartext') ?: '#ffffff';
    
    // Hero section
    $herotaglinecolor = get_config('theme_mytheme', 'herotaglinecolor') ?: '#FFD700';
    $herotitlecolor = get_config('theme_mytheme', 'herotitlecolor') ?: '#ffffff';
    $herodesccolor = get_config('theme_mytheme', 'herodesccolor') ?: '#ffffff';
    $herooverlaycolor = get_config('theme_mytheme', 'herooverlaycolor') ?: 'rgba(0,0,0,0.55)';
    
    // Build CSS variables
    $variables = "
        :root {
            /* Core Colors */
            --primary-color: $primarycolor;
            --secondary-color: $secondarycolor;
            
            /* Navbar */
            --navbar-bg: $navbarbg;
            --navbar-text: $navbartextcolor;
            --navbar-hover: $navbarhovercolor;
            --navbar-active: $navbaractivecolor;
            --navbar-height: $navbarheight;
            
            /* Footer */
            --footer-bg: $footerbg;
            --footer-text: $footertextcolor;
            --footer-heading: $footerheadingcolor;
            --footer-accent: $footeraccent;
            --footer-padding: $footerpadding;
            
            /* Login Page */
            --login-bg: $loginbg;
            --login-card-bg: $logincardbg;
            --login-btn-bg: $loginbtnbg;
            --login-btn-text: $loginbtntext;
            
            /* User Dropdown */
            --user-dropdown-bg: $userdropdownbg;
            --user-dropdown-link: $userdropdownlinkcolor;
            --user-dropdown-hover-bg: $userdropdownhoverbg;
            --user-dropdown-hover-color: $userdropdownhovercolor;
            --user-avatar-bg: $useravatarbg;
            --user-avatar-text: $useravatartext;
            
            /* Buttons */
            --btn-bg: $btnprimarybg;
            --btn-hover: $btnprimaryhover;
            --btn-text: $btntextcolor;
            --btn-radius: $btnborderradius;
            
            /* Typography */
            --mytheme-font: $fontfamily;
            --mytheme-heading-font: $headingfont;
            --mytheme-font-size: $basefontsize;
            --mytheme-text: $textcolor;
            --mytheme-heading: $headingcolor;
            --mytheme-link: $linkcolor;
            --mytheme-link-hover: $linkhovercolor;
            
            /* Hero */
            --hero-tagline-color: $herotaglinecolor;
            --hero-title-color: $herotitlecolor;
            --hero-desc-color: $herodesccolor;
            --hero-overlay-color: $herooverlaycolor;
        }
        
        /* Navbar Styles */
        .navbar {
            background: var(--navbar-bg) !important;
            min-height: var(--navbar-height);
        }
        .navbar .nav-link {
            color: var(--navbar-text) !important;
        }
        .navbar .nav-link:hover {
            color: var(--navbar-hover) !important;
        }
        .navbar .nav-link.active {
            color: var(--navbar-active) !important;
            font-weight: 600;
        }
        
        /* Footer Styles */
        footer {
            background: var(--footer-bg) !important;
            padding: var(--footer-padding) !important;
            color: var(--footer-text) !important;
        }
        footer h1, footer h2, footer h3, footer h4, footer h5, footer h6 {
            color: var(--footer-heading) !important;
        }
        footer a:hover {
            color: var(--footer-accent) !important;
        }
        
        /* Login Page Styles */
        body.pagelayout-login {
            background: var(--login-bg) !important;
        }
        body.pagelayout-login .auth-card {
            background: var(--login-card-bg) !important;
        }
        
        /* Button Styles */
        .btn-primary {
            background-color: var(--btn-bg) !important;
            border-color: var(--btn-bg) !important;
            color: var(--btn-text) !important;
            border-radius: var(--btn-radius) !important;
        }
        .btn-primary:hover {
            background-color: var(--btn-hover) !important;
            border-color: var(--btn-hover) !important;
        }
        
        /* Typography */
        body {
            font-family: var(--mytheme-font);
            font-size: var(--mytheme-font-size);
            color: var(--mytheme-text);
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--mytheme-heading-font);
            color: var(--mytheme-heading);
        }
        a {
            color: var(--mytheme-link);
        }
        a:hover {
            color: var(--mytheme-link-hover);
        }
        
        /* User Dropdown */
        .dropdown-menu {
            background-color: var(--user-dropdown-bg) !important;
        }
        .dropdown-item {
            color: var(--user-dropdown-link) !important;
        }
        .dropdown-item:hover {
            background-color: var(--user-dropdown-hover-bg) !important;
            color: var(--user-dropdown-hover-color) !important;
        }
        .user-avatar-small-initials, .user-avatar-small-initials-mobile {
            background-color: var(--user-avatar-bg) !important;
            color: var(--user-avatar-text) !important;
        }
    ";
    
    return $variables . $scss;
}

/**
 * Serve theme files (images, logos, etc.)
 */
function theme_mytheme_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        send_file_not_found();
    }
    
    $allowedareas = [
        'logo', 'heroimage', 'aboutimage', 'ctaimage', 
        'categoriesbg', 'testimonialimage',
        'brand1', 'brand2', 'brand3', 'brand4', 'brand5', 'brand6'
    ];
    
    if (in_array($filearea, $allowedareas)) {
        $theme = theme_config::load('mytheme');
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    
    send_file_not_found();
}

/**
 * Get top level course categories for navbar dropdown
 */
function theme_mytheme_get_categories() {
    global $DB;
    $categories = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC', 'id, name');
    $results = [];
    foreach ($categories as $cat) {
        $results[] = [
            'id' => $cat->id,
            'name' => format_string($cat->name),
            'url' => (new moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false)
        ];
    }
    return $results;
}