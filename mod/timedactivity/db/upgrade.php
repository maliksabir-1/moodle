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

defined('MOODLE_INTERNAL') || die();

function xmldb_timedactivity_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026043009) {
        // Define field completiontime to be added to timedactivity.
        $table = new xmldb_table('timedactivity');
        $field = new xmldb_field('completiontime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'requiredtime');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026043009, 'timedactivity');
    }

    if ($oldversion < 2026043010) {
        // Define field videoposition to be added to timedactivity_tracking.
        $table = new xmldb_table('timedactivity_tracking');
        $field = new xmldb_field('videoposition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'totaltimespent');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026043010, 'timedactivity');
    }

    if ($oldversion < 2026043011) {
        // Add missing fields to timedactivity table (for old installations).
        $table = new xmldb_table('timedactivity');
        
        $fields = [
            'videosource' => new xmldb_field('videosource', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'local', 'completiontime'),
            'youtubeurl' => new xmldb_field('youtubeurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'videosource'),
            'matchduration' => new xmldb_field('matchduration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'youtubeurl'),
        ];

        foreach ($fields as $name => $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026043011, 'timedactivity');
    }

    if ($oldversion < 2026043012) {
        // Create quiz tables if they don't exist (for very old installations).
        
        // Define table timedactivity_quiz to be created.
        $table = new xmldb_table('timedactivity_quiz');

        // Adding fields to table timedactivity_quiz.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timedactivityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeposition', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('questiontext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null);
        $table->add_field('options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null);
        $table->add_field('correctanswer', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('explanation', XMLDB_TYPE_TEXT, null, null, null, null);

        // Adding keys to table timedactivity_quiz.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('timedactivityid', XMLDB_KEY_FOREIGN, ['timedactivityid'], 'timedactivity', ['id']);

        // Conditionally launch create table for timedactivity_quiz.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table timedactivity_quiz_attempts to be created.
        $table = new xmldb_table('timedactivity_quiz_attempts');

        // Adding fields to table timedactivity_quiz_attempts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('answer', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '-1');
        $table->add_field('iscorrect', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeattempted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table timedactivity_quiz_attempts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quizid', XMLDB_KEY_FOREIGN, ['quizid'], 'timedactivity_quiz', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for timedactivity_quiz_attempts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026043012, 'timedactivity');
    }

    if ($oldversion < 2026043013) {
        $table = new xmldb_table('timedactivity');
        
        // Add grade and certificate fields
        $fields = [
            'grademethod' => new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'matchduration'),
            'requiretimeforgrade' => new xmldb_field('requiretimeforgrade', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grademethod'),
            'retakesallowed' => new xmldb_field('retakesallowed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'requiretimeforgrade'),
            'randomizequestions' => new xmldb_field('randomizequestions', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'retakesallowed'),
            'timelimitperquestion' => new xmldb_field('timelimitperquestion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'randomizequestions'),
            'enablecertificate' => new xmldb_field('enablecertificate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timelimitperquestion'),
            'passinggrade' => new xmldb_field('passinggrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '70', 'enablecertificate'),
        ];

        foreach ($fields as $name => $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Add explanation field to timedactivity_quiz (if table exists and field doesn't)
        $table_quiz = new xmldb_table('timedactivity_quiz');
        if ($dbman->table_exists($table_quiz)) {
            $field_explanation = new xmldb_field('explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'correctanswer');
            if (!$dbman->field_exists($table_quiz, $field_explanation)) {
                $dbman->add_field($table_quiz, $field_explanation);
            }
        }

        // Drop unique key from timedactivity_quiz_attempts if it exists
        $table_attempts = new xmldb_table('timedactivity_quiz_attempts');
        if ($dbman->table_exists($table_attempts)) {
            $key = new xmldb_key('unique_attempt', XMLDB_KEY_UNIQUE, ['quizid', 'userid']);
            try {
                $dbman->drop_key($table_attempts, $key);
            } catch (Exception $e) {
                // Key might not exist, that's ok
            }
        }

        upgrade_mod_savepoint(true, 2026043013, 'timedactivity');
    }

    if ($oldversion < 2026051800) {
        // Define field attempts to be added to timedactivity_tracking.
        $table = new xmldb_table('timedactivity_tracking');
        $field = new xmldb_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'videoposition');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026051800, 'timedactivity');
    }

    if ($oldversion < 2026051801) {
        // Define field allowedattempts to be added to timedactivity.
        $table = new xmldb_table('timedactivity');
        $field = new xmldb_field('allowedattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timelimitperquestion');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026051801, 'timedactivity');
    }

    if ($oldversion < 2026051802) {
        // Define field maxquizattempts to be added to timedactivity.
        $table = new xmldb_table('timedactivity');
        $field = new xmldb_field('maxquizattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'allowedattempts');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026051802, 'timedactivity');
    }

    return true;
}