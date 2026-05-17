<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_blogpost_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2026042300) {
        // Define table block_blogpost_prefs to be created.
        $table = new xmldb_table('block_blogpost_prefs');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('email_notifications', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('notify_tags', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_UNIQUE, ['userid']);
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        // Add tags field to block_blogpost table.
        $table = new xmldb_table('block_blogpost');
        $field = new xmldb_field('tags', XMLDB_TYPE_TEXT, null, null, null, null, null, 'blog_text');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'tags');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Blogpost savepoint reached.
        upgrade_block_savepoint(true, 2026042300, 'blogpost');
    }
    
    return true;
}