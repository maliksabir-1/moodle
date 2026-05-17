<?php
namespace theme_mytheme\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \theme_boost\output\core_renderer {
    
    public function get_setting($name, $default = '') {
        return get_config('theme_mytheme', $name) ?: $default;
    }
    
    public function get_dynamic_css() {
        $css = ':root {';
        
        // === BRAND COLORS ===
        $css .= '--primary-color: ' . $this->get_setting('primarycolor', '#5751E1') . ';';
        $css .= '--secondary-color: ' . $this->get_setting('secondarycolor', '#FFC224') . ';';
        
        // === CORE COLORS ===
        $css .= '--mytheme-accent: ' . $this->get_setting('accentcolor', '#FFC224') . ';';
        
        // === BACKGROUND COLORS ===
        $css .= '--mytheme-bg-primary: ' . $this->get_setting('bgprimary', '#F9F9F9') . ';';
        $css .= '--mytheme-bg-secondary: ' . $this->get_setting('bgsecondary', '#0B0B3B') . ';';
        $css .= '--stats-bg: ' . $this->get_setting('statsbg', '#F9F9F9') . ';';
        $css .= '--courses-bg: ' . $this->get_setting('coursesbg', '#F9F9F9') . ';';
        
        // === TEXT COLORS ===
        $css .= '--mytheme-text: ' . $this->get_setting('textcolor', '#333333') . ';';
        $css .= '--mytheme-heading: ' . $this->get_setting('headingcolor', '#000000') . ';';
        $css .= '--mytheme-link: ' . $this->get_setting('linkcolor', '#1a56db') . ';';
        $css .= '--mytheme-link-hover: ' . $this->get_setting('linkhovercolor', '#1e40af') . ';';
        
        // === NAVBAR COLORS ===
        $css .= '--navbar-bg: ' . $this->get_setting('navbarbg', '#ffffff') . ';';
        $css .= '--navbar-text: ' . $this->get_setting('navbartextcolor', '#5a5c69') . ';';
        $css .= '--navbar-hover: ' . $this->get_setting('navbarhovercolor', '#1a56db') . ';';
        $css .= '--navbar-active: ' . $this->get_setting('navbaractivecolor', '#1a56db') . ';';
        $css .= '--navbar-height: ' . $this->get_setting('navbarheight', '60px') . ';';
        
        // === FOOTER COLORS ===
        $css .= '--footer-bg: ' . $this->get_setting('footerbg', '#0B0B3B') . ';';
        $css .= '--footer-text: ' . $this->get_setting('footertextcolor', '#a8a8c8') . ';';
        $css .= '--footer-heading: ' . $this->get_setting('footerheadingcolor', '#ffffff') . ';';
        $css .= '--footer-accent: ' . $this->get_setting('footeraccent', '#6259F3') . ';';
        $css .= '--footer-padding: ' . $this->get_setting('footerpadding', '80px 0 10px 0') . ';';
        
        // === HERO SECTION ===
        $css .= '--hero-tagline-color: ' . $this->get_setting('herotaglinecolor', '#FFD700') . ';';
        $css .= '--hero-title-color: ' . $this->get_setting('herotitlecolor', '#ffffff') . ';';
        $css .= '--hero-desc-color: ' . $this->get_setting('herodesccolor', '#ffffff') . ';';
        $css .= '--hero-overlay-color: ' . $this->get_setting('herooverlaycolor', 'rgba(0,0,0,0.55)') . ';';
        
        // === STATS COLORS ===
        $css .= '--stat1-color: ' . $this->get_setting('stat1color', '#1a56db') . ';';
        $css .= '--stat2-color: ' . $this->get_setting('stat2color', '#198754') . ';';
        $css .= '--stat3-color: ' . $this->get_setting('stat3color', '#ffc107') . ';';
        $css .= '--stat4-color: ' . $this->get_setting('stat4color', '#0dcaf0') . ';';
        $css .= '--stat-label-color: ' . $this->get_setting('statlabelcolor', '#000000') . ';';
        
        // === SECTION BACKGROUNDS ===
        $css .= '--blog-bg: ' . $this->get_setting('blogsectionbg', '#F8F8F8') . ';';
        $css .= '--blog-title-color: ' . $this->get_setting('blogtitlecolor', '#000000') . ';';
        $css .= '--events-bg: ' . $this->get_setting('eventssectionbg', '#F8F8F8') . ';';
        $css .= '--events-title-color: ' . $this->get_setting('eventstitlecolor', '#000000') . ';';
        
        // === BADGE COLORS ===
        $css .= '--blog-badge-bg: ' . $this->get_setting('blogbadgebg', '#f0f2f5') . ';';
        $css .= '--blog-badge-color: ' . $this->get_setting('blogbadgecolor', '#1a56db') . ';';
        $css .= '--events-badge-bg: ' . $this->get_setting('eventsbadgebg', '#f0f2f5') . ';';
        $css .= '--events-badge-color: ' . $this->get_setting('eventsbadgecolor', '#1a56db') . ';';
        
        // === LOGIN PAGE ===
        $css .= '--login-bg: ' . $this->get_setting('loginbg', 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)') . ';';
        $css .= '--login-card-bg: ' . $this->get_setting('logincardbg', '#ffffff') . ';';
        $css .= '--login-btn-bg: ' . $this->get_setting('loginbtnbg', '#1a56db') . ';';
        $css .= '--login-btn-text: ' . $this->get_setting('loginbtntext', '#ffffff') . ';';
        
        // === BUTTON SETTINGS ===
        $css .= '--btn-bg: ' . $this->get_setting('btnprimarybg', '#1a56db') . ';';
        $css .= '--btn-hover: ' . $this->get_setting('btnprimaryhover', '#1e40af') . ';';
        $css .= '--btn-text: ' . $this->get_setting('btntextcolor', '#ffffff') . ';';
        $css .= '--btn-radius: ' . $this->get_setting('btnborderradius', '8px') . ';';
        
        // === TYPOGRAPHY ===
        $css .= '--mytheme-font: ' . $this->get_setting('fontfamily', "'Inter', sans-serif") . ';';
        $css .= '--mytheme-heading-font: ' . $this->get_setting('headingfont', "'Inter', sans-serif") . ';';
        $css .= '--mytheme-font-size: ' . $this->get_setting('basefontsize', '16px') . ';';
        
        // === USER DROPDOWN ===
        $css .= '--user-dropdown-bg: ' . $this->get_setting('userdropdownbg', '#ffffff') . ';';
        $css .= '--user-dropdown-link: ' . $this->get_setting('userdropdownlinkcolor', '#333333') . ';';
        $css .= '--user-dropdown-hover-bg: ' . $this->get_setting('userdropdownhoverbg', '#f0f2f5') . ';';
        $css .= '--user-dropdown-hover-color: ' . $this->get_setting('userdropdownhovercolor', '#1a56db') . ';';
        $css .= '--user-avatar-bg: ' . $this->get_setting('useravatarbg', '#1a56db') . ';';
        $css .= '--user-avatar-text: ' . $this->get_setting('useravatartext', '#ffffff') . ';';
        
        // === FOOTER COLORS ===
        $css .= '--footer-bg: ' . $this->get_setting('footerbg', '#0B0B3B') . ';';
        $css .= '--footer-text: ' . $this->get_setting('footertextcolor', '#a8a8c8') . ';';
        $css .= '--footer-heading: ' . $this->get_setting('footerheadingcolor', '#ffffff') . ';';
        $css .= '--footer-accent: ' . $this->get_setting('footeraccent', '#6259F3') . ';';
        $css .= '--footer-padding: ' . $this->get_setting('footerpadding', '80px 0 10px 0') . ';';
        
        $css .= '}';
        
        // Add navbar specific CSS
        $css .= '.navbar {';
        $css .= 'background: var(--navbar-bg) !important;';
        $css .= 'min-height: var(--navbar-height);';
        $css .= '}';
        
        $css .= '.navbar .nav-link {';
        $css .= 'color: var(--navbar-text) !important;';
        $css .= '}';
        
        $css .= '.navbar .nav-link:hover {';
        $css .= 'color: var(--navbar-hover) !important;';
        $css .= '}';
        
        $css .= '.navbar .nav-link.active {';
        $css .= 'color: var(--navbar-active) !important;';
        $css .= '}';
        
        // Footer specific CSS
        $css .= 'footer {';
        $css .= 'background: var(--footer-bg) !important;';
        $css .= 'padding: var(--footer-padding) !important;';
        $css .= 'color: var(--footer-text) !important;';
        $css .= '}';
        
        $css .= 'footer h1, footer h2, footer h3, footer h4, footer h5, footer h6 {';
        $css .= 'color: var(--footer-heading) !important;';
        $css .= '}';
        
        $css .= 'footer a { color: var(--footer-text) !important; }';
        $css .= 'footer a:hover { color: var(--footer-accent) !important; }';
        
        // Login page CSS
        $css .= 'body.pagelayout-login {';
        $css .= 'background: var(--login-bg) !important;';
        $css .= '}';
        
        return $css;
    }
    
    public function standard_head_html() {
        $output = parent::standard_head_html();
        
        // Google Font
        $google_font = $this->get_setting('googlefont', 'Inter');
        if (!empty($google_font)) {
            $font_url = 'https://fonts.googleapis.com/css2?family=' . urlencode($google_font) . ':wght@300;400;500;600;700;800&display=swap';
            $output .= '<link href="' . $font_url . '" rel="stylesheet">';
        }
        
        // Dynamic CSS
        $output .= '<style id="theme-dynamic-css">' . $this->get_dynamic_css() . '</style>';
        
        // Custom CSS
        $custom_css = $this->get_setting('customcss', '');
        if (!empty($custom_css)) {
            $output .= '<style id="theme-custom-css">' . $custom_css . '</style>';
        }
        
        // Force refresh CSS variables
        $output .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Force apply theme settings
                var style = document.getElementById("theme-dynamic-css");
                if (style) {
                    style.disabled = true;
                    setTimeout(function() {
                        style.disabled = false;
                    }, 50);
                }
            });
        </script>';
        
        return $output;
    }
    
    public function get_hero_image_url() {
        return $this->get_theme_image_url('heroimage', 'heroimage');
    }

    public function get_about_image_url() {
        return $this->get_theme_image_url('aboutimage', 'aboutimage');
    }

    public function get_cta_image_url() {
        return $this->get_theme_image_url('ctaimage', 'ctaimage');
    }

    public function get_testimonial_image_url() {
        return $this->get_theme_image_url('testimonialimage', 'testimonialimage');
    }

    public function get_categories_bg_image_url() {
        return $this->get_theme_image_url('categoriesbgimage', 'categoriesbg');
    }

    public function get_brand_logo_url($num) {
        return $this->get_theme_image_url('brand' . $num, 'brand' . $num);
    }

    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        return $this->get_theme_image_url('logo', 'logo');
    }

    private function get_theme_image_url($settingname, $filearea) {
        $theme = \theme_config::load('mytheme');
        $settingvalue = $theme->settings->$settingname ?? '';
        
        if (empty($settingvalue) || $settingvalue === '/img.png') {
            return false;
        }
        
        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'theme_mytheme', $filearea, 0, 'itemid, filepath, filename', false);
        
        if (!empty($files)) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }
        
        if (!empty($settingvalue) && $settingvalue !== '/img.png') {
            return new \moodle_url($settingvalue);
        }
        
        return false;
    }

    public function render_from_template($templatename, $context) {
        if ($templatename === 'core/loginform') {
            if (is_object($context)) {
                $context->loginheading = get_config('theme_mytheme', 'loginheading');
                $context->forgotpasswordtext = get_config('theme_mytheme', 'forgotpasswordtext');
                $context->guestlogintext = get_config('theme_mytheme', 'guestlogintext');
                $context->guestbuttontext = get_config('theme_mytheme', 'guestbuttontext');
            } else if (is_array($context)) {
                $context['loginheading'] = get_config('theme_mytheme', 'loginheading');
                $context['forgotpasswordtext'] = get_config('theme_mytheme', 'forgotpasswordtext');
                $context['guestlogintext'] = get_config('theme_mytheme', 'guestlogintext');
                $context['guestbuttontext'] = get_config('theme_mytheme', 'guestbuttontext');
            }
        }
        return parent::render_from_template($templatename, $context);
    }

}