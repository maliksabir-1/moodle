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

namespace block_blogpost\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for user preferences.
 */
class preferences_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $USER, $DB;
        
        $mform = $this->_form;
        
        // Get existing preferences
        $preferences = $DB->get_record('block_blogpost_prefs', ['userid' => $USER->id]);
        
        // Email notifications - option to receive all blog post updates
        $mform->addElement('checkbox', 'email_updates', get_string('emailupdates', 'block_blogpost'));
        $mform->setDefault('email_updates', $preferences ? $preferences->email_updates : 0);
        $mform->addHelpButton('email_updates', 'emailupdates', 'block_blogpost');
        
        $this->add_action_buttons(false, get_string('savepreferences', 'block_blogpost'));
    }
    
    /**
     * Validation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}