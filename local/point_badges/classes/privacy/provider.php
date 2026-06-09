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

namespace local_point_badges\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    
    /**
     * Get metadata about this plugin's data
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection) : \core_privacy\local\metadata\collection {
        
        $collection->add_database_table('local_pb_user_xp', [
            'userid' => 'privacy:metadata:userid',
            'total_xp' => 'privacy:metadata:total_xp',
            'current_level' => 'privacy:metadata:current_level',
            'courseid' => 'privacy:metadata:courseid',
        ], 'privacy:metadata:user_xp');
        
        $collection->add_database_table('local_pb_xp_log', [
            'userid' => 'privacy:metadata:userid',
            'xp_amount' => 'privacy:metadata:xp_amount',
            'reason' => 'privacy:metadata:reason',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:xp_log');
        
        $collection->add_database_table('local_pb_streak', [
            'userid' => 'privacy:metadata:userid',
            'current_streak' => 'privacy:metadata:current_streak',
            'max_streak' => 'privacy:metadata:max_streak',
        ], 'privacy:metadata:streak');
        
        // $collection->add_database_table('local_pb_certificates', [
        //     'userid' => 'privacy:metadata:userid',
        //     'certificate_name' => 'privacy:metadata:certificate_name',
        //     'issued_date' => 'privacy:metadata:issued_date',
        // ], 'privacy:metadata:certificates');
        
        $collection->add_user_preference('coupon_discount', 'privacy:metadata:preference:coupon_discount');
        $collection->add_user_preference('extra_quiz_attempts', 'privacy:metadata:preference:extra_quiz_attempts');
        
        return $collection;
    }
    
    /**
     * Export user preferences
     */
    public static function export_user_preferences(int $userid) {
        $discount = get_user_preferences('coupon_discount', null, $userid);
        if ($discount !== null) {
            \core_privacy\local\request\writer::export_user_preference(
                'local_point_badges',
                'coupon_discount',
                $discount,
                get_string('privacy:preference:coupon_discount', 'local_point_badges')
            );
        }
        
        $extraattempts = get_user_preferences('extra_quiz_attempts', null, $userid);
        if ($extraattempts !== null) {
            \core_privacy\local\request\writer::export_user_preference(
                'local_point_badges',
                'extra_quiz_attempts',
                $extraattempts,
                get_string('privacy:preference:extra_quiz_attempts', 'local_point_badges')
            );
        }
    }
}