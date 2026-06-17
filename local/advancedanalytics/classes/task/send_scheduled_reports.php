<?php
// local/advancedanalytics/classes/task/send_scheduled_reports.php

namespace local_advancedanalytics\task;

defined('MOODLE_INTERNAL') || die();

class send_scheduled_reports extends \core\task\scheduled_task {
    
    public function get_name() {
        return "Send Scheduled Analytics Reports";
    }

    public function execute() {
        global $DB, $CFG;

        $schedules = $DB->get_records('local_aa_reports', ['status' => 1]);
        $now = time();

        foreach ($schedules as $s) {
            // Check if it's time to send
            $last_sent = $s->last_sent;
            $should_send = false;

            if ($s->frequency === 'daily' && ($now - $last_sent) >= 86400) $should_send = true;
            if ($s->frequency === 'weekly' && ($now - $last_sent) >= 604800) $should_send = true;
            if ($s->frequency === 'monthly' && ($now - $last_sent) >= 2592000) $should_send = true;

            if ($should_send) {
                $this->send_report($s);
                $s->last_sent = $now;
                $DB->update_record('local_aa_reports', $s);
            }
        }
    }

    private function send_report($s) {
        global $CFG, $SITE;
        
        $filters = []; // Default filters for scheduled reports
        $content = "";
        $ext = "";
        $mime = "";

        if ($s->format === 'pdf') {
            $content = \local_advancedanalytics\report_generator::generate_pdf($s->report_type, $filters);
            $ext = 'pdf';
            $mime = 'application/pdf';
        } else if ($s->format === 'excel') {
            $content = \local_advancedanalytics\report_generator::generate_excel($s->report_type, $filters);
            $ext = 'xls';
            $mime = 'application/vnd.ms-excel';
        } else {
            $content = \local_advancedanalytics\report_generator::generate_csv($s->report_type, $filters);
            $ext = 'csv';
            $mime = 'text/csv';
        }

        $emails = explode(',', $s->recipients);
        $subject = "Scheduled Analytics Report: " . $s->name;
        $body = "Please find attached the scheduled $s->report_type analytics report generated on " . date('d M Y');

        // Temporary file for attachment
        $tempdir = make_temp_directory('local_advancedanalytics');
        $filepath = $tempdir . '/report_' . $s->id . '_' . time() . '.' . $ext;
        file_put_contents($filepath, $content);

        foreach ($emails as $email) {
            $email = trim($email);
            if (empty($email)) continue;
            
            $dummyuser = new \stdClass();
            $dummyuser->email = $email;
            $dummyuser->firstname = "Administrator";
            $dummyuser->lastname = "";
            $dummyuser->id = -1; // Not a real user

            email_to_user($dummyuser, $CFG->noreplyaddress, $subject, $body, $body, $filepath, 'AnalyticsReport.' . $ext);
        }

        @unlink($filepath);
    }
}
