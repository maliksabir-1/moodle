<?php
// local/advancedanalytics/classes/report_generator.php

namespace local_advancedanalytics;

defined('MOODLE_INTERNAL') || die();

class report_generator {
    
    /**
     * Generate CSV content - Optimized for Excel compatibility
     */
    public static function generate_csv($type, $filters) {
        $data = self::get_data($type, $filters);
        if (empty($data)) return "";
        
        $output = fopen('php://temp', 'r+');
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        $first = reset($data);
        if ($first) {
            fputcsv($output, array_map(function($h) { return strtoupper(str_replace('_', ' ', $h)); }, array_keys((array)$first)));
        }
        
        // Rows
        foreach ($data as $row) {
            fputcsv($output, (array)$row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }

    /**
     * Generate Excel (XLS) with Comprehensive Data & Visuals
     */
    public static function generate_excel($type, $filters) {
        $data = self::get_data($type, $filters);
        if (empty($data)) return "No data available";

        $output = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $output .= '<head><meta http-equiv="Content-type" content="text/html;charset=utf-8" /><style>td { vertical-align: middle; }</style></head><body>';
        
        $output .= '<h1>Advanced Analytics: ' . strtoupper($type) . ' Complete Report</h1>';
        $output .= '<p>Generated on: ' . date('d M Y, H:i') . '</p>';

        // 1. RISK DISTRIBUTION (PROMOTED TO TOP)
        $risk = self::get_risk_stats($filters);
        $output .= '<h3>1. Organizational Health & Safety Profile</h3>';
        $output .= '<table border="1" cellpadding="8" style="margin-bottom: 20px; width: 600px; border-collapse: collapse; border-color: #e2e8f0;">';
        $output .= '<tr style="background-color:#4f46e5; color:#ffffff; font-weight:bold;"><th>Org Risk Status</th><th width="300">Distribution Chart</th><th>Count</th><th>%</th></tr>';
        $risk_lvls = [['CRITICAL RISK','#ef4444','high'], ['MODERATE RISK','#f59e0b','med'], ['LOW RISK PROFILE','#10b981','low']];
        foreach ($risk_lvls as $rl) {
            $p = $risk[$rl[2].'_pct'];
            $output .= '<tr><td style="font-weight:bold; color:'.$rl[1].';">'.$rl[0].'</td><td style="padding:4px; vertical-align: middle;"><table width="100%" height="24" border="0" cellpadding="0" cellspacing="0"><tr><td width="'.$p.'%" bgcolor="'.$rl[1].'" style="border-radius:4px;"></td><td width="'.(100-$p).'%" bgcolor="#f1f5f9"></td></tr></table></td><td align="center">'.$risk[$rl[2].'_count'].'</td><td align="right" style="font-weight:bold;">'.$p.'%</td></tr>';
        }
        $output .= '</table><br/>';

        // 2. ORGANIZATIONAL KPIS
        $kpis = self::get_kpis($filters);
        $output .= '<h3>2. Key Performance Indicators</h3>';
        $output .= '<table border="1" cellpadding="8" style="background-color:#f8fafc; margin-bottom: 20px; border-collapse: collapse; border-color: #e2e8f0;">';
        $output .= '<tr style="background-color:#6366f1; color:#ffffff; font-weight:bold;"><th>Category</th><th>Metric Description</th><th>Current Performance</th></tr>';
        foreach ($kpis as $k => $v) {
            $output .= "<tr><td style='font-weight:bold; color:#4f46e5;'>Summary Metric</td><td style='color:#64748b; font-weight:600;'>" . strtoupper(str_replace('_', ' ', $k)) . "</td><td style='font-weight:bold; color:#1e293b;'>" . $v . "</td></tr>";
        }
        $output .= '</table><br/>';

        // 3. TREND ANALYSIS
        $trend = self::get_trend_data($filters);
        if (!empty($trend)) {
            $output .= '<h3>3. Employee Engagement Trends</h3>';
            $output .= '<table border="1" cellpadding="8" style="margin-bottom: 20px; width: 500px; border-collapse: collapse; border-color: #e2e8f0;">';
            $output .= '<tr style="background-color:#4f46e5; color:#ffffff; font-weight:bold;"><th>Measurement Date</th><th width="300">Activity Intensity</th><th>Active Users</th></tr>';
            $max_t = max(1, ...array_map(function($t){return $t->active_count;}, $trend));
            foreach ($trend as $t) {
                $pct = round(($t->active_count / $max_t) * 100);
                $output .= '<tr><td style="font-weight:600;">' . $t->time_day . '</td><td style="padding:4px;">';
                $output .= '<table width="100%" height="18" border="0" cellpadding="0" cellspacing="0"><tr>';
                $output .= '<td width="' . $pct . '%" bgcolor="#3b82f6" style="border-radius:3px;"></td><td width="' . (100-$pct) . '%" bgcolor="#f8fafc"></td>';
                $output .= '</tr></table></td><td align="center">' . $t->active_count . '</td></tr>';
            }
            $output .= '</table><br/>';
        }

        // 4. MAIN DATA
        $output .= '<h3>4. Detailed Log Records</h3>';
        if ($type === 'master') {
            // Append all tables for Master Report
            $types = ['executive', 'compliance', 'learners'];
            foreach ($types as $t) {
                $output .= '<div style="background-color:#f1f5f9; padding:10px; margin:20px 0; border-left: 4px solid #4f46e5;"><h4 style="margin:0;">Section: ' . strtoupper($t) . '</h4></div>';
                $t_data = self::get_data($t, $filters);
                $output .= '<table border="1" cellpadding="6" style="margin-bottom:30px; border-collapse: collapse; border-color: #e2e8f0;">';
                if (!empty($t_data)) {
                    $first = reset($t_data);
                    $output .= '<tr style="background-color:#4f46e5; color:#ffffff; font-size: 11pt;">';
                    foreach (array_keys((array)$first) as $h) { $output .= '<th style="padding:10px;">' . strtoupper(str_replace('_',' ',$h)) . '</th>'; }
                    $output .= '</tr>';
                    foreach ($t_data as $row) {
                        $output .= '<tr style="font-size: 10pt;">';
                        foreach ((array)$row as $v) { $output .= '<td style="padding:8px;">' . htmlspecialchars($v) . '</td>'; }
                        $output .= '</tr>';
                    }
                }
                $output .= '</table>';
            }
        } else {
            $output .= '<table border="1" cellpadding="6" style="border-collapse: collapse; border-color: #e2e8f0;">';
            $output .= '<tr style="background-color:#4f46e5; color:#ffffff; font-weight:bold; font-size: 11pt;">';
            $first = reset($data);
            foreach (array_keys((array)$first) as $h) {
                $output .= '<th style="padding:10px;">' . strtoupper(str_replace('_', ' ', $h)) . '</th>';
            }
            $output .= '</tr>';
            foreach ($data as $row) {
                $output .= '<tr style="font-size: 10pt;">';
                foreach ((array)$row as $v) { $output .= '<td style="padding:8px;">' . htmlspecialchars($v) . '</td>'; }
                $output .= '</tr>';
            }
            $output .= '</table>';
        }
        $output .= '</body></html>';
        return $output;
    }

    /**
     * Generate PDF with All Dashboard Visuals
     */
    public static function generate_pdf($type, $filters) {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->libdir . '/tcpdf/tcpdf.php');
        
        $data = self::get_data($type, $filters);
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Advanced Analytics');
        $pdf->SetTitle(strtoupper($type) . ' Comprehensive Analytics Report');
        $pdf->setPrintHeader(false); $pdf->setPrintFooter(true);
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(79, 70, 229);
        $pdf->Cell(0, 15, 'MANAGEMENT PERFORMANCE REPORT', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell(0, 5, 'Scope: ' . ($filters['dept'] ?: 'All Departments') . ' | Date: ' . date('d M Y, H:i'), 0, 1, 'L');
        $pdf->Ln(10);
        
        // 1. RISK ANALYSIS (PROMOTED TO TOP)
        $risk = self::get_risk_stats($filters);
        $pdf->SetFont('helvetica', 'B', 14); $pdf->SetTextColor(220, 38, 38);
        $pdf->Cell(0, 10, 'CRITICAL RISK SUMMARY', 0, 1, 'L');
        $html = '<table border="0" cellpadding="6" width="100%">';
        $risk_map = [['High Risk Status','#ef4444','high'], ['Medium Risk Level','#f59e0b','med'], ['Low Risk Standing','#10b981','low']];
        foreach ($risk_map as $rm) {
            $p = $risk[$rm[2].'_pct'];
            $html .= '<tr><td width="25%" style="font-size:10px; font-weight:bold; color: #374151;">'.$rm[0].'</td>
                      <td width="60%"><table border="0" width="100%"><tr><td width="'.$p.'%" bgcolor="'.$rm[1].'" height="18"></td><td width="'.(100-$p).'%" bgcolor="#f1f5f9"></td></tr></table></td>
                      <td width="15%" style="font-size:10px; font-weight:bold; text-align:right; color: '.$rm[1].';">'.$p.'%</td></tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html);
        $pdf->Ln(5);

        // 2. KPIS
        $pdf->SetFont('helvetica', 'B', 12); $pdf->SetTextColor(31, 41, 55);
        $pdf->Cell(0, 10, 'Key Performance Benchmarks', 0, 1, 'L');
        $kpis = self::get_kpis($filters);
        $html = '<table border="0" cellpadding="12" cellspacing="5" width="100%"><tr>';
        foreach ($kpis as $label => $val) {
            $html .= '<td style="background-color:#f8fafc; border: 1px solid #e5e7eb; text-align:center;">
                        <div style="font-size: 8px; color: #6366f1; font-weight: bold; text-transform: uppercase;">'.str_replace('_',' ',$label).'</div>
                        <div style="font-size: 18px; font-weight: bold; color: #1e293b;">'.$val.'</div>
                      </td>';
        }
        $html .= '</tr></table>';
        $pdf->writeHTML($html);
        $pdf->Ln(5);

        // 3. TRENDS GRAPH
        $trend = self::get_trend_data($filters);
        if (!empty($trend)) {
            $pdf->SetFont('helvetica', 'B', 12); $pdf->SetTextColor(31, 41, 55);
            $pdf->Cell(0, 10, 'Historical Activity Trend Profile', 0, 1, 'L');
            $max_t = max(1, ...array_map(function($t){return $t->active_count;}, $trend));
            
            $html = '<table border="0" cellpadding="5" width="100%">';
            foreach ($trend as $t) {
                $pct = round(($t->active_count / $max_t) * 100);
                $html .= '<tr><td width="20%" style="font-size:9px; font-weight: bold; color: #64748b;">'.$t->time_day.'</td>
                          <td width="70%"><table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>
                          <td width="'.$pct.'%" bgcolor="#6366f1" height="14"></td><td width="'.(100-$pct).'%" bgcolor="#f8fafc"></td>
                          </tr></table></td>
                          <td width="10%" style="font-size:9px; font-weight:bold; text-align: right;">'.$t->active_count.'</td></tr>';
            }
            $html .= '</table>';
            $pdf->writeHTML($html);
            $pdf->Ln(5);
        }
        
        // Main Table (on new page if needed)
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12); $pdf->Cell(0, 10, 'Detailed Organizational Data Appendix', 0, 1, 'L');
        
        if ($type === 'master') {
            $types = ['executive', 'compliance', 'learners'];
            foreach ($types as $t) {
                $pdf->SetFont('helvetica', 'B', 11); $pdf->SetTextColor(79, 70, 229);
                $pdf->Cell(0, 10, 'SECTION: ' . strtoupper($t), 0, 1, 'L');
                $pdf->SetTextColor(0,0,0);
                $t_data = self::get_data($t, $filters);
                $html = '<table border="1" cellpadding="6" style="border-collapse: collapse; border-color: #e5e7eb;">';
                if (!empty($t_data)) {
                    $first = reset($t_data);
                    $html .= '<tr style="background-color:#4f46e5; color:#ffffff; font-weight:bold; font-size: 8px;">';
                    foreach (array_keys((array)$first) as $h) { $html .= '<th>' . strtoupper(str_replace('_',' ',$h)) . '</th>'; }
                    $html .= '</tr>';
                    foreach (array_slice($t_data, 0, 50) as $row) {
                        $html .= '<tr style="font-size: 7px; color: #374151;">';
                        foreach ((array)$row as $v) { $html .= '<td>' . htmlspecialchars($v) . '</td>'; }
                        $html .= '</tr>';
                    }
                }
                $html .= '</table><br/><br/>';
                $pdf->SetFont('helvetica', '', 7); $pdf->writeHTML($html);
            }
        } else {
            $html = '<table border="1" cellpadding="8" style="border-collapse: collapse; border-color: #e5e7eb;">';
            if (!empty($data)) {
                $html .= '<tr style="background-color:#4f46e5; color:#ffffff; font-weight:bold; font-size: 10px;">';
                $first = reset($data);
                foreach (array_keys((array)$first) as $h) { $html .= '<th align="center">' . strtoupper(str_replace('_', ' ', $h)) . '</th>'; }
                $html .= '</tr>';
                foreach ($data as $row) {
                    $html .= '<tr style="font-size: 9px; color: #374151;">';
                    foreach ((array)$row as $v) { $html .= '<td>' . htmlspecialchars($v) . '</td>'; }
                    $html .= '</tr>';
                }
            }
            $html .= '</table>';
            $pdf->SetFont('helvetica', '', 9); $pdf->writeHTML($html);
        }
        
        return $pdf->Output('analytics_report.pdf', 'S');
    }

    /**
     * Data extraction logic for reports - FULLY FILTER AWARE
     */
    private static function get_data($type, $filters) {
        global $DB;
        $dept = $filters['dept'] ?? '';
        $courseid = $filters['course'] ?? 0;
        $role = $filters['role'] ?? 0;
        $search = $filters['search'] ?? '';
        
        // Build base user where for filtering sub-queries
        $u_where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        $u_params = [];
        if ($dept) {
            if ($dept === 'Unassigned') { $u_where .= " AND (u.department IS NULL OR u.department = '')"; }
            else { $u_where .= " AND u.department = ?"; $u_params[] = $dept; }
        }
        if ($courseid) {
            $u_where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $u_params[] = $courseid;
        }
        if ($role) {
            $u_where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $u_params[] = $role;
        }
        if ($search) {
            $u_where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $u_params = array_merge($u_params, ["%$search%", "%$search%", "%$search%"]);
        }

        switch ($type) {
            case 'executive':
                $where = "1=1"; $params = [];
                if ($dept) { $where .= " AND department = ?"; $params[] = $dept; }
                if ($courseid) { $where .= " AND courseid = ?"; $params[] = $courseid; }
                return $DB->get_records_sql("SELECT id, department, total_users, active_users, completions, avg_grade, engagement_score, timemodified FROM {local_aa_summary} WHERE $where ORDER BY timemodified DESC LIMIT 500", $params);
            case 'compliance':
                return $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.email, u.department, uc.status, uc.compliance_percentage FROM {user} u LEFT JOIN {local_aa_user_compliance} uc ON uc.userid = u.id WHERE $u_where ORDER BY u.lastname ASC", $u_params);
            case 'learners':
                return $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.department, up.engagement_score, up.completion_percentage, up.risk_level FROM {local_aa_user_perf} up JOIN {user} u ON u.id = up.userid WHERE $u_where ORDER BY up.engagement_score DESC", $u_params);
            case 'master':
                // For master, we use department as the unique key for the results array
                return $DB->get_records_sql("SELECT COALESCE(u.department, 'Unassigned') as id, u.department, COUNT(u.id) as user_count, AVG(up.engagement_score) as avg_engage, AVG(up.completion_percentage) as avg_progress FROM {user} u LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id WHERE $u_where GROUP BY u.department", $u_params);
            default: return [];
        }
    }

    private static function get_kpis($filters) {
        global $DB;
        $dept = $filters['dept'] ?? '';
        $courseid = $filters['course'] ?? 0;
        $role = $filters['role'] ?? 0;
        $search = $filters['search'] ?? '';

        $u_where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        $u_params = [];
        if ($dept) {
            if ($dept === 'Unassigned') { $u_where .= " AND (u.department IS NULL OR u.department = '')"; }
            else { $u_where .= " AND u.department = ?"; $u_params[] = $dept; }
        }
        if ($courseid) {
            $u_where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $u_params[] = $courseid;
        }
        if ($role) {
            $u_where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $u_params[] = $role;
        }
        if ($search) {
            $u_where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $u_params = array_merge($u_params, ["%$search%", "%$search%", "%$search%"]);
        }

        $total = $DB->count_records_sql("SELECT COUNT(*) FROM {user} u WHERE $u_where", $u_params) ?: 1;
        $activeCount = $DB->count_records_sql("SELECT COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l JOIN {user} u ON u.id = l.userid WHERE $u_where AND l.timecreated > ?", array_merge($u_params, [time() - (30*86400)]));
        $avg_grade = $DB->get_field_sql("SELECT AVG(gg.finalgrade) FROM {grade_grades} gg JOIN {user} u ON u.id = gg.userid WHERE $u_where AND gg.finalgrade IS NOT NULL", $u_params) ?: 0;
        $compliant = $DB->count_records_sql("SELECT COUNT(*) FROM {local_aa_user_compliance} uc JOIN {user} u ON u.id = uc.userid WHERE $u_where AND uc.status = 'compliant'", $u_params);

        return [
            'total_users' => $total, 'active_users' => $activeCount,
            'avg_grade' => round($avg_grade, 1) . '%',
            'compliance' => round(($compliant / $total) * 100, 1) . '%'
        ];
    }

    private static function get_trend_data($filters) {
        global $DB;
        $dept = $filters['dept'] ?? '';
        $where = "1=1"; $params = [];
        if ($dept) { $where .= " AND department = ?"; $params[] = $dept; }
        $records = $DB->get_records_sql("SELECT date as id, date, SUM(active_users) as active_count FROM {local_aa_summary} WHERE $where GROUP BY date ORDER BY date DESC LIMIT 7", $params);
        $results = [];
        foreach ($records as $r) { $results[] = (object)['time_day' => date('d M', $r->date), 'active_count' => $r->active_count]; }
        return array_reverse($results);
    }

    private static function get_risk_stats($filters) {
        global $DB;
        $dept = $filters['dept'] ?? '';
        $courseid = $filters['course'] ?? 0;
        $role = $filters['role'] ?? 0;
        $search = $filters['search'] ?? '';

        $u_where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
        $u_params = [];
        if ($dept) {
            if ($dept === 'Unassigned') { $u_where .= " AND (u.department IS NULL OR u.department = '')"; }
            else { $u_where .= " AND u.department = ?"; $u_params[] = $dept; }
        }
        if ($courseid) {
            $u_where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
            $u_params[] = $courseid;
        }
        if ($role) { $u_where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)"; $u_params[] = $role; }

        $records = $DB->get_records_sql("SELECT up.risk_level, COUNT(*) as count FROM {local_aa_user_perf} up JOIN {user} u ON u.id = up.userid WHERE $u_where GROUP BY up.risk_level", $u_params);
        $high = $records['high']->count ?? 0; $med = $records['medium']->count ?? 0; $low = $records['low']->count ?? 0;
        $total = max(1, $high + $med + $low);
        return [
            'high_count' => $high, 'high_pct' => round(($high/$total)*100),
            'med_count' => $med, 'med_pct' => round(($med/$total)*100),
            'low_count' => $low, 'low_pct' => round(($low/$total)*100)
        ];
    }
}
