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

function xmldb_local_point_badges_install() {
    global $DB;
    
    // Insert default level definitions
    $levels = [
        ['level_number' => 1, 'level_name' => 'Beginner', 'min_xp' => 0, 'max_xp' => 100, 'badge_color' => '#cd7f32'],
        ['level_number' => 2, 'level_name' => 'Intermediate', 'min_xp' => 101, 'max_xp' => 300, 'badge_color' => '#c0c0c0'],
        ['level_number' => 3, 'level_name' => 'Advanced', 'min_xp' => 301, 'max_xp' => 700, 'badge_color' => '#ffd700'],
        ['level_number' => 4, 'level_name' => 'Expert', 'min_xp' => 701, 'max_xp' => 999999, 'badge_color' => '#e5e4e2'],
    ];
    
    foreach ($levels as $level) {
        $DB->insert_record('local_pb_levels', (object)$level);
    }
    
   // In install.php, update the challenges array:
$challenges = [
    [
        'name' => 'Quiz Taker',
        'description' => 'Attempt 1 quiz today',
        'event_name' => 'quiz_completed',
        'required_count' => 1,
        'xp_reward' => 25,
        'active' => 1,
    ],
    [
        'name' => 'Video Watcher',
        'description' => 'Watch 2 videos today',
        'event_name' => 'scorm_completed',  // Assuming SCORM is used for videos
        'required_count' => 2,
        'xp_reward' => 40,
        'active' => 1,
    ],
    [
        'name' => 'Forum Participant',
        'description' => 'Participate in forum today',
        'event_name' => 'forum_post',
        'required_count' => 1,
        'xp_reward' => 15,
        'active' => 1,
    ],
    [
        'name' => 'Assignment Submitter',
        'description' => 'Complete assignment before deadline',
        'event_name' => 'assignment_submitted',
        'required_count' => 1,
        'xp_reward' => 30,
        'active' => 1,
    ]
];
    
    foreach ($challenges as $challenge) {
        // Check if challenge already exists to avoid duplicates
        if (!$DB->record_exists('local_pb_challenge', ['name' => $challenge['name']])) {
            $DB->insert_record('local_pb_challenge', (object)$challenge);
        }
    }
}