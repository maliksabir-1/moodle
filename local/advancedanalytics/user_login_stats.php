<?php
// local/advancedanalytics/user_login_stats.php
// Full Login Statistics and History for a specific user

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
$userid = required_param('userid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
$offset = $page * $perpage;

$context = context_system::instance();
require_login();

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$PAGE->set_url('/local/advancedanalytics/user_login_stats.php', ['userid' => $userid, 'page' => $page]);
$PAGE->set_context($context);
$PAGE->set_title("Login History: " . fullname($user));
$PAGE->set_heading("User Activity Audit");

$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'));
$PAGE->requires->css(new moodle_url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'));
$PAGE->requires->css(new moodle_url('/local/advancedanalytics/styles/styles.css'));

echo $OUTPUT->header();

$logins = $DB->get_records_sql("
    SELECT id, timecreated, ip, action, eventname
    FROM {logstore_standard_log}
    WHERE userid = ? AND (action = 'loggedin' OR action = 'loggedout')
    ORDER BY timecreated DESC
    LIMIT $perpage OFFSET $offset
", [$userid]);

$total_count = $DB->count_records_sql("SELECT COUNT(*) FROM {logstore_standard_log} WHERE userid = ? AND (action = 'loggedin' OR action = 'loggedout')", [$userid]);

?>

<div class="aa-dashboard-container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><?php echo $OUTPUT->user_picture($user, ['size' => 60]); ?></div>
            <div>
                <h2 class="fw-800 m-0"><?php echo fullname($user); ?></h2>
                <p class="text-muted mb-0"><?php echo $user->email; ?> | <?php echo $user->department ?: 'No Department'; ?></p>
            </div>
        </div>
        <div>
            <a href="index.php?userid_drill=<?php echo $userid; ?>" class="aa-btn aa-btn-primary me-2">Back to Details</a>
            <button onclick="window.close()" class="aa-btn btn-outline-secondary">Close Window</button>
        </div>
    </div>

    <div class="aa-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="fas fa-history me-2 text-primary"></i> Activity History (<?php echo $total_count; ?> entries)</h5>
            <div class="btn-group">
                <?php if($page > 0): ?>
                    <a href="?userid=<?php echo $userid; ?>&page=<?php echo $page-1; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php endif; ?>
                <?php if($total_count > $offset + $perpage): ?>
                    <a href="?userid=<?php echo $userid; ?>&page=<?php echo $page+1; ?>" class="btn btn-sm btn-outline-primary">Next <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Action Entity</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logins as $l): 
                        $display_action = '';
                        $action_class = 'light';
                        if ($l->action == 'loggedin') { $display_action = 'Authenticated'; $action_class = 'success'; }
                        else if ($l->action == 'loggedout') { $display_action = 'Session Ended'; $action_class = 'warning'; }
                        else { $display_action = 'Content Access'; $action_class = 'info'; }
                    ?>
                        <tr>
                            <td class="fw-bold"><?php echo userdate($l->timecreated, '%d %b %Y, %H:%M:%S'); ?></td>
                            <td>
                                <div class="fw-bold small"><?php echo $display_action; ?></div>
                                <div class="text-muted x-small"><?php echo str_replace('\\', '/', $l->eventname); ?></div>
                            </td>
                            <td><span class="badge bg-<?php echo $action_class; ?> rounded-pill px-3"><?php echo strtoupper($l->action); ?></span></td>
                            <td><code><?php echo $l->ip; ?></code></td>
                            <td><span class="text-success"><i class="fas fa-check-circle me-1"></i> SUCCESS</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logins)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No recent activity logs found for this user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
