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

class xp_earned extends \core\event\base {
    
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    
    public static function get_name() {
        return get_string('xp_earned', 'local_point_badges');
    }
    
    public function get_description() {
        $xp = $this->other['xp_amount'];
        $reason = $this->other['reason'];
        return "The user with id '{$this->relateduserid}' earned {$xp} XP for: {$reason}";
    }
    
    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->relateduserid]);
    }
    
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['xp_amount'])) {
            throw new \coding_exception('The \'xp_amount\' must be set in other.');
        }
        if (!isset($this->other['reason'])) {
            throw new \coding_exception('The \'reason\' must be set in other.');
        }
    }
}