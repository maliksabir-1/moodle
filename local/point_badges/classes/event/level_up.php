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

namespace local_point_badges\event;

defined('MOODLE_INTERNAL') || die();

class level_up extends \core\event\base {
    
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    
    public static function get_name() {
        return get_string('level_up', 'local_point_badges');
    }
    
    public function get_description() {
        $old = $this->other['old_level'];
        $new = $this->other['new_level'];
        return "The user with id '{$this->relateduserid}' advanced from level {$old} to level {$new}";
    }
    
    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->relateduserid]);
    }
    
    // REMOVED the incorrect static get_message() method
    // Level up notifications should be handled by the event observer, not here
    
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