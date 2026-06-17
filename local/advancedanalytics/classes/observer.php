<?php
// local/advancedanalytics/classes/observer.php
// Event observers

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    /**
     * Handle user login
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        // Trigger analytics update
        \local_advancedanalytics\task\aggregate_analytics::queue_immediate();
    }
    
    /**
     * Handle course completion
     */
    public static function course_completed(\core\event\course_completed $event) {
        // Update user performance cache
        $data = $event->get_data();
        if (isset($data['relateduserid'])) {
            \local_advancedanalytics\learner_scoring::calculate_learner_score($data['relateduserid']);
        }
    }
}