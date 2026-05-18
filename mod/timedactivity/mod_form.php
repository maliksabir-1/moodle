<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_timedactivity_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        // Video Settings.
        $mform->addElement('header', 'videosettings', get_string('videosettings', 'mod_timedactivity'));
        $mform->addElement('select', 'videosource', get_string('videosource', 'mod_timedactivity'),
            array('local' => get_string('localvideo', 'mod_timedactivity'),
                  'youtube' => get_string('youtube', 'mod_timedactivity')));
        $mform->setDefault('videosource', 'local');

        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'mod_timedactivity'), null,
            array('subdirs' => 0, 'maxbytes' => 1024 * 1024 * 500, 'accepted_types' => array('.mp4', '.webm', '.ogv')));
        $mform->hideIf('videofile', 'videosource', 'eq', 'youtube');

        $mform->addElement('text', 'youtubeurl', get_string('youtubeurl', 'mod_timedactivity'), array('size' => 60));
        $mform->setType('youtubeurl', PARAM_URL);
        $mform->hideIf('youtubeurl', 'videosource', 'eq', 'local');

        // Timer Settings.
        $mform->addElement('header', 'timersettings', get_string('timersettings', 'mod_timedactivity'));
        
        $timegroup = [];
        $timegroup[] = $mform->createElement('text', 'timevalue', '', ['size' => 6]);
        $timegroup[] = $mform->createElement('select', 'timeunit', '', [
            1 => get_string('second', 'mod_timedactivity'),
            60 => get_string('minute', 'mod_timedactivity'),
            3600 => get_string('hour', 'mod_timedactivity'),
        ]);
        $mform->addGroup($timegroup, 'time_group', get_string('timerequired', 'mod_timedactivity'), ' ', false);
        $mform->setType('timevalue', PARAM_INT);
        $mform->setType('timeunit',  PARAM_INT);
        $mform->setDefault('time_group', ['timevalue' => 0, 'timeunit' => 1]);

        $mform->addElement('checkbox', 'matchduration', get_string('matchduration', 'mod_timedactivity'));
        $mform->addHelpButton('matchduration', 'matchduration', 'mod_timedactivity');

        $mform->addElement('date_time_selector', 'completiontime', get_string('completiontime', 'mod_timedactivity'), array('optional' => true));

        // Quiz settings.
        $mform->addElement('header', 'quizsettings', get_string('quizpopups', 'mod_timedactivity'));
        $mform->addElement('textarea', 'quizdata', get_string('quizdata', 'mod_timedactivity'), array('rows' => 10, 'cols' => 80));
        $mform->setType('quizdata', PARAM_RAW);
        $mform->addHelpButton('quizdata', 'quizdata', 'mod_timedactivity');
        $mform->setDefault('quizdata', '[]');



        $mform->addElement('checkbox', 'retakesallowed', get_string('retakesallowed', 'mod_timedactivity'));
        $mform->setDefault('retakesallowed', 1);

        // Grade settings.
        $mform->addElement('header', 'gradesettings', get_string('gradesettings', 'mod_timedactivity'));
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_timedactivity'), array(
            0 => get_string('grademethod_none', 'mod_timedactivity'),
            1 => get_string('grademethod_quiz', 'mod_timedactivity'),
            2 => get_string('grademethod_time', 'mod_timedactivity'),
            3 => get_string('grademethod_both', 'mod_timedactivity')
        ));
        $mform->addElement('text', 'passinggrade', get_string('passinggrade', 'mod_timedactivity'));
        $mform->setType('passinggrade', PARAM_INT);
        $mform->setDefault('passinggrade', 70);
        $mform->addElement('checkbox', 'requiretimeforgrade', get_string('requiretimeforgrade', 'mod_timedactivity'));
        
        $attempts_options = [];
        $attempts_options[0] = 'Unlimited';
        for ($i = 1; $i <= 30; $i++) {
            $attempts_options[$i] = $i . ($i === 1 ? ' Attempt' : ' Attempts');
        }
        $mform->addElement('select', 'allowedattempts', get_string('allowedattempts', 'mod_timedactivity'), $attempts_options);
        $mform->addHelpButton('allowedattempts', 'allowedattempts', 'mod_timedactivity');
        $mform->setDefault('allowedattempts', 1);

        // Standard CM elements.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform = $this->_form;
        $mform->addElement('checkbox', 'completionrequiretime', '', get_string('completionmessage_checkbox', 'mod_timedactivity'));
        $mform->addElement('checkbox', 'completionpass', '', get_string('completionpass', 'mod_timedactivity'));
        $mform->addElement('checkbox', 'completionallquizzes', '', get_string('completionallquizzes', 'mod_timedactivity'));
        return ['completionrequiretime', 'completionpass', 'completionallquizzes'];
    }

    public function completion_rule_enabled($data) {
        return (!empty($data['completionrequiretime']) || !empty($data['completionpass']) || !empty($data['completionallquizzes']));
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $totalseconds = (int)($defaultvalues['requiredtime'] ?? 0);
        $value = $totalseconds;
        $unit = 1;
        if ($totalseconds > 0) {
            if ($totalseconds % 3600 == 0) {
                $value = $totalseconds / 3600;
                $unit = 3600;
            } else if ($totalseconds % 60 == 0) {
                $value = $totalseconds / 60;
                $unit = 60;
            }
        }
        $defaultvalues['timevalue'] = $value;
        $defaultvalues['timeunit'] = $unit;

        if ($totalseconds > 0) {
            $defaultvalues['completionrequiretime'] = 1;
        }

        if (isset($defaultvalues['id'])) {
            global $DB;
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_timedactivity', 'video', $defaultvalues['id']);
            $defaultvalues['videofile'] = $draftitemid;

            $questions = $DB->get_records('timedactivity_quiz', array('timedactivityid' => $defaultvalues['id']), 'timeposition ASC');
            $quizarray = array();
            foreach ($questions as $q) {
                $quizarray[] = array(
                    'time' => (int)$q->timeposition,
                    'text' => $q->questiontext,
                    'options' => json_decode($q->options),
                    'correct' => (int)$q->correctanswer,
                    'explanation' => $q->explanation
                );
            }
            $defaultvalues['quizdata'] = json_encode($quizarray);
        }
    }

    // Fallback helper to prevent "Call to undefined method" if some trait expects it.
    protected function get_suffixed_name($name) {
        if (method_exists($this, 'get_suffix')) {
            return $name . $this->get_suffix();
        }
        return $name;
    }
}