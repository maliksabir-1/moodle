<?php
require_once(__DIR__ . '/../../config.php');

require_login();

global $USER, $DB;

$issueid = required_param('issueid', PARAM_INT);

$certificate = $DB->get_record('local_pb_certificates', ['id' => $issueid, 'userid' => $USER->id]);
if (!$certificate) {
    throw new moodle_exception('errorcertificateaccess', 'local_point_badges');
}

redirect(new moodle_url('/local/point_badges/view_certificate.php', ['issueid' => $issueid]));
