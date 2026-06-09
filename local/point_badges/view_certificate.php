<?php
require_once(__DIR__ . '/../../config.php');

require_login();

global $USER, $DB;

$issueid = required_param('issueid', PARAM_INT);

$certificate = $DB->get_record('local_pb_certificates', ['id' => $issueid, 'userid' => $USER->id]);
if (!$certificate) {
    throw new moodle_exception('errorcertificateaccess', 'local_point_badges');
}

$pdfcontent = \local_point_badges\certificate_manager::get_certificate_pdf_content($certificate);
if (!$pdfcontent) {
    throw new moodle_exception('errorcertificateaccess', 'local_point_badges');
}

$filename = \local_point_badges\certificate_manager::get_certificate_filename($certificate, $USER->id);

\local_point_badges\certificate_manager::output_fullscreen_viewer(
    $certificate->certificate_name,
    $pdfcontent,
    $filename,
    get_string('downloadcertificate', 'local_point_badges'),
    get_string('saveaspdf', 'local_point_badges')
);
