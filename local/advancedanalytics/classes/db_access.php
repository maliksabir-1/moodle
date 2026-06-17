<?php
// local/advancedanalytics/classes/db_access.php
// Database access utilities

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class db_access {
    
    /**
     * Get departments list
     */
    public static function get_departments() {
        global $DB;
        
        // Get departments from user table AND cached stats to ensure full coverage
        $sql = "SELECT department, department as d2
                FROM (
                    SELECT DISTINCT department FROM {user} WHERE department IS NOT NULL AND department != ''
                    UNION
                    SELECT DISTINCT department FROM {local_aa_dept_stats} WHERE department IS NOT NULL AND department != ''
                ) as depts
                ORDER BY department ASC";
                
        return $DB->get_records_sql_menu($sql);
    }
    
    /**
     * Get courses list
     */
    public static function get_courses() {
        global $DB;
        
        return $DB->get_records_menu('course', 
            ['visible' => 1], 
            'fullname', 
            'id, fullname'
        );
    }
    
    /**
     * Clear analytics cache
     */
    public static function clear_cache() {
        global $DB;
        
        // Delete old aggregated data
        $retention_days = get_config('local_advancedanalytics', 'data_retention_days') ?: 365;
        $cutoff = time() - ($retention_days * 24 * 3600);
        
        $DB->delete_records_select('local_aa_summary', 'date < ?', [$cutoff]);
        $DB->delete_records_select('local_aa_user_perf', 'last_calculated < ?', [$cutoff]);
        
        return true;
    }
}