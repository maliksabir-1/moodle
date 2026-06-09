<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class quiz_manager {
    
    /**
     * Grant extra quiz attempt (purchased from shop)
     */
    public static function grant_extra_attempt($userid, $quizid, $xp_cost = 100) {
        global $DB;
        
        try {
            // Check XP balance
            $user_level = manager::get_user_level_info($userid, 0);
            if ($user_level['total_xp'] < $xp_cost) {
                return ['success' => false, 'message' => 'Not enough XP. You have ' . $user_level['total_xp'] . ' XP but need ' . $xp_cost . ' XP.'];
            }
            
            // Check if quiz exists
            $quiz = $DB->get_record('quiz', ['id' => $quizid]);
            if (!$quiz) {
                return ['success' => false, 'message' => 'Invalid quiz selected.'];
            }
            
            // Get or create extra attempts record for specific quiz
            $extra = $DB->get_record('local_pb_extra_attempts', [
                'userid' => $userid,
                'quizid' => $quizid
            ]);
            
            if (!$extra) {
                $extra = new \stdClass();
                $extra->userid = $userid;
                $extra->quizid = $quizid;
                $extra->extra_attempts = 1;
                $extra->used_attempts = 0;
                $DB->insert_record('local_pb_extra_attempts', $extra);
                $new_count = 1;
            } else {
                $extra->extra_attempts++;
                $DB->update_record('local_pb_extra_attempts', $extra);
                $new_count = $extra->extra_attempts;
            }
            
            // Set 7-day expiration for the attempts on this specific quiz
            \local_point_badges\coupon_redemption::set_user_preference('extra_attempt_expiry_' . $quizid, strtotime('+7 days'), $userid);
            
            // Set Global purchase timestamp to restrict to once per 7 days
            \local_point_badges\coupon_redemption::set_user_preference('last_extra_attempt_purchase', time(), $userid);
            
            // Deduct XP
            $deducted = manager::deduct_xp($userid, 0, $xp_cost, 'extra_quiz_attempt_quiz_' . $quizid);
            
            if (!$deducted) {
                if ($new_count == 1) {
                    $DB->delete_records('local_pb_extra_attempts', ['userid' => $userid, 'quizid' => $quizid]);
                } else {
                    $extra->extra_attempts = $new_count - 1;
                    $DB->update_record('local_pb_extra_attempts', $extra);
                }
                return ['success' => false, 'message' => 'Failed to deduct XP.'];
            }
            
            $available = $extra->extra_attempts - ($extra->used_attempts ?? 0);
            
            return [
                'success' => true, 
                'message' => '✅ Extra attempt purchased! Total purchased: ' . $new_count . '. Available to use: ' . $available
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get total extra attempts purchased
     */
    public static function get_total_extra_attempts($userid, $quizid) {
        global $DB;
        
        // Get quiz-specific attempts
        $specific = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => $quizid
        ]);
        $specific_total = $specific ? (int)$specific->extra_attempts : 0;
        
        // Get generic attempts (quizid = 0 means "any quiz")
        $generic = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => 0
        ]);
        $generic_total = $generic ? (int)$generic->extra_attempts : 0;
        
        return $specific_total + $generic_total;
    }
    
    /**
     * Get used extra attempts
     */
    public static function get_used_extra_attempts($userid, $quizid) {
        global $DB;
        
        $specific = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => $quizid
        ]);
        $specific_used = $specific ? (int)($specific->used_attempts ?? 0) : 0;
        
        $generic = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => 0
        ]);
        $generic_used = $generic ? (int)($generic->used_attempts ?? 0) : 0;
        
        return $specific_used + $generic_used;
    }
    
    /**
     * Get remaining extra attempts
     * This is the key method used by the access rule
     */
    public static function get_remaining_extra_attempts($userid, $quizid) {
        global $DB;
        
        // Check for quiz-specific extra attempts
        $specific = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => $quizid
        ]);
        
        $specific_remaining = 0;
        if ($specific) {
            $expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('extra_attempt_expiry_' . $quizid, 0, $userid);
            if ($expiry == 0 || $expiry > time()) {
                $specific_remaining = max(0, $specific->extra_attempts - ($specific->used_attempts ?? 0));
            }
        }
        
        // Check for generic extra attempts (quizid = 0)
        $generic = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => 0
        ]);
        
        $generic_remaining = 0;
        if ($generic) {
            $expiry = (int)\local_point_badges\coupon_redemption::get_user_preference('extra_attempt_expiry_0', 0, $userid);
            if ($expiry == 0 || $expiry > time()) {
                $generic_remaining = max(0, $generic->extra_attempts - ($generic->used_attempts ?? 0));
            }
        }
        
        // Return total remaining (specific + generic)
        return $specific_remaining + $generic_remaining;
    }
    
    /**
     * Mark an extra attempt as used
     * Priority: Use quiz-specific attempts first, then generic
     */
    public static function mark_extra_attempt_used($userid, $quizid) {
        global $DB;
        
        // First try to use quiz-specific attempts
        $specific = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => $quizid
        ]);
        
        if ($specific && $specific->extra_attempts > ($specific->used_attempts ?? 0)) {
            $specific->used_attempts = ($specific->used_attempts ?? 0) + 1;
            $DB->update_record('local_pb_extra_attempts', $specific);
            return true;
        }
        
        // If no specific attempts, use generic attempts (quizid = 0)
        $generic = $DB->get_record('local_pb_extra_attempts', [
            'userid' => $userid,
            'quizid' => 0
        ]);
        
        if ($generic && $generic->extra_attempts > ($generic->used_attempts ?? 0)) {
            $generic->used_attempts = ($generic->used_attempts ?? 0) + 1;
            $DB->update_record('local_pb_extra_attempts', $generic);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all extra attempts for display in shop
     */
    public static function get_user_all_extra_attempts($userid) {
        global $DB;
        
        $sql = "SELECT ea.*, 
                       COALESCE(q.name, 'Any Quiz') as quiz_name, 
                       COALESCE(c.fullname, 'All Courses') as course_name,
                       (ea.extra_attempts - COALESCE(ea.used_attempts, 0)) as remaining
                FROM {local_pb_extra_attempts} ea
                LEFT JOIN {quiz} q ON q.id = ea.quizid AND ea.quizid > 0
                LEFT JOIN {course} c ON c.id = q.course
                WHERE ea.userid = :userid 
                AND (ea.extra_attempts - COALESCE(ea.used_attempts, 0)) > 0
                ORDER BY ea.quizid ASC, ea.extra_attempts DESC";
        
        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }
}
?>