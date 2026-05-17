<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_blogpost_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    return true;
}
