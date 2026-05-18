<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/timedactivity/backup/moodle2/backup_timedactivity_stepslib.php');

/**
 * Provides all the settings and steps to perform one complete backup of the timedactivity activity.
 */
class backup_timedactivity_activity_task extends backup_activity_task {

    /**
     * Define the settings of the activity task.
     */
    protected function define_my_settings() {
        // No custom settings for this activity module.
    }

    /**
     * Define the steps of the activity task.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_timedactivity_activity_structure_step('timedactivity_structure', 'timedactivity.xml'));
    }

    /**
     * Encodes content links.
     *
     * @param string $content
     * @return string
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of timedactivities
        $pattern = "/(" . $base . "\/mod\/timedactivity\/index\.php\?id\=)([0-9]+)/";
        $content = preg_replace($pattern, '$@TIMEDACTIVITYINDEX*$2@$', $content);

        // Link to timedactivity view page
        $pattern = "/(" . $base . "\/mod\/timedactivity\/view\.php\?id\=)([0-9]+)/";
        $content = preg_replace($pattern, '$@TIMEDACTIVITYVIEWBYID*$2@$', $content);

        return $content;
    }
}
