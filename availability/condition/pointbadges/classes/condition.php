<?php
namespace availability_pointbadges;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {

    public const RESTRICTION_PREMIUM = 'premium';
    public const RESTRICTION_VIP = 'vip';

    /** @var string */
    protected $restriction;

    public function __construct($structure) {
        if (!isset($structure->restriction) ||
                !in_array($structure->restriction, [self::RESTRICTION_PREMIUM, self::RESTRICTION_VIP], true)) {
            throw new \coding_exception('Invalid restriction type for availability_pointbadges');
        }
        $this->restriction = $structure->restriction;
    }

    public function save() {
        return (object) [
            'type' => 'pointbadges',
            'restriction' => $this->restriction,
        ];
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $cm = $info->get_course_module();
        $allow = \local_point_badges\access_check::can_access_activity($userid, $cm->id);
        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        if ($this->restriction === self::RESTRICTION_VIP) {
            return get_string('requires_vip', 'availability_pointbadges');
        }
        return get_string('requires_premium', 'availability_pointbadges');
    }

    protected function get_debug_string() {
        return 'pointbadges:' . $this->restriction;
    }

    /**
     * Build JSON for programmatic availability updates.
     *
     * @param string $restriction premium|vip
     * @return \stdClass
     */
    public static function get_json(string $restriction): \stdClass {
        if (!in_array($restriction, [self::RESTRICTION_PREMIUM, self::RESTRICTION_VIP], true)) {
            throw new \coding_exception('Invalid restriction type');
        }
        return (object) [
            'type' => 'pointbadges',
            'restriction' => $restriction,
        ];
    }
}
