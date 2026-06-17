<?php
// local/advancedanalytics/db/upgrade.php
// Handles plugin upgrades - FIXED VERSION

defined('MOODLE_INTERNAL') || die();

function xmldb_local_advancedanalytics_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    // For NEW installations, skip all upgrade code
    // Tables will be created by install.xml
    if ($oldversion == 0) {
        return true;
    }
    
    // Only run upgrade code if upgrading from a previous version
    if ($oldversion < 2024100103) {
        
        // Check if tables exist before trying to modify them
        $table = new xmldb_table('local_aa_summary');
        
        if ($dbman->table_exists($table)) {
            // Add new_users field to local_aa_summary table
            $field = new xmldb_field('new_users', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'active_users');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            // Add logins_count field
            $field = new xmldb_field('logins_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'engagement_score');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        
        // Create course cache table if it doesn't exist
        $table = new xmldb_table('local_aa_course_cache');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('total_enrolled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completed_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completion_rate', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('avg_grade', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('active_participants', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('avg_time_spent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('last_activity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('course_date', XMLDB_KEY_UNIQUE, ['courseid', 'date']);
            $table->add_index('completion_idx', XMLDB_INDEX_NOTUNIQUE, ['completion_rate']);
            $table->add_index('date_idx', XMLDB_INDEX_NOTUNIQUE, ['date']);
            
            $dbman->create_table($table);
        }
        
        // Update local_aa_dept_stats if table exists
        $table = new xmldb_table('local_aa_dept_stats');
        
        if ($dbman->table_exists($table)) {
            // Add high_performers field
            $field = new xmldb_field('high_performers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'at_risk_count');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            // Add low_performers field
            $field = new xmldb_field('low_performers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'high_performers');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        
        // Update local_aa_user_perf if table exists
        $table = new xmldb_table('local_aa_user_perf');
        
        if ($dbman->table_exists($table)) {
            // Add login_count field
            $field = new xmldb_field('login_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'last_access');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            
            // Add predicted_completion field
            $field = new xmldb_field('predicted_completion', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0', 'risk_level');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        
        // Main savepoint reached
        upgrade_plugin_savepoint(true, 2024100103, 'local', 'advancedanalytics');
    }
    
    if ($oldversion < 2024100106) {
        // Create scheduled reports table
        $table = new xmldb_table('local_aa_reports');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('report_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'executive');
            $table->add_field('frequency', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'weekly');
            $table->add_field('format', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'pdf');
            $table->add_field('recipients', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('last_sent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2024100106, 'local', 'advancedanalytics');
    }
    
    return true;
}