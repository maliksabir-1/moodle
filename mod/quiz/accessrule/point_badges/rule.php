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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;

/**
 * Quiz access rule: allow extra attempts purchased via Point Badges XP.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  HOW THE ATTEMPT LIMIT IS EXTENDED  (critical design note)
 * ═══════════════════════════════════════════════════════════════════
 *
 * Moodle's access_manager::prevent_new_attempt() collects block messages
 * from EVERY active rule and returns ALL of them.  Returning false from
 * our rule does NOT cancel a block already added by quizaccess_numattempts.
 *
 * The solution used here:
 *   In make(), BEFORE any rule is instantiated, we mutate the shared
 *   in-memory quiz stdClass object so that:
 *
 *       $quiz->attempts = (admin default) + (user's remaining extra)
 *
 *   Because access_rule_base stores $this->quiz as a reference to the
 *   same stdClass, quizaccess_numattempts sees the inflated total and
 *   allows additional attempts automatically.  The DB record is NEVER
 *   written — only the in-memory object is changed for this request.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  ATTEMPT CONSUMPTION TRACKING
 * ═══════════════════════════════════════════════════════════════════
 *
 * current_attempt_finished() is the official Moodle hook called after
 * each attempt is submitted.  We query the DB for the *original*
 * admin-set attempts value (always unaffected by our in-memory inflation)
 * and compare it to the user's total finished attempts to decide whether
 * an extra purchased attempt was just consumed.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  WORKED EXAMPLE
 * ═══════════════════════════════════════════════════════════════════
 *
 *   Admin sets:          quiz.attempts = 1   (DB)
 *   User purchases:      2 extra attempts    (local_pb_extra_attempts)
 *   Our make() sets:     quiz.attempts = 3   (in-memory only)
 *
 *   Attempt 1 → numattempts sees 3 total allowed, 0 taken → ✅ allowed
 *   Attempt 2 → numattempts sees 3 total allowed, 1 taken → ✅ allowed
 *               current_attempt_finished: finished(1) > default(1)? NO → no extra consumed
 *   (Wait — after attempt 1, finished=1. Is 1 > 1? No. So no extra consumed yet.)
 *
 *   Actually step through correctly:
 *   After Attempt 1 finishes:  finished=1, default=1  → 1>1? No  → default attempt used, no extra consumed ✓
 *   After Attempt 2 finishes:  finished=2, default=1  → 2>1? Yes → extra_used was 0 < purchased 2 → consume 1 ✓
 *   After Attempt 3 finishes:  finished=3, default=1  → 3>1? Yes → extra_used was 1 < purchased 2 → consume 1 ✓
 *   Attempt 4:     numattempts sees 3 allowed (default 1 + remaining 0), 3 taken → ❌ blocked ✓
 *
 * @package   quizaccess_point_badges
 * @copyright 2026 Point Badges Plugin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_point_badges extends access_rule_base {

    /**
     * Create a rule instance and inflate quiz->attempts if the user has extra
     * purchased attempts.
     *
     * We always return an instance (never null) so that:
     *   - description() always fires to show the info panel.
     *   - current_attempt_finished() always fires to track consumption.
     *
     * @param quiz_settings $quizobj
     * @param int  $timenow
     * @param bool $canignoretimelimits
     * @return self
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $USER;

        if (!empty($USER->id)) {
            // get_quiz() returns the shared stdClass reference.
            // Modifying it here affects ALL rules in this request,
            // including quizaccess_numattempts.
            $quiz             = $quizobj->get_quiz();
            $default_attempts = (int)$quiz->attempts;

            if ($default_attempts > 0) {
                $extra_remaining = self::get_remaining_extra_attempts($USER->id, $quiz->id);

                if ($extra_remaining > 0) {
                    // Inflate: numattempts will now allow up to this total.
                    $quiz->attempts = $default_attempts + $extra_remaining;
                }
            }
        }

        return new self($quizobj, $timenow);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // After each attempt: track extra attempt consumption
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called by Moodle immediately after the user submits/finishes an attempt.
     *
     * Compares the updated count of finished attempts against the original
     * DB default to determine whether an extra purchased attempt was consumed.
     */
    public function current_attempt_finished() {
        global $USER, $DB;

        if (empty($USER->id)) {
            return;
        }

        // Read original (admin-set) attempts from DB — our in-memory value may be inflated.
        $original_default = (int) $DB->get_field('quiz', 'attempts', ['id' => $this->quiz->id]);

        if ($original_default === 0) {
            return; // Unlimited quiz — nothing to track.
        }

        // Total attempts this user has now completed (including the one just submitted).
        $finished_attempts = quiz_get_user_attempts($this->quiz->id, $USER->id, 'finished');
        $num_finished      = count($finished_attempts);

        // Read extra attempt tracking data.
        $extra_purchased = self::get_total_extra_attempts($USER->id, $this->quiz->id);
        $extra_used      = self::get_used_extra_attempts($USER->id, $this->quiz->id);

        // If the user has now completed more attempts than the default allows,
        // and they still have unused purchased extras, one extra was consumed.
        if ($extra_purchased > 0
            && $num_finished > $original_default
            && $extra_used < $extra_purchased
        ) {
            self::mark_extra_attempt_used($USER->id, $this->quiz->id);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Info panel shown above the Start Attempt button
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return an HTML string showing a breakdown of the user's attempt allowance.
     * Displayed on the quiz view page above the Start Attempt button.
     */
    public function description() {
        global $USER, $DB;

        if (empty($USER->id)) {
            return '';
        }

        // Use DB value — not the inflated in-memory one — for "default attempts".
        $original_default = (int) $DB->get_field('quiz', 'attempts', ['id' => $this->quiz->id]);

        if ($original_default === 0) {
            return ''; // Unlimited attempts — nothing useful to show.
        }

        $extra_purchased = self::get_total_extra_attempts($USER->id, $this->quiz->id);
        $extra_used      = self::get_used_extra_attempts($USER->id, $this->quiz->id);
        $extra_remaining = max(0, $extra_purchased - $extra_used);
        $total_allowed   = $original_default + $extra_remaining;

        $finished_attempts = quiz_get_user_attempts($this->quiz->id, $USER->id, 'finished');
        $attempts_taken    = count($finished_attempts);
        $attempts_left     = max(0, $total_allowed - $attempts_taken);

        // ── Simple message panel ──────────────────────────────────────────────────
        $out  = '<div style="background:#e3f2fd;border:1px solid #2196f3;border-radius:10px;'
              . 'padding:15px 20px;margin:12px 0;font-size:0.93em;line-height:1.8;">';

        // ── Simple status message ────────────────────────────────────────────────────
        if ($attempts_left === 0) {
            // No attempts left at all.
            $out .= '<div style="padding:8px 12px;background:#f44336;color:#fff;'
                  . 'border-radius:6px;font-weight:bold;">';
            $out .= '🚫 No attempts remaining. ';
            if ($extra_purchased === 0) {
                $shop = (new moodle_url('/local/point_badges/shop.php'))->out();
                $out .= '<a href="' . $shop . '" style="color:#fff;text-decoration:underline;">'
                      . 'Purchase extra attempts in the Reward Shop →</a>';
            } else {
                $out .= 'You have used all ' . $total_allowed . ' allowed attempt(s).';
            }
            $out .= '</div>';

        } elseif ($extra_remaining > 0 && $attempts_taken >= $original_default) {
            // Currently within the extra-attempts zone.
            $out .= '<div style="padding:8px 12px;background:#ff9800;color:#fff;'
                  . 'border-radius:6px;">';
            $out .= '🎯 <strong>You are now using your purchased extra attempts.</strong><br>';
            $out .= 'You have <strong>' . $extra_remaining . ' remaining</strong> purchased extra attempt(s).';
            $out .= '</div>';

        } elseif ($attempts_left > 0 && $attempts_taken < $original_default) {
            // Still within default attempts.
            $remaining_default = $original_default - $attempts_taken;
            $out .= '<div style="padding:8px 12px;background:#43a047;color:#fff;'
                  . 'border-radius:6px;">';
            $out .= '✅ <strong>' . $remaining_default . ' default attempt(s) remaining.</strong>';
            if ($extra_purchased === 0) {
                $shop = (new moodle_url('/local/point_badges/shop.php'))->out();
                $out .= ' &nbsp;<a href="' . $shop . '" style="color:#fff;text-decoration:underline;">'
                      . 'Need more? Buy extra attempts with XP!</a>';
            }
            $out .= '</div>';
        }

        $out .= '</div>'; // main panel

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Standard rule methods
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Our rule does not add its own block message — quizaccess_numattempts
     * handles blocking using the inflated $quiz->attempts value we set in make().
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        return false;
    }

    /**
     * Is the user completely finished?
     * $this->quiz->attempts is already inflated by make(), so this is consistent.
     */
    public function is_finished($numprevattempts, $lastattempt) {
        if ((int)$this->quiz->attempts === 0) {
            return false; // Unlimited.
        }
        return $numprevattempts >= (int)$this->quiz->attempts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delegation to local_point_badges\quiz_manager
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get remaining (unused) extra attempts for this user on this quiz.
     */
    private static function get_remaining_extra_attempts(int $userid, int $quizid): int {
        if (!class_exists('\local_point_badges\quiz_manager')) {
            return 0;
        }
        return (int) \local_point_badges\quiz_manager::get_remaining_extra_attempts($userid, $quizid);
    }

    /**
     * Get total extra attempts ever purchased by this user for this quiz.
     */
    private static function get_total_extra_attempts(int $userid, int $quizid): int {
        if (!class_exists('\local_point_badges\quiz_manager')) {
            return 0;
        }
        return (int) \local_point_badges\quiz_manager::get_total_extra_attempts($userid, $quizid);
    }

    /**
     * Get how many extra attempts have already been consumed.
     */
    private static function get_used_extra_attempts(int $userid, int $quizid): int {
        if (!class_exists('\local_point_badges\quiz_manager')) {
            return 0;
        }
        return (int) \local_point_badges\quiz_manager::get_used_extra_attempts($userid, $quizid);
    }

    /**
     * Mark one extra attempt as consumed for this user on this quiz.
     */
    private static function mark_extra_attempt_used(int $userid, int $quizid): void {
        if (class_exists('\local_point_badges\quiz_manager')) {
            \local_point_badges\quiz_manager::mark_extra_attempt_used($userid, $quizid);
        }
    }
}