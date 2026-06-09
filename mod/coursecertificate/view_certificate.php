<?php
// This file is part of the mod_coursecertificate plugin for Moodle - http://moodle.org/
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
 * Embedded certificate viewer that avoids external PDF download managers.
 *
 * @package     mod_coursecertificate
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_coursecertificate\helper;
use tool_certificate\template;

global $USER, $DB;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'coursecertificate');
require_course_login($course, true, $cm);

$certificate = $DB->get_record('coursecertificate', ['id' => $cm->instance], '*', MUST_EXIST);
helper::issue_certificate($USER, $certificate, $course);

$existingcertificate = helper::get_user_certificate($USER->id, $course->id, $certificate->template);
if (!$existingcertificate) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$issue = template::get_issue_from_code($existingcertificate->code);
if (!$issue || $issue->userid != $USER->id) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$certtemplate = template::instance($issue->templateid);
$file = $certtemplate->get_issue_file($issue);
if (!$file || $file->get_filesize() === 0) {
    throw new moodle_exception('notfound', 'tool_certificate');
}

$filename = $issue->code . '.pdf';
$pdfcontent = $file->get_content();
$title = format_string($cm->name);

header('Content-Type: text/html; charset=utf-8');
$pdfdatauri = 'data:application/pdf;base64,' . base64_encode($pdfcontent) . '#view=FitH&toolbar=0';

echo '<!DOCTYPE html><html lang="en"><head>';
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>' . s($title) . '</title>';
echo '<style>
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
        background: #525659;
    }
    .certificate-viewer {
        display: flex;
        flex-direction: column;
        width: 100vw;
        height: 100vh;
    }
    .certificate-viewer__embed {
        flex: 1 1 auto;
        width: 100%;
        min-height: 0;
        border: 0;
        display: block;
        background: #525659;
    }
    .certificate-viewer__toolbar {
        flex: 0 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: #fff;
        border-top: 1px solid #dee2e6;
    }
    .certificate-viewer__btn {
        appearance: none;
        border: 0;
        border-radius: 4px;
        background: #0f6cbf;
        color: #fff;
        cursor: pointer;
        font-size: 14px;
        line-height: 1.2;
        padding: 10px 18px;
    }
    .certificate-viewer__btn:hover {
        background: #0c589c;
    }
</style></head><body>';
echo '<div class="certificate-viewer">';
echo '<embed class="certificate-viewer__embed" src="' . s($pdfdatauri) . '" type="application/pdf">';
echo '<div class="certificate-viewer__toolbar">';
echo '<button type="button" class="certificate-viewer__btn" id="mod-coursecertificate-download-certificate" data-filename="' .
    s($filename) . '">' . s(get_string('viewcertificate', 'tool_certificate')) . '</button>';
echo '</div></div>';
echo '<script>
    document.getElementById("mod-coursecertificate-download-certificate").addEventListener("click", function() {
        var embed = document.querySelector(".certificate-viewer__embed");
        if (!embed || !embed.src) {
            return;
        }
        var link = document.createElement("a");
        link.href = embed.src.split("#")[0];
        link.download = this.getAttribute("data-filename");
        document.body.appendChild(link);
        link.click();
        link.remove();
    });
</script></body></html>';
