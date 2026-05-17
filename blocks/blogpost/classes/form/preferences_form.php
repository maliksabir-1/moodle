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
        
        // Email notifications
        $mform->addElement('header', 'emailsettings', get_string('emailsettings', 'block_blogpost'));
        
        $mform->addElement('checkbox', 'email_notifications', get_string('emailnotifications', 'block_blogpost'));
        $mform->setDefault('email_notifications', $preferences ? $preferences->email_notifications : 1);
        $mform->addHelpButton('email_notifications', 'emailnotifications', 'block_blogpost');
        
        // Tag preferences
        $mform->addElement('header', 'tagsettings', get_string('tagsettings', 'block_blogpost'));
        
        $mform->addElement('text', 'notify_tags', get_string('notifytags', 'block_blogpost'));
        $mform->setType('notify_tags', PARAM_TEXT);
        $mform->setDefault('notify_tags', $preferences ? $preferences->notify_tags : '');
        $mform->addHelpButton('notify_tags', 'notifytags', 'block_blogpost');
        
        $mform->addElement('static', 'tag_example', '', get_string('tagexample', 'block_blogpost'));
        
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