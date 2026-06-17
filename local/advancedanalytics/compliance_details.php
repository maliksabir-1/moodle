<?php
// local/advancedanalytics/compliance_details.php
// Dedicated Compliance Detailed Report Page

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login();

$PAGE->set_url('/local/advancedanalytics/compliance_details.php');
$PAGE->set_context($context);
$PAGE->set_title("Detailed Compliance Audit");
$PAGE->set_heading("Detailed Compliance Audit");

// Required Assets
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'));
$PAGE->requires->css(new moodle_url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'));
$PAGE->requires->css(new moodle_url('/local/advancedanalytics/styles/styles.css'));

echo $OUTPUT->header();

// 1. INPUT HANDLING
$dept = optional_param('dept', '', PARAM_TEXT);
$courseid = optional_param('course', 0, PARAM_INT);
$role_filter = optional_param('role', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);

global $DB;

// 2. BUILD QUERY
$where = "u.deleted = 0 AND u.username != 'guest'";
$params = [];

if ($dept) {
    if ($dept === 'Unassigned') {
        $where .= " AND (u.department IS NULL OR u.department = '')";
    } else {
        $where .= " AND u.department = ?";
        $params[] = $dept;
    }
}

if ($role_filter) {
    $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
    $params[] = $role_filter;
}

if ($search) {
    $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch all users and their compliance status
$sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, 
               uc.compliance_percentage, uc.status, uc.completed_count, uc.total_mandatory
        FROM {user} u
        LEFT JOIN {local_aa_user_compliance} uc ON uc.userid = u.id
        WHERE $where
        ORDER BY uc.compliance_percentage ASC, u.lastname ASC";

$records = $DB->get_records_sql($sql, $params);

?>

<div class="aa-dashboard-container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-800 m-0">Detailed Compliance Monitor</h2>
            <p class="text-muted">Tracking individual status for all mandatory training requirements.</p>
        </div>
        <a href="index.php?view=compliance&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="aa-btn aa-btn-primary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <!-- Summary Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="aa-card text-center">
                <div class="aa-kpi-label">TOTAL USERS</div>
                <div class="aa-kpi-val text-primary"><?php echo count($records); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="aa-card text-center">
                <div class="aa-kpi-label">COMPLIANT (100%)</div>
                <div class="aa-kpi-val text-success">
                    <?php echo count(array_filter($records, function($r) { return ($r->compliance_percentage ?? 0) >= 100; })); ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="aa-card text-center">
                <div class="aa-kpi-label">AT RISK / PENDING</div>
                <div class="aa-kpi-val text-warning">
                    <?php echo count(array_filter($records, function($r) { return ($r->compliance_percentage ?? 0) < 100; })); ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="aa-card text-center">
                <div class="aa-kpi-label">OVERDUE</div>
                <div class="aa-kpi-val text-danger">
                    <?php echo count(array_filter($records, function($r) { return ($r->status ?? '') === 'overdue'; })); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="aa-card">
        <div class="table-responsive">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Trained Items</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo fullname($r); ?></strong></td>
                            <td><?php echo $r->department ?: 'Unassigned'; ?></td>
                            <td><?php echo ($r->completed_count ?? 0) . ' / ' . ($r->total_mandatory ?? 0); ?></td>
                            <td>
                                <div class="progress" style="height: 10px; width: 120px;">
                                    <div class="progress-bar <?php echo ($r->compliance_percentage >= 100 ? 'bg-success' : 'bg-primary'); ?>" 
                                         style="width: <?php echo $r->compliance_percentage; ?>%"></div>
                                </div>
                                <small class="fw-bold mt-1 d-block"><?php echo round($r->compliance_percentage ?? 0); ?>%</small>
                            </td>
                            <td>
                                <?php 
                                $status = $r->status ?: 'pending';
                                $class = ($status == 'compliant') ? 'success' : (($status == 'overdue') ? 'danger' : 'warning');
                                ?>
                                <span class="aa-status-badge text-white" style="background: var(--aa-<?php echo $class; ?>);">
                                    <?php echo strtoupper($status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
