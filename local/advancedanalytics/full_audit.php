<?php
// local/advancedanalytics/full_audit.php
// Dedicated Compliance Audit Page - Standalone

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login();

$dept = optional_param('dept', '', PARAM_TEXT);
$courseid = optional_param('course', 0, PARAM_INT);
$role_filter = optional_param('role', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);

$PAGE->set_url('/local/advancedanalytics/full_audit.php');
$PAGE->set_context($context);
$PAGE->set_title("Full Compliance Audit");

$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'));
$PAGE->requires->css(new moodle_url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'));
$PAGE->requires->css(new moodle_url('/local/advancedanalytics/styles/styles.css'));

echo $OUTPUT->header();

global $DB;

$where = "u.deleted = 0 AND u.username != 'guest'";
$params = [];

if ($dept) {
    if ($dept === 'Unassigned') { $where .= " AND (u.department IS NULL OR u.department = '')"; }
    else { $where .= " AND u.department = ?"; $params[] = $dept; }
}
if ($courseid) {
    $where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
    $params[] = $courseid;
}
if ($role_filter) {
    $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
    $params[] = $role_filter;
}
if ($search) {
    $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$audit_data = $DB->get_records_sql("
    SELECT u.id, uc.compliance_percentage, uc.status, uc.completed_count, uc.total_mandatory, uc.timemodified,
           u.firstname, u.lastname, u.email, u.department, u.picture, u.imagealt,
           u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
    FROM {user} u
    LEFT JOIN {local_aa_user_compliance} uc ON uc.userid = u.id
    WHERE $where
    ORDER BY uc.status ASC, u.lastname ASC
", $params);

?>

<div class="aa-dashboard-container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-800 m-0">Organization Compliance Audit</h2>
            <p class="text-muted">Comprehensive regulatory reporting dashboard.</p>
        </div>
        <a href="index.php?view=compliance" class="aa-btn aa-btn-primary">Back to Dashboard</a>
    </div>

    <div class="aa-card">
        <div class="table-responsive">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Completion</th>
                        <th>Last Verified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($audit_data as $row): 
                        $status_class = $row->status == 'compliant' ? 'success' : ($row->status == 'overdue' ? 'danger' : 'warning');
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-2"><?php echo $OUTPUT->user_picture($row, ['size'=>35]); ?></div>
                                    <div><strong><?php echo fullname($row); ?></strong><br><small class="text-muted"><?php echo $row->email; ?></small></div>
                                </div>
                            </td>
                            <td><?php echo $row->department ?: 'Unassigned'; ?></td>
                            <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo strtoupper($row->status ?: 'PENDING'); ?></span></td>
                            <td class="fw-bold"><?php echo round($row->compliance_percentage ?? 0); ?>%</td>
                            <td class="text-muted"><?php echo $row->timemodified ? userdate($row->timemodified, '%d %b %Y') : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
