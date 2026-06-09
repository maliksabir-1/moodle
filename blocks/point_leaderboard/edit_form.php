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

class block_point_leaderboard_edit_form extends block_edit_form {
    
    protected function specific_definition($mform) {
        global $CFG;
        
        // Header
        $mform->addElement('header', 'configheader', get_string('settings'));
        
        // Custom title
        $mform->addElement('text', 'config_title', get_string('title', 'block_point_leaderboard'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', '');
        
        // Number of entries
        $mform->addElement('select', 'config_entries', get_string('entries_to_show', 'block_point_leaderboard'), [
            5 => 5,
            10 => 10,
            15 => 15,
            20 => 20,
            25 => 25,
            50 => 50,
        ]);
        $mform->setDefault('config_entries', 10);
        
        // Global mode
        $mform->addElement('advcheckbox', 'config_global_mode', get_string('global_leaderboard', 'block_point_leaderboard'));
        $mform->setDefault('config_global_mode', 0);
        
        // Show streak
        $mform->addElement('advcheckbox', 'config_show_streak', get_string('show_streak', 'block_point_leaderboard'));
        $mform->setDefault('config_show_streak', 1);
        
        // Show avatars
        $mform->addElement('advcheckbox', 'config_show_avatars', get_string('show_avatars', 'block_point_leaderboard'));
        $mform->setDefault('config_show_avatars', 1);
    }
}