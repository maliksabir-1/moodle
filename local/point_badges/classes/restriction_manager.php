<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class restriction_manager {

    /**
     * Apply premium restriction to a course module and lock it via availability.
     */
    public static function apply_premium($cmid): void {
        self::apply_restriction($cmid, \availability_pointbadges\condition::RESTRICTION_PREMIUM);
    }

    /**
     * Apply VIP restriction to a course module and lock it via availability.
     */
    public static function apply_vip($cmid): void {
        self::apply_restriction($cmid, \availability_pointbadges\condition::RESTRICTION_VIP);
    }

    /**
     * Remove premium restriction from a course module.
     */
    public static function remove_premium($cmid): void {
        self::remove_restriction($cmid, \availability_pointbadges\condition::RESTRICTION_PREMIUM);
    }

    /**
     * Remove VIP restriction from a course module.
     */
    public static function remove_vip($cmid): void {
        self::remove_restriction($cmid, \availability_pointbadges\condition::RESTRICTION_VIP);
    }

    /**
     * Sync availability for all premium/VIP restrictions already stored in the database.
     */
    public static function sync_all_restrictions(): void {
        global $DB;

        $premium = $DB->get_records('local_pb_premium_restrictions');
        foreach ($premium as $record) {
            self::apply_premium($record->cmid);
        }

        $vip = $DB->get_records('local_pb_vip_restrictions');
        foreach ($vip as $record) {
            self::apply_vip($record->cmid);
        }
    }

    /**
     * @param int $cmid
     * @param string $restriction premium|vip
     */
    protected static function apply_restriction(int $cmid, string $restriction): void {
        global $CFG, $DB;

        self::bump_restriction_epoch($cmid, $restriction);
        self::clear_activity_unlocks($cmid);

        if (empty($CFG->enableavailability) || !self::availability_plugin_installed()) {
            return;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $newcondition = \availability_pointbadges\condition::get_json($restriction);
        $updated = self::merge_condition($cm->availability, $newcondition);

        self::save_availability($cm, $updated);
    }

    /**
     * @param int $cmid
     * @param string $restriction premium|vip
     */
    protected static function remove_restriction(int $cmid, string $restriction): void {
        global $CFG, $DB;

        if (empty($CFG->enableavailability) || !self::availability_plugin_installed()) {
            return;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $updated = self::remove_condition($cm->availability, $restriction);

        self::save_availability($cm, $updated);
    }

    /**
     * Persist availability and always rebuild course cache.
     */
    protected static function save_availability(\stdClass $cm, ?string $availability): void {
        global $DB;

        $cm->availability = $availability ?? '';
        $DB->update_record('course_modules', $cm);
        self::rebuild_cache($cm->course);
    }

    /**
     * Rebuild application and static modinfo caches for a course.
     */
    protected static function rebuild_cache(int $courseid): void {
        rebuild_course_cache($courseid, true);
        get_fast_modinfo($courseid, 0, true);
    }

    /**
     * Add or replace a pointbadges condition in the availability tree.
     */
    protected static function merge_condition(?string $availability, \stdClass $newcondition): ?string {
        $children = self::collect_non_pointbadges_children($availability);
        $children[] = $newcondition;

        if (empty($children)) {
            return null;
        }

        $tree = \core_availability\tree::get_root_json($children, \core_availability\tree::OP_AND, true);
        return json_encode($tree);
    }

    /**
     * Remove all pointbadges conditions of the given type from the availability tree.
     */
    protected static function remove_condition(?string $availability, string $restriction): ?string {
        if (empty($availability)) {
            return null;
        }

        $decoded = json_decode($availability);
        if (!$decoded || !isset($decoded->c) || !is_array($decoded->c)) {
            return null;
        }

        $children = [];
        foreach ($decoded->c as $child) {
            if (($child->type ?? '') === 'pointbadges') {
                if (($child->restriction ?? '') === $restriction) {
                    continue;
                }
            }
            $children[] = $child;
        }

        if (empty($children)) {
            return null;
        }

        $show = true;
        if (isset($decoded->showc) && is_array($decoded->showc)) {
            $show = array_pad([], count($children), true);
        }

        $tree = \core_availability\tree::get_root_json(
            $children,
            $decoded->op ?? \core_availability\tree::OP_AND,
            $show
        );
        return json_encode($tree);
    }

    /**
     * Collect availability children excluding any pointbadges conditions.
     */
    protected static function collect_non_pointbadges_children(?string $availability): array {
        $children = [];

        if (empty($availability)) {
            return $children;
        }

        $decoded = json_decode($availability);
        if (!$decoded || !isset($decoded->c) || !is_array($decoded->c)) {
            return $children;
        }

        foreach ($decoded->c as $child) {
            if (($child->type ?? '') === 'pointbadges') {
                continue;
            }
            $children[] = $child;
        }

        return $children;
    }

    /**
     * Bump restriction epoch so previous purchases no longer unlock this activity.
     */
    protected static function bump_restriction_epoch(int $cmid, string $restriction): void {
        global $DB;

        $now = time();
        if ($restriction === \availability_pointbadges\condition::RESTRICTION_PREMIUM) {
            if ($DB->record_exists('local_pb_premium_restrictions', ['cmid' => $cmid])) {
                $DB->set_field('local_pb_premium_restrictions', 'timemodified', $now, ['cmid' => $cmid]);
            }
            return;
        }

        if ($DB->record_exists('local_pb_vip_restrictions', ['cmid' => $cmid])) {
            $DB->set_field('local_pb_vip_restrictions', 'timemodified', $now, ['cmid' => $cmid]);
        }
    }

    /**
     * Remove per-activity unlock records when admin re-applies a restriction.
     */
    protected static function clear_activity_unlocks(int $cmid): void {
        global $DB;
        $DB->delete_records('local_pb_unlocked_activities', ['cmid' => $cmid]);
    }

    protected static function availability_plugin_installed(): bool {
        return class_exists('\availability_pointbadges\condition');
    }
}
