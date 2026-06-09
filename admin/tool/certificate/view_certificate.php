<?php
// This file is part of the tool_certificate plugin for Moodle - http://moodle.org/
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

/**
 * Embedded certificate viewer (avoids direct .pdf URLs intercepted by download managers).
 *
 * @package    tool_certificate
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_certificate\permission;
use tool_certificate\template;

require_login();

$issuecode = required_param('code', PARAM_TEXT);

$issue = template::get_issue_from_code($issuecode);
if (!$issue) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$context = \context_course::instance($issue->courseid, IGNORE_MISSING) ?: null;
$certtemplate = template::instance($issue->templateid);

if (!permission::can_verify() && !permission::can_view_issue($certtemplate, $issue, $context)) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$file = $certtemplate->get_issue_file($issue);
if (!$file || $file->get_filesize() === 0) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$title = $certtemplate->get_formatted_name();
$filename = $issue->code . '.pdf';
$pdfcontent = $file->get_content();

if (file_exists($CFG->dirroot . '/local/point_badges/classes/certificate_manager.php')) {
    require_once($CFG->dirroot . '/local/point_badges/classes/certificate_manager.php');
    \local_point_badges\certificate_manager::output_fullscreen_viewer(
        $title,
        $pdfcontent,
        $filename,
        get_string('downloadcertificate', 'tool_certificate'),
        get_string('saveaspdf', 'tool_certificate')
    );
}

$pdfdatauri = 'data:application/pdf;base64,' . base64_encode($pdfcontent) . '#view=FitH&toolbar=0';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="en"><head>';
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>' . s($title) . '</title>';
echo '<style>
    html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #525659; }
    .certificate-viewer { display: flex; flex-direction: column; width: 100vw; height: 100vh; }
    .certificate-viewer__embed { flex: 1 1 auto; width: 100%; min-height: 0; border: 0; display: block; background: #525659; }
    .certificate-viewer__toolbar { flex: 0 0 auto; display: flex; justify-content: center; align-items: center; gap: 12px; padding: 10px 16px; background: #fff; border-top: 1px solid #dee2e6; }
    .certificate-viewer__btn { appearance: none; border: 0; border-radius: 4px; background: #0f6cbf; color: #fff; cursor: pointer; font-size: 14px; line-height: 1.2; padding: 10px 18px; }
    .certificate-viewer__btn:hover { background: #0c589c; }
    .certificate-viewer__btn--secondary { background: #6c757d; }
    .certificate-viewer__btn--secondary:hover { background: #5a6268; }
    @media print { .certificate-viewer__toolbar { display: none !important; } .certificate-viewer__embed { height: 100vh !important; } }
</style></head><body>';
echo '<script type="text/plain" id="certificate-pdf-data">' . base64_encode($pdfcontent) . '</script>';
echo '<div class="certificate-viewer">';
echo '<embed class="certificate-viewer__embed" src="' . s($pdfdatauri) . '" type="application/pdf">';
echo '<div class="certificate-viewer__toolbar">';
echo '<button type="button" class="certificate-viewer__btn" id="certificate-save-print">' .
    s(get_string('saveaspdf', 'tool_certificate')) . '</button>';
echo '<button type="button" class="certificate-viewer__btn certificate-viewer__btn--secondary" ' .
    'id="certificate-download-btn" data-filename="' . s($filename) . '">' .
    s(get_string('downloadcertificate', 'tool_certificate')) . '</button>';
echo '</div></div>';
echo '<script>
    (function() {
        function getPdfBytes() {
            var b64 = document.getElementById("certificate-pdf-data").textContent.trim();
            var binary = atob(b64);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes;
        }
        document.getElementById("certificate-save-print").addEventListener("click", function() { window.print(); });
        document.getElementById("certificate-download-btn").addEventListener("click", function() {
            var bytes = getPdfBytes();
            var blob = new Blob([bytes], {type: "application/octet-stream"});
            var url = URL.createObjectURL(blob);
            var link = document.createElement("a");
            link.href = url;
            link.download = this.getAttribute("data-filename");
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
            setTimeout(function() { URL.revokeObjectURL(url); link.remove(); }, 250);
        });
    })();
</script></body></html>';
