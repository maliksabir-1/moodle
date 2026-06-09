<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/csvlib.class.php');

require_login();
$context = context_system::instance();
require_capability('local/point_badges:viewreports', $context);

$PAGE->set_url(new moodle_url('/local/point_badges/report.php'));
$PAGE->set_context($context);
$PAGE->set_title('Point Badges Reports');
$PAGE->set_heading('Point Badges Reports');

// Export functionality
$export = optional_param('export', '', PARAM_ALPHA);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($export == 'xp') {
    export_xp_report($format, $courseid);
} elseif ($export == 'streaks') {
    export_streak_report($format);
} elseif ($export == 'challenges') {
    export_challenge_report($format);
}

echo $OUTPUT->header();

echo '<div class="report-buttons">';
echo '<h3>Export Reports</h3>';
echo '<a href="?export=xp&format=csv" class="btn btn-primary">Export XP Report (CSV)</a> ';
echo '<a href="?export=xp&format=excel" class="btn btn-primary">Export XP Report (Excel)</a><br><br>';
echo '<a href="?export=streaks&format=csv" class="btn btn-secondary">Export Streak Report (CSV)</a> ';
echo '<a href="?export=challenges&format=csv" class="btn btn-secondary">Export Challenge Report (CSV)</a>';
echo '</div>';

// Display table
$table = new flexible_table('point_badges_report');
$table->define_columns(['fullname', 'total_xp', 'level', 'streak', 'challenges_completed']);
$table->define_headers(['User', 'Total XP', 'Level', 'Current Streak', 'Challenges Completed']);
$table->setup();

$users = \local_point_badges\manager::get_leaderboard(null, 1000);
foreach ($users as $user) {
    $streak = \local_point_badges\manager::get_user_streak($user->id);
    $challenges = get_user_challenge_completions($user->id);
    
    $table->add_data([
        fullname($user),
        $user->total_xp,
        $user->level_name,
        $streak ? $streak->current_streak : 0,
        $challenges
    ]);
}

$table->print_html();

echo $OUTPUT->footer();

// Export functions
function export_xp_report($format, $courseid) {
    global $DB;
    
    $data = [];
    $users = \local_point_badges\manager::get_leaderboard($courseid, 10000);
    
    foreach ($users as $user) {
        $streak = \local_point_badges\manager::get_user_streak($user->id);
        $data[] = (object)[
            'Username' => $user->username ?? $user->fullname,
            'Full Name' => $user->fullname,
            'Total XP' => $user->total_xp,
            'Level' => $user->level_name,
            'Current Streak' => $streak ? $streak->current_streak : 0,
            'Max Streak' => $streak ? $streak->max_streak : 0,
            'Last Activity' => userdate($streak->last_login_date ?? time())
        ];
    }
    
    export_to_file($data, 'xp_report', $format);
}

function export_streak_report($format) {
    global $DB;
    
    $sql = "SELECT u.id, u.firstname, u.lastname, u.username,
                   s.current_streak, s.max_streak, s.last_login_date
            FROM {local_pb_streak} s
            JOIN {user} u ON u.id = s.userid
            ORDER BY s.current_streak DESC";
    
    $data = $DB->get_records_sql($sql);
    
    $export_data = [];
    foreach ($data as $record) {
        $export_data[] = (object)[
            'Username' => $record->username,
            'Full Name' => fullname($record),
            'Current Streak' => $record->current_streak,
            'Best Streak' => $record->max_streak,
            'Last Login' => userdate($record->last_login_date)
        ];
    }
    
    export_to_file($export_data, 'streak_report', $format);
}

function export_challenge_report($format) {
    global $DB;
    
    $sql = "SELECT u.username, u.firstname, u.lastname,
                   c.name as challenge_name,
                   uc.completed, uc.progress, c.required_count
            FROM {local_pb_user_challenge} uc
            JOIN {user} u ON u.id = uc.userid
            JOIN {local_pb_challenge} c ON c.id = uc.challengeid
            WHERE uc.date_assigned >= :weekstart
            ORDER BY uc.completed DESC";
    
    $params = ['weekstart' => strtotime('monday this week')];
    $data = $DB->get_records_sql($sql, $params);
    
    $export_data = [];
    foreach ($data as $record) {
        $export_data[] = (object)[
            'Username' => $record->username,
            'Full Name' => fullname($record),
            'Challenge' => $record->challenge_name,
            'Status' => $record->completed ? 'Completed' : 'In Progress',
            'Progress' => $record->progress . '/' . $record->required_count
        ];
    }
    
    export_to_file($export_data, 'challenge_report', $format);
}

function export_to_file($data, $filename, $format) {
    if (empty($data)) {
        die('No data to export');
    }
    
    if ($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
        
        echo '<table border="1">';
        // Headers
        $first = reset($data);
        echo '<tr>';
        foreach ((array)$first as $key => $value) {
            echo '<th>' . $key . '</th>';
        }
        echo '</tr>';
        // Data
        foreach ($data as $row) {
            echo '<tr>';
            foreach ((array)$row as $value) {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        // CSV format
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        $first = reset($data);
        fputcsv($output, array_keys((array)$first));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, (array)$row);
        }
        
        fclose($output);
    }
    exit;
}

function get_user_challenge_completions($userid) {
    global $DB;
    return $DB->count_records('local_pb_user_challenge', [
        'userid' => $userid, 
        'completed' => 1
    ]);
}