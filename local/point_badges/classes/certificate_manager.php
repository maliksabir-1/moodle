<?php
namespace local_point_badges;

defined('MOODLE_INTERNAL') || die();

class certificate_manager {

    /**
     * Issue custom certificate using tool_certificate when a valid template exists.
     */
    public static function issue_custom_certificate($userid, $certificatetype) {
        if (self::tool_certificate_available()) {
            $templates = self::get_available_templates();
            foreach ($templates as $template) {
                if ($template->name === $certificatetype) {
                    $issueid = self::issue_tool_certificate($userid, $template->id, $certificatetype);
                    if ($issueid) {
                        return $issueid;
                    }
                    break;
                }
            }
            if (!empty($templates)) {
                $first = reset($templates);
                $issueid = self::issue_tool_certificate($userid, $first->id, $first->name);
                if ($issueid) {
                    return $issueid;
                }
            }
        }

        return self::issue_local_certificate($userid, $certificatetype);
    }

    /**
     * Issue certificate for reaching a new level.
     */
    public static function issue_level_certificate($userid, $level) {
        $levelinfo = manager::get_level_details($level);
        return self::issue_custom_certificate($userid, $levelinfo['name'] . ' Level');
    }

    /**
     * Issue a local certificate record (fallback when tool_certificate is unavailable).
     */
    public static function issue_local_certificate($userid, $certificatetype) {
        global $DB;

        self::create_local_certificates_table();

        $issue = new \stdClass();
        $issue->userid = $userid;
        $issue->certificate_name = $certificatetype;
        $issue->certificate_code = self::generate_certificate_code($userid, $certificatetype);
        $issue->issued_date = time();
        $issue->tool_issue_id = 0;

        return $DB->insert_record('local_pb_certificates', $issue);
    }

    /**
     * Issue certificate via tool_certificate and store a local reference.
     */
    public static function issue_tool_certificate($userid, $templateid, $certificatename) {
        global $DB;

        if (!self::tool_certificate_available() || !$templateid) {
            return false;
        }

        if (!$DB->record_exists('tool_certificate_pages', ['templateid' => $templateid])) {
            return false;
        }

        self::create_local_certificates_table();

        try {
            $template = \tool_certificate\template::instance($templateid);
            $issueid = $template->issue_certificate(
                $userid,
                null,
                ['certificatename' => $certificatename],
                'local_point_badges'
            );
        } catch (\Throwable $e) {
            return false;
        }

        if (!$issueid) {
            return false;
        }

        $toolissue = $DB->get_record('tool_certificate_issues', ['id' => $issueid]);
        if (!$toolissue) {
            return false;
        }

        $issue = new \stdClass();
        $issue->userid = $userid;
        $issue->certificate_name = $certificatename;
        $issue->certificate_code = $toolissue->code;
        $issue->issued_date = $toolissue->timecreated;
        $issue->tool_issue_id = $issueid;

        return $DB->insert_record('local_pb_certificates', $issue);
    }

    private static function generate_certificate_code($userid, $certificatetype) {
        return strtoupper(substr(md5($userid . $certificatetype . time() . uniqid()), 0, 12));
    }

    /**
     * Get user's certificates.
     */
    public static function get_user_certificates($userid) {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_pb_certificates')) {
            return [];
        }

        return $DB->get_records('local_pb_certificates', ['userid' => $userid], 'issued_date DESC');
    }

    /**
     * Get certificate templates that have at least one page configured.
     */
    public static function get_available_templates() {
        global $DB;

        if (!self::tool_certificate_available()) {
            return [];
        }

        $sql = "SELECT t.id, t.name
                  FROM {tool_certificate_templates} t
                 WHERE EXISTS (
                       SELECT 1
                         FROM {tool_certificate_pages} p
                        WHERE p.templateid = t.id
                 )
              ORDER BY t.name ASC";

        return $DB->get_records_sql($sql) ?: [];
    }

    /**
     * Get the URL to view a certificate inside Moodle.
     */
    public static function get_view_url(\stdClass $certificate): \moodle_url {
        return new \moodle_url('/local/point_badges/view_certificate.php', [
            'issueid' => $certificate->id,
        ]);
    }

    /**
     * Get raw PDF bytes for a certificate.
     */
    public static function get_certificate_pdf_content(\stdClass $certificate): ?string {
        global $DB, $USER;

        if (!empty($certificate->tool_issue_id) && self::tool_certificate_available()) {
            $toolissue = $DB->get_record('tool_certificate_issues', [
                'id' => $certificate->tool_issue_id,
                'userid' => $USER->id,
            ]);

            if ($toolissue) {
                try {
                    $template = \tool_certificate\template::instance($toolissue->templateid);
                    $file = $template->get_issue_file($toolissue);
                    if ($file && $file->get_filesize() > 0) {
                        return $file->get_content();
                    }
                } catch (\Throwable $e) {
                    // Fall back to the locally generated PDF below.
                }
            }
        }

        return self::generate_local_pdf_content($certificate);
    }

    /**
     * Get the filename used when saving a certificate.
     */
    public static function get_certificate_filename(\stdClass $certificate, int $userid): string {
        return self::build_filename($certificate, $userid);
    }

    /**
     * Output a minimal full-screen certificate viewer page.
     */
    public static function output_fullscreen_viewer(
        string $title,
        string $pdfcontent,
        string $filename,
        string $downloadlabel,
        string $savepdflabel = 'Save as PDF (Print)'
    ): void {
        $pdfdatauri = 'data:application/pdf;base64,' . base64_encode($pdfcontent) . '#view=FitH&toolbar=0';

        header('Content-Type: text/html; charset=utf-8');
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
            .certificate-viewer__btn--secondary {
                background: #6c757d;
            }
            .certificate-viewer__btn--secondary:hover {
                background: #5a6268;
            }
            @media print {
                .certificate-viewer__toolbar {
                    display: none !important;
                }
                .certificate-viewer__embed {
                    height: 100vh !important;
                }
            }
        </style></head><body>';
        echo '<script type="text/plain" id="certificate-pdf-data">' . base64_encode($pdfcontent) . '</script>';
        echo '<div class="certificate-viewer">';
        echo '<embed class="certificate-viewer__embed" src="' . s($pdfdatauri) . '" type="application/pdf">';
        echo '<div class="certificate-viewer__toolbar">';
        echo '<button type="button" class="certificate-viewer__btn" id="certificate-save-print">' .
            s($savepdflabel) . '</button>';
        echo '<button type="button" class="certificate-viewer__btn certificate-viewer__btn--secondary" ' .
            'id="certificate-download-btn" data-filename="' . s($filename) . '">' . s($downloadlabel) . '</button>';
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

                document.getElementById("certificate-save-print").addEventListener("click", function() {
                    window.print();
                });

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
                    setTimeout(function() {
                        URL.revokeObjectURL(url);
                        link.remove();
                    }, 250);
                });
            })();
        </script></body></html>';
        exit;
    }

    /**
     * Get the URL to view/download a certificate without HTTP PDF responses.
     */
    public static function get_download_url(\stdClass $certificate): \moodle_url {
        return self::get_view_url($certificate);
    }

    /**
     * Output the certificate PDF to the browser.
     *
     * @param \stdClass $certificate
     * @param bool $forcedownload True to send as attachment, false to display inline.
     */
    public static function serve_certificate(\stdClass $certificate, bool $forcedownload = true): bool {
        global $CFG, $DB, $USER;

        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!empty($certificate->tool_issue_id) && self::tool_certificate_available()) {
            $toolissue = $DB->get_record('tool_certificate_issues', [
                'id' => $certificate->tool_issue_id,
                'userid' => $USER->id,
            ]);

            if ($toolissue) {
                try {
                    $template = \tool_certificate\template::instance($toolissue->templateid);
                    $file = $template->get_issue_file($toolissue);
                    if ($file && $file->get_filesize() > 0) {
                        send_stored_file($file, 0, 0, $forcedownload, [
                            'filename' => self::build_filename($certificate, $USER->id),
                        ]);
                        return true;
                    }
                } catch (\Throwable $e) {
                    // Fall back to the locally generated PDF below.
                }
            }
        }

        $content = self::generate_local_pdf_content($certificate);
        if ($content === null) {
            return false;
        }

        $filename = self::build_filename($certificate, $USER->id);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($content));
        if ($forcedownload) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        echo $content;

        return true;
    }

    /**
     * Generate a local fallback PDF certificate and return the raw bytes.
     */
    protected static function generate_local_pdf_content(\stdClass $certificate): ?string {
        global $CFG, $DB, $USER;

        $user = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);
        $signaturename = get_config('local_point_badges', 'signature_name') ?: 'System Administrator';
        $signaturetitle = get_config('local_point_badges', 'signature_title') ?: 'Point Badges System';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $html = '
<div style="text-align: center;">
    <h1 style="color: #2c3e50; font-size: 26pt;">CERTIFICATE OF ACHIEVEMENT</h1>
    <p style="font-size: 10pt; color: #7f8c8d;">Point Badges System - Official Achievement</p>
</div>
<hr>
<table cellpadding="10" style="border: 8px double #d4af37; width: 100%;">
    <tr>
        <td align="center">
            <h2 style="color: #d4af37; font-size: 16pt;">This certificate is proudly presented to</h2>
            <h1 style="color: #8b0000; font-size: 28pt;">' . fullname($user) . '</h1>
            <p style="font-size: 12pt;">for outstanding achievement and successful completion of</p>
            <h2 style="color: #2e7d32; font-size: 18pt;">"' . htmlspecialchars($certificate->certificate_name) . '"</h2>
            <hr>
            <p><strong>Certificate Code:</strong> ' . htmlspecialchars($certificate->certificate_code) . '</p>
            <p><strong>Date Issued:</strong> ' . userdate($certificate->issued_date, '%B %d, %Y') . '</p>
            <br><br>
            <table width="100%">
                <tr>
                    <td width="50%" align="center">
                        <hr width="80%">
                        <strong>' . htmlspecialchars($signaturename) . '</strong><br>
                        ' . htmlspecialchars($signaturetitle) . '
                    </td>
                    <td width="50%" align="center">
                        <hr width="80%">
                        <strong>Student</strong><br>
                        Recipient
                    </td>
                </tr>
            </table>
            <br>
            <p style="font-size: 8pt; color: #999;">Generated by ' . $CFG->fullname . '</p>
        </td>
    </tr>
</table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    protected static function build_filename(\stdClass $certificate, int $userid): string {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename', MUST_EXIST);
        $cleanname = preg_replace('/[^A-Za-z0-9\-]/', '_', fullname($user));

        return 'Certificate_' . $cleanname . '_' . $certificate->certificate_code . '.pdf';
    }

    /**
     * Whether tool_certificate is installed and available.
     */
    public static function tool_certificate_available(): bool {
        global $CFG;
        return file_exists($CFG->dirroot . '/admin/tool/certificate/lib.php');
    }

    private static function create_local_certificates_table() {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_pb_certificates');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('certificate_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('certificate_code', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('issued_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('tool_issue_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->create_table($table);
            return;
        }

        $field = new \xmldb_field('tool_issue_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'issued_date');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
}
