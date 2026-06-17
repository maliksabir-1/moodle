<?php
// local/advancedanalytics/classes/task/aggregate_analytics.php
// Scheduled task for data aggregation - UPDATED

namespace local_advancedanalytics\task;

defined('MOODLE_INTERNAL') || die();

class aggregate_analytics extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('pluginname', 'local_advancedanalytics') . ' - Data Aggregation';
    }
    
    
    /**
     * Queue this task to run as soon as possible via crontab
     */
    public static function queue_immediate() {
        try {
            $task = \core\task\manager::get_scheduled_task('\local_advancedanalytics\task\aggregate_analytics');
            if ($task) {
                $task->set_next_run_time(time());
                \core\task\manager::configure_scheduled_task($task);
            }
        } catch (\Exception $e) {
            // Silently fail to avoid blocking user login/actions
        }
    }

    public function execute() {
        global $CFG;
        
        $cron_enabled = get_config('local_advancedanalytics', 'cron_enabled');
        
        if (!$cron_enabled) {
            mtrace('Analytics aggregation is disabled in settings');
            return;
        }
        
        mtrace('Starting scheduled analytics aggregation...');
        
        // Include required files
        require_once($CFG->dirroot . '/local/advancedanalytics/classes/data_extractor.php');
        require_once($CFG->dirroot . '/local/advancedanalytics/classes/cron/data_sync.php');
        
        // Run full data sync
        \local_advancedanalytics\cron\data_sync::sync_all(true);
        
        mtrace('Scheduled analytics aggregation completed');
        
        return true;
    }
}