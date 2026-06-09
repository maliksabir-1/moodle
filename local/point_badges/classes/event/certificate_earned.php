<?php
namespace local_point_badges\event;

defined('MOODLE_INTERNAL') || die();

class certificate_earned extends \core\event\base {
    
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
    
    public static function get_name() {
        return get_string('certificate_earned', 'local_point_badges');
    }
    
    public function get_description() {
        $cert_type = $this->other['certificate_type'] ?? 'Certificate';
        return "The user with id '{$this->relateduserid}' earned certificate: {$cert_type}";
    }
    
    public function get_url() {
        return new \moodle_url('/user/profile.php', ['id' => $this->relateduserid]);
    }
    
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}