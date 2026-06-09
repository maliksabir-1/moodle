<?php
namespace local_point_badges\event;

defined('MOODLE_INTERNAL') || die();

class level_down extends \core\event\base {
    
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    
    public static function get_name() {
        return get_string('level_down', 'local_point_badges');
    }
    
    public function get_description() {
        $old = $this->other['old_level'];
        $new = $this->other['new_level'];
        return "The user with id '{$this->relateduserid}' moved from level {$old} down to level {$new}";
    }
    
    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->relateduserid]);
    }
    
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['old_level'])) {
            throw new \coding_exception('The \'old_level\' must be set in other.');
        }
        if (!isset($this->other['new_level'])) {
            throw new \coding_exception('The \'new_level\' must be set in other.');
        }
    }
}