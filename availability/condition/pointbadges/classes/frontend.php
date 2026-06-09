<?php
namespace availability_pointbadges;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {
    protected function get_javascript_strings() {
        return ['title', 'restriction_premium', 'restriction_vip'];
    }

    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return [];
    }
}
