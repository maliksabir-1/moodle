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
 * Quiz access rule that allows users to purchase extra attempts via Point Badges XP.
 *
 * HOW IT WORKS (critical design note):
 * ─────────────────────────────────────
 * Moodle's access_manager collects block messages from EVERY active rule.
 * If the built-in `quizaccess_numattempts` rule fires, it blocks at the
 * admin-set default limit — even if our rule returns false (allow).
 *
 * Solution: In make(), we inflate the shared in-memory quiz->attempts
 * property to (default + extra_remaining). Since $this->quiz in every rule
 * points to the same stdClass object, the numattempts rule then sees the
 * higher total and allows the extra attempts naturally. The database record
 * is NEVER modified — only the in-memory object.
 *
 * Attempt consumption tracking:
 * ─────────────────────────────
 * current_attempt_finished() is called by Moodle after each attempt is
 * submitted. We compare the new completed-attempt count against the
 * original DB default to decide if an extra attempt was just consumed.
 *
 * @package   local_point_badges
 * @copyright 2026 Point Badges Plugin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_point_badges_accessrule_extra_attempts extends access_rule_base {

    /**
     * Create a rule instance for this quiz.
     *
     * CRITICAL: We inflate $quiz->attempts in memory here so that
     * quizaccess_numattempts sees the correct expanded limit.
     * The DB record is never touched.
     *
     * @param quiz_settings $quizobj
     * @param int           $timenow
     * @param bool          $canignoretimelimits
     * @return self always returns an instance so description() always runs.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        global $USER;

        // get_quiz() returns a reference to the shared in-memory stdClass.
        // Modifying ->attempts here affects ALL other rules that access
        // $this->quiz->attempts (including quizaccess_numattempts).
        $quiz             = $quizobj->get_quiz();
        $default_attempts = (int)$quiz->attempts;

        if ($default_attempts > 0 && !empty($USER->id)) {
            $extra_remaining = \local_point_badges\quiz_manager::get_remaining_extra_attempts(
                $USER->id, $quiz->id
            );

            if ($extra_remaining > 0) {
                // Inflate so numattempts allows up to default + purchased extras.
                $quiz->attempts = $default_attempts + $extra_remaining;
            }
        }

        // Always return an instance so description() + current_attempt_finished() fire.
        return new self($quizobj, $timenow);
    }

    // ──────────────────────────────────────────────────────────────
    // After each attempt is submitted, mark extra attempt as consumed
    // ──────────────────────────────────────────────────────────────

    /**
     * Called by Moodle immediately after an attempt is finished/submitted.
     *
     * We query the DATABASE for the original default attempts (our in-memory
     * value may already be inflated). If the user has now completed more
     * attempts than the default allows, one extra purchased attempt is used.
     */
    public function current_attempt_finished() {
        global $USER, $DB;

        if (empty($USER->id)) {
            return;
        }

        // Fetch original (admin-set) attempts from DB — never affected by our inflation.
        $original_default = (int)$DB->get_field('quiz', 'attempts', ['id' => $this->quiz->id]);

        if ($original_default === 0) {
            // Quiz has unlimited attempts — nothing to track.
            return;
        }

        // How many attempts has this user now finished?
        $finished_attempts = quiz_get_user_attempts($this->quiz->id, $USER->id, 'finished');
        $num_finished      = count($finished_attempts);

        // Pull tracking data for extra attempts.
        $extra_purchased = \local_point_badges\quiz_manager::get_total_extra_attempts(
            $USER->id, $this->quiz->id
        );
        $extra_used = \local_point_badges\quiz_manager::get_used_extra_attempts(
            $USER->id, $this->quiz->id
        );

        // If the user now has more finished attempts than the default allows,
        // and they still have unused purchased extras, consume one.
        if ($extra_purchased > 0
            && $num_finished > $original_default
            && $extra_used < $extra_purchased
        ) {
            \local_point_badges\quiz_manager::mark_extra_attempt_used($USER->id, $this->quiz->id);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Info panel shown on the quiz view page
    // ──────────────────────────────────────────────────────────────

    /**
     * Return an HTML description shown above the "Start attempt" button,
     * giving the student a clear breakdown of their attempt allowance.
     */
    public function description() {
        global $USER, $DB;

        if (empty($USER->id)) {
            return '';
        }

        // Use the original DB value for "default attempts".
        $original_default = (int)$DB->get_field('quiz', 'attempts', ['id' => $this->quiz->id]);

        if ($original_default === 0) {
            return ''; // Unlimited — nothing useful to show.
        }

        $extra_purchased = \local_point_badges\quiz_manager::get_total_extra_attempts(
            $USER->id, $this->quiz->id
        );
        $extra_used     = \local_point_badges\quiz_manager::get_used_extra_attempts(
            $USER->id, $this->quiz->id
        );
        $extra_remaining = max(0, $extra_purchased - $extra_used);
        $total_allowed   = $original_default + $extra_remaining;

        $finished_attempts = quiz_get_user_attempts($this->quiz->id, $USER->id, 'finished');
        $attempts_taken    = count($finished_attempts);
        $attempts_left     = max(0, $total_allowed - $attempts_taken);

        // ── Main info box ──
        $html  = '<div style="background:#e3f2fd;border:1px solid #2196f3;border-radius:10px;';
        $html .= 'padding:15px;margin:10px 0;font-size:0.95em;">';
        $html .= '<strong>📊 Quiz Attempt Information</strong><br><br>';

        // Row helper
        $row = function($label, $value, $color = '') use (&$html) {
            $style = $color ? "color:{$color};font-weight:bold;" : '';
            $html .= "<span style='{$style}'>• <strong>{$label}:</strong> {$value}</span><br>";
        };

        $row('Default attempts (admin setting)', $original_default);

        if ($extra_purchased > 0) {
            $row('Extra attempts purchased',  $extra_purchased, '#1565c0');
            $row('Extra attempts used',       $extra_used);
            $row('Extra attempts remaining',  $extra_remaining, $extra_remaining > 0 ? '#2e7d32' : '#c62828');
        }

        $row('Total attempts allowed',  $total_allowed, '#4a148c');
        $row('Attempts taken',          $attempts_taken);
        $row('Attempts remaining',      $attempts_left, $attempts_left > 0 ? '#2e7d32' : '#c62828');

        $html .= '<br>';

        // ── Status banner ──
        if ($attempts_left === 0) {
            // Completely blocked.
            $html .= '<div style="padding:8px;background:#f44336;color:white;border-radius:5px;">';
            $html .= '🚫 <strong>No attempts remaining.</strong> ';
            if ($extra_purchased === 0) {
                $shopurl = (new moodle_url('/local/point_badges/shop.php'))->out();
                $html .= '<a href="' . $shopurl . '" style="color:white;text-decoration:underline;">';
                $html .= 'Purchase extra attempts in the Reward Shop!</a>';
            } else {
                $html .= 'You have used all ' . $total_allowed . ' allowed attempt(s).';
            }
            $html .= '</div>';

        } elseif ($extra_remaining > 0 && $attempts_taken >= $original_default) {
            // Currently inside extra attempts.
            $used_extras = $extra_used + 1; // about to use next
            $html .= '<div style="padding:8px;background:#ff9800;color:white;border-radius:5px;">';
            $html .= '🎯 <strong>Using purchased extra attempts.</strong> ';
            $html .= 'Your ' . $original_default . ' default attempt(s) are used. ';
            $html .= 'Each new attempt now consumes one of your <strong>' . $extra_remaining . '</strong> remaining purchased extra(s).';
            $html .= '</div>';

        } elseif ($attempts_left > 0 && $attempts_taken < $original_default) {
            // Still within default attempts.
            $remaining_default = $original_default - $attempts_taken;
            $html .= '<div style="padding:8px;background:#43a047;color:white;border-radius:5px;">';
            $html .= '✅ <strong>' . $remaining_default . ' default attempt(s) remaining.</strong> ';
            if ($extra_purchased === 0) {
                $shopurl = (new moodle_url('/local/point_badges/shop.php'))->out();
                $html .= '<a href="' . $shopurl . '" style="color:white;text-decoration:underline;">';
                $html .= 'Need more? Buy extra attempts with XP!</a>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    // ──────────────────────────────────────────────────────────────
    // These methods are handled by numattempts (which sees our
    // inflated $quiz->attempts), but we override is_finished() to
    // be safe and consistent with the inflated total.
    // ──────────────────────────────────────────────────────────────

    /**
     * Is the user permanently finished (no more attempts possible)?
     * Uses the already-inflated $this->quiz->attempts for consistency.
     */
    public function is_finished($numprevattempts, $lastattempt) {
        // $this->quiz->attempts is already = default + extra_remaining (inflated in make()).
        // If default was 0 (unlimited), is_finished should be false.
        if ($this->quiz->attempts == 0) {
            return false;
        }
        return $numprevattempts >= $this->quiz->attempts;
    }

    /**
     * prevent_new_attempt is NOT needed here because quizaccess_numattempts
     * handles it using the inflated $quiz->attempts value we set in make().
     * We return false to not add any additional block.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        return false;
    }
}