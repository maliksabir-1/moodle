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
        $table->add_field('email_updates', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
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
        
        upgrade_block_savepoint(true, 2026042300, 'blogpost');
    }
    
    if ($oldversion < 2026052200) {
        // Rename email_notifications to email_updates in block_blogpost_prefs table
        $table = new xmldb_table('block_blogpost_prefs');
        
        // Check if old field exists and rename it
        $oldfield = new xmldb_field('email_notifications', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->rename_field($table, $oldfield, 'email_updates');
        }
        
        // If field doesn't exist or rename failed, ensure it exists
        $newfield = new xmldb_field('email_updates', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }
        
        upgrade_block_savepoint(true, 2026052200, 'blogpost');
    }
    
    if ($oldversion < 2026052201) {
        // Remove notify_tags field from block_blogpost_prefs table
        $table = new xmldb_table('block_blogpost_prefs');
        $field = new xmldb_field('notify_tags');
        
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        upgrade_block_savepoint(true, 2026052201, 'blogpost');
    }
    
    if ($oldversion < 2026052202) {
        $table = new xmldb_table('block_blogpost_replies');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reply_text', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('postid', XMLDB_KEY_FOREIGN, ['postid'], 'block_blogpost', ['id']);
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        upgrade_block_savepoint(true, 2026052202, 'blogpost');
    }
    
    if ($oldversion < 2026052203) {
        upgrade_block_savepoint(true, 2026052203, 'blogpost');
    }

    if ($oldversion < 2026052213) {
        $table = new xmldb_table('block_blogpost_replies');
        $field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'postid');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_block_savepoint(true, 2026052213, 'blogpost');
    }
    
    return true;
}