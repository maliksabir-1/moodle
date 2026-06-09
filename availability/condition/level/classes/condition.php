<?php
namespace availability_level;
defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    protected $level = 1;

    public function __construct($structure) {
        if (isset($structure->level)) {
            $this->level = (int)$structure->level;
        }
    }

    public function save() {
        return (object)['type' => 'level', 'level' => $this->level];
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $course = $info->get_course();
        $user_level_info = \local_point_badges\manager::get_user_level_info($userid, $course->id);
        $user_level = $user_level_info['current_level'];
        
        $allow = ($user_level >= $this->level);
        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        return get_string($not ? 'requires_notlevel' : 'requires_level', 'availability_level', $this->level);
    }

    protected function get_debug_string() {
        return 'level>=' . $this->level;
    }
}
