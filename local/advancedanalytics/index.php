<?php
// local/advancedanalytics/index.php
// THE Advanced Analytics Dashboard - Unified, Single-File Architecture - FULLY FIXED

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/analytics_engine.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/db_access.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/ai_engine.php');

$context = context_system::instance();
require_login();

// 1. INPUT HANDLING - ALL FILTERS
$view = optional_param('view', 'executive', PARAM_ALPHAEXT);
$courseid = optional_param('course', 0, PARAM_INT);
$dept = optional_param('dept', '', PARAM_TEXT);
$date_start = optional_param('start', date('Y-m-d', strtotime('-30 days')), PARAM_TEXT);
$date_end = optional_param('end', date('Y-m-d'), PARAM_TEXT);
$userid_drill = optional_param('userid', 0, PARAM_INT);
$status_filter = optional_param('status', '', PARAM_ALPHA);
$type_filter = optional_param('type', '', PARAM_ALPHA);
$role_filter = optional_param('role', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$export = optional_param('export', '', PARAM_ALPHA);
$mark_mandatory = optional_param('mark_mandatory', 0, PARAM_INT);
$unmark_mandatory = optional_param('unmark_mandatory', 0, PARAM_INT);

// 2. CAPABILITIES & RBAC
$is_admin = has_capability('local/advancedanalytics:viewadmin', $context);
$is_manager = has_capability('local/advancedanalytics:viewmanager', $context);
$is_hr = has_capability('local/advancedanalytics:viewhr', $context);

if (!$is_admin && !$is_manager && !$is_hr) {
    throw new moodle_exception('no_permission', 'local_advancedanalytics');
}

// Redirect to manager view if not admin/hr
if (!$is_admin && !$is_hr && $is_manager && $view === 'executive') {
    $view = 'manager';
}

// 3. ACTIONS HANDLING
if ($mark_mandatory && $is_admin) {
    if (!$DB->record_exists('local_aa_compliance', ['courseid' => $mark_mandatory])) {
        $DB->insert_record('local_aa_compliance', ['courseid' => $mark_mandatory, 'is_mandatory' => 1, 'timecreated' => time()]);
    }
}
if ($unmark_mandatory && $is_admin) {
    $DB->delete_records('local_aa_compliance', ['courseid' => $unmark_mandatory]);
}

// Handle export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analytics_report_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Department', 'Last Access']);
    $users = $DB->get_records('user', ['deleted' => 0], '', 'id, firstname, lastname, email, department, lastaccess', 0, 5000);
    foreach($users as $u) {
        fputcsv($output, [$u->id, fullname($u), $u->email, $u->department, userdate($u->lastaccess)]);
    }
    fclose($output);
    exit;
}

// 4. PAGE SETUP
$PAGE->set_url('/local/advancedanalytics/index.php', ['view' => $view]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_advancedanalytics'));
$PAGE->set_heading(get_string('pluginname', 'local_advancedanalytics'));
$PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'));
$PAGE->requires->css(new moodle_url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'));
$PAGE->requires->css(new moodle_url('/local/advancedanalytics/styles/styles.css'));

echo $OUTPUT->header();

global $DB, $USER;

// 5. SIDEBAR NAVIGATION ITEMS
$nav_items = [
    ['id' => 'executive', 'label' => 'Executive Overview', 'icon' => 'fa-tachometer-alt', 'cap' => $is_admin || $is_hr],
    ['id' => 'departments', 'label' => 'Dept. Analytics', 'icon' => 'fa-building', 'cap' => $is_admin || $is_hr],
    ['id' => 'learners', 'label' => 'Learner Performance', 'icon' => 'fa-user-graduate', 'cap' => true],
    ['id' => 'compliance', 'label' => 'Compliance Monitor', 'icon' => 'fa-clipboard-check', 'cap' => true],
    ['id' => 'manager', 'label' => 'My Team', 'icon' => 'fa-users', 'cap' => $is_manager || $is_admin],
    ['id' => 'reports', 'label' => 'Reports & Export', 'icon' => 'fa-file-export', 'cap' => true],
];
$nav_items = array_filter($nav_items, function($i) { return $i['cap']; });

?>

<div class="aa-dashboard-container">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-lg-2 d-none d-lg-block">
            <div class="aa-sidebar shadow-sm">
                <div class="mb-5 px-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary text-white rounded p-2 me-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="mb-0 fw-800">Analytics</h4>
                    </div>
                    <small class="text-muted fw-bold">PRO DASHBOARD</small>
                </div>
                <nav>
                    <?php foreach ($nav_items as $item): ?>
                        <a href="?view=<?php echo $item['id']; ?>&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="aa-nav-item <?php echo $view == $item['id'] ? 'active' : ''; ?>">
                            <i class="fas <?php echo $item['icon']; ?>"></i>
                            <?php echo $item['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-lg-10 p-4">
            <!-- Global Filter Bar -->
            <form class="aa-filters-bar shadow-sm mb-4" method="get" id="filterForm">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <div>
                    <label class="small fw-bold d-block mb-1 text-muted">COURSE</label>
                    <select name="course" class="aa-filter-select" style="min-width: 180px;">
                        <option value="0">All Courses</option>
                        <?php 
                        $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname', 'id, fullname');
                        foreach($courses as $cid => $cname): 
                            if($cid == SITEID) continue;
                            $selected = ($courseid == $cid) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $cid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($cname); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="small fw-bold d-block mb-1 text-muted">DEPT</label>
                    <select name="dept" class="aa-filter-select">
                        <option value="" <?php echo (empty($dept) ? 'selected' : ''); ?>>All Departments</option>
                        <option value="Unassigned" <?php echo ($dept == 'Unassigned' ? 'selected' : ''); ?>>Unassigned</option>
                        <?php 
                        $departments = $DB->get_records_sql("SELECT DISTINCT department FROM {user} WHERE department != '' AND department IS NOT NULL AND deleted = 0 ORDER BY department ASC");
                        foreach($departments as $d_row): 
                            $d = $d_row->department;
                            if(empty($d)) continue;
                            $selected = ($dept == $d) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="small fw-bold d-block mb-1 text-muted">ROLE</label>
                    <select name="role" class="aa-filter-select">
                        <option value="0">All Roles</option>
                        <?php 
                        $roles = $DB->get_records('role', [], 'sortorder', 'id, shortname, name');
                        foreach($roles as $role): 
                            $rid = $role->id;
                            $rname = $role->shortname;
                            if($rid == 1) continue; // Skip manager if needed or keep it
                            if(in_array($rname, ['guest', 'user', 'frontpage'])) continue;
                            
                            $role_context = context_system::instance();
                            $display_name = role_get_name($role, $role_context);
                            $selected = ($role_filter == $rid) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $rid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="small fw-bold d-block mb-1 text-muted">SEARCH</label>
                    <input type="text" name="search" value="<?php echo s($search); ?>" placeholder="Name/Email..." class="aa-filter-select" style="min-width: 160px;">
                </div>
                <div class="pt-4">
                    <button type="submit" class="aa-btn aa-btn-primary">Apply Filters</button>
                    <a href="?view=<?php echo $view; ?>" class="aa-btn btn-outline-secondary ms-2">Clear All</a>
                </div>
            </form>

            <!-- Dynamic View Loading -->
            <div id="dashboard-content">
                <?php
                if ($userid_drill) {
                    render_learner_detail_view($userid_drill, $view, $dept, $courseid, $role_filter);
                } else if ($view === 'learners' && $status_filter === 'active') {
                    render_active_learners_view($courseid, $dept, $search, $role_filter);
                } else if ($view === 'compliance_detail') {
                    render_compliance_detail_view($courseid, $dept, $role_filter, $search, $status_filter);
                } else if ($view === 'compliance_overdue') {
                    render_compliance_overdue_view($dept, $search, $role_filter);
                } else {
                    switch($view) {
                        case 'executive': render_executive_view($courseid, $dept, $role_filter, $search); break;
                        case 'departments': render_departments_view($dept, $courseid, $role_filter, $search); break;
                        case 'learners': render_learners_view($courseid, $dept, $search, $role_filter); break;
                        case 'compliance': render_compliance_view($courseid, $dept, $role_filter, $search); break;
                        case 'compliance_overdue': render_compliance_overdue_view($dept, $search, $role_filter); break;
                        case 'grade_report': render_grade_report_view($courseid, $dept, $role_filter, $search); break;
                        case 'course_completions': render_completions_report_view($courseid, $dept, $role_filter, $search); break;
                        case 'manager': render_manager_view($courseid, $role_filter, $search, $dept); break;
                        case 'reports': render_reports_view($courseid, $dept, $role_filter); break;
                        default: render_executive_view($courseid, $dept, $role_filter, $search);
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php

// ============================================
// EXECUTIVE VIEW
// ============================================

function render_executive_view($courseid=0, $dept='', $role_filter=0, $search='') {
    global $DB;
    
    $params = [];
    $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
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
        $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " 
                  OR " . $DB->sql_like('u.lastname', '?', false) . " 
                  OR " . $DB->sql_like('u.email', '?', false) . ")";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    // KPI Data
    $total_users = $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) FROM {user} u WHERE $where", $params);
    
    $cutoff = time() - (30 * 24 * 3600);
    $active_sql = "SELECT COUNT(DISTINCT u.id) 
                  FROM {user} u 
                  JOIN {logstore_standard_log} l ON l.userid = u.id 
                  WHERE $where AND l.timecreated > ?";
    $active_users = $DB->count_records_sql($active_sql, array_merge($params, [$cutoff]));
    
    $comp_where = $where . " AND cc.timecompleted > 0";
    $comp_params = $params;
    if ($courseid) {
        $comp_where .= " AND cc.course = ?";
        $comp_params[] = $courseid;
    }
    
    $comp_sql = "SELECT COUNT(DISTINCT u.id) 
                FROM {user} u 
                JOIN {course_completions} cc ON cc.userid = u.id 
                WHERE $comp_where";
    $users_completed = $DB->count_records_sql($comp_sql, $comp_params);
    $comp_rate = $total_users > 0 ? round(($users_completed / $total_users) * 100, 1) : 0;
    
    $grade_where = $where . " AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL";
    $grade_params = $params;
    if ($courseid) {
        $grade_where .= " AND gi.courseid = ?";
        $grade_params[] = $courseid;
    }
    
    $grade_avg = $DB->get_field_sql("
        SELECT AVG(
            CASE 
                WHEN gg.finalgrade > 100 THEN 100 
                WHEN gg.finalgrade < 0 THEN 0 
                ELSE gg.finalgrade 
            END
        ) 
        FROM {grade_grades} gg 
        JOIN {user} u ON u.id = gg.userid
        JOIN {grade_items} gi ON gi.id = gg.itemid 
        WHERE $grade_where
    ", $grade_params) ?: 0;
    
    // Active User Trends (Last 7 Days)
    $trend_labels = [];
    $trend_values = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $day_start = strtotime(date('Y-m-d', strtotime("-$i days")));
        $day_end = $day_start + 86400;
        $trend_labels[] = date('D, M j', $day_start);
        
        $day_where = "l.timecreated >= ? AND l.timecreated < ?";
        $day_params = [$day_start, $day_end];
        
        if ($dept) {
            if ($dept === 'Unassigned') {
                $day_where .= " AND (u.department IS NULL OR u.department = '')";
            } else {
                $day_where .= " AND u.department = ?";
                $day_params[] = $dept;
            }
        }
        
        if ($courseid) {
            $day_where .= " AND l.courseid = ?";
            $day_params[] = $courseid;
        }
        if ($role_filter) {
            $day_where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
            $day_params[] = $role_filter;
        }
        
        if ($search) {
            $day_where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
            $day_params[] = "%$search%"; $day_params[] = "%$search%"; $day_params[] = "%$search%";
        }
        
        $day_count = $DB->count_records_sql("
            SELECT COUNT(DISTINCT l.userid) 
            FROM {logstore_standard_log} l 
            JOIN {user} u ON u.id = l.userid 
            WHERE $day_where
        ", $day_params);
        
        $trend_values[] = $day_count;
    }
    
    // Department distribution
    $dept_stats = $DB->get_records_sql("
        SELECT COALESCE(u.department, 'Unassigned') as dept_name, COUNT(DISTINCT u.id) as user_count
        FROM {user} u
        WHERE $where
        GROUP BY COALESCE(u.department, 'Unassigned')
        ORDER BY user_count DESC
        LIMIT 6
    ", $params);
    
    $dept_labels = [];
    $dept_values = [];
    foreach ($dept_stats as $ds) {
        $dept_labels[] = $ds->dept_name;
        $dept_values[] = $ds->user_count;
    }
    
    ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="?view=learners&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none">
                <div class="aa-card aa-card-clickable text-center">
                    <div class="aa-kpi-label">TOTAL USERS</div>
                    <div class="aa-kpi-val text-primary"><?php echo number_format($total_users); ?></div>
                    <div class="small text-muted mt-1">Click to view</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?view=learners&status=active&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="text-decoration-none">
                <div class="aa-card aa-card-clickable text-center">
                    <div class="aa-kpi-label">ACTIVE (30D)</div>
                    <div class="aa-kpi-val text-success"><?php echo number_format($active_users); ?></div>
                    <div class="small text-muted mt-1"><?php echo $total_users > 0 ? round(($active_users / $total_users) * 100, 1) : 0; ?>% engagement</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?view=course_completions&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none">
                <div class="aa-card aa-card-clickable text-center">
                    <div class="aa-kpi-label">COMPLETION</div>
                    <div class="aa-kpi-val text-info"><?php echo $comp_rate; ?>%</div>
                    <div class="small text-muted mt-1"><?php echo $users_completed; ?> users completed courses</div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?view=grade_report&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="text-decoration-none">
                <div class="aa-card aa-card-clickable text-center">
                    <div class="aa-kpi-label">AVG GRADE</div>
                    <div class="aa-kpi-val text-warning"><?php echo number_format($grade_avg, 1); ?>%</div>
                    <div class="small text-muted mt-1">Overall average</div>
                </div>
            </a>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-8">
            <div class="aa-card">
                <h5><i class="fas fa-chart-line text-primary me-2"></i> Active User Trends (Last 7 Days)</h5>
                <div class="aa-php-chart-v">
                    <?php 
                    $max_val = max(1, ...($trend_values ?: [1]));
                    foreach ($trend_labels as $idx => $label): 
                        $val = $trend_values[$idx] ?? 0;
                        $pct = ($val / $max_val) * 100;
                    ?>
                        <div class="aa-php-bar-v-container">
                            <div class="aa-php-bar-v" style="height: <?php echo $pct; ?>%;" data-value="<?php echo $val; ?>"></div>
                            <div class="aa-php-bar-label"><?php echo substr($label, 0, 3); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="aa-card">
                <h5><i class="fas fa-building text-success me-2"></i> Department Distribution</h5>
                <div class="aa-php-chart-h">
                    <?php 
                    $max_dept = max(1, ...($dept_values ?: [1]));
                    foreach ($dept_labels as $idx => $label): 
                        $val = $dept_values[$idx] ?? 0;
                        $pct = ($val / $max_dept) * 100;
                    ?>
                        <div class="aa-php-row-h">
                            <div class="aa-php-label-h" title="<?php echo $label; ?>"><?php echo $label; ?></div>
                            <div class="aa-php-bar-wrapper-h">
                                <div class="aa-php-bar-h" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <div class="aa-php-val-h"><?php echo $val; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ============================================
// LEARNERS VIEW
// ============================================

function render_learners_view($courseid, $dept, $search, $role_filter) {
    global $DB;
    
    $params = [];
    $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
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
        $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " 
                  OR " . $DB->sql_like('u.lastname', '?', false) . " 
                  OR " . $DB->sql_like('u.email', '?', false) . ")";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    $users = $DB->get_records_sql("
        SELECT u.id, u.firstname, u.lastname, u.email, u.department, u.picture, u.imagealt,
               COALESCE(up.engagement_score, 0) as engagement_score,
               COALESCE(up.completion_percentage, 0) as completion_percentage,
               COALESCE(up.risk_level, 'low') as risk_level,
               COALESCE(up.login_count, 0) as login_count
        FROM {user} u
        LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id
        WHERE $where
        ORDER BY up.engagement_score DESC, u.lastname ASC
    ", $params, 0, 100);
    
    $high_risk = 0;
    $engagement_total = 0;
    $engagement_count = 0;
    foreach ($users as $u) {
        if ($u->risk_level == 'high') $high_risk++;
        if ($u->engagement_score > 0) {
            $engagement_total += $u->engagement_score;
            $engagement_count++;
        }
    }
    $avg_engagement = $engagement_count > 0 ? round($engagement_total / $engagement_count, 1) : 0;
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">Total Learners</div><div class="aa-kpi-val text-primary"><?php echo count($users); ?></div></div></div>
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">Avg Engagement</div><div class="aa-kpi-val text-success"><?php echo $avg_engagement; ?>%</div></div></div>
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">High Risk</div><div class="aa-kpi-val text-danger"><?php echo $high_risk; ?></div></div></div>
    </div>
    
    <div class="aa-card">
        <h5><i class="fas fa-user-graduate me-2 text-primary"></i> Individual Performance Scores</h5>
        <div class="table-responsive">
            <table class="aa-table">
                <thead><tr><th>#</th><th>User</th><th>Email</th><th>Dept</th><th>Engage</th><th>Progress</th><th>Risk</th><th>Action</th></tr></thead>
                <tbody>
                    <?php $i = 1; foreach($users as $u): 
                        $risk_color = $u->risk_level == 'high' ? '#ef4444' : ($u->risk_level == 'medium' ? '#f59e0b' : '#10b981');
                        $eng_color = $u->engagement_score >= 70 ? 'text-success' : ($u->engagement_score >= 40 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo fullname($u); ?></strong></td>
                        <td><small><?php echo $u->email; ?></small></td>
                        <td><?php echo $u->department ?: 'Unassigned'; ?></td>
                        <td class="<?php echo $eng_color; ?> fw-bold"><?php echo round($u->engagement_score); ?>%</td>
                        <td><?php echo round($u->completion_percentage); ?>%</td>
                        <td><span class="aa-status-badge text-white" style="background: <?php echo $risk_color; ?>;"><?php echo strtoupper($u->risk_level); ?></span></td>
                        <td><a href="?userid=<?php echo $u->id; ?>&view=learners" class="btn btn-sm btn-outline-primary">Details</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function render_learner_detail_view($userid, $view, $dept, $courseid, $role_filter) {
    global $DB;
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $perf = $DB->get_record('local_aa_user_perf', ['userid' => $userid]);
    $score_data = \local_advancedanalytics\learner_scoring::calculate_learner_score($userid);
    $progress_data = \local_advancedanalytics\learner_scoring::get_learner_progress($userid);
    
    $completed_courses = $DB->get_records_sql("
        SELECT c.id, c.fullname, cc.timecompleted
        FROM {course_completions} cc
        JOIN {course} c ON c.id = cc.course
        WHERE cc.userid = ? AND cc.timecompleted IS NOT NULL
        ORDER BY cc.timecompleted DESC
    ", [$userid]);
    ?>
    <div class="aa-card">
        <div class="d-flex justify-content-between mb-4">
            <a href="?view=<?php echo $view; ?>&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
            <a href="user_login_stats.php?userid=<?php echo $userid; ?>" class="btn btn-sm btn-info" target="_blank">📊 Full Login Stats</a>
        </div>
        
        <div class="row align-items-center mb-4">
            <div class="col-md-2 text-center"><?php global $OUTPUT; echo $OUTPUT->user_picture($user, array('size' => 100)); ?></div>
            <div class="col-md-7">
                <div class="d-flex align-items-center mb-2">
                    <h3 class="m-0 me-3"><?php echo fullname($user); ?></h3>
                    <span class="badge bg-<?php echo ($perf->risk_level ?? 'low') == 'high' ? 'danger' : (($perf->risk_level ?? 'low') == 'medium' ? 'warning' : 'success'); ?> fs-6"><?php echo strtoupper($perf->risk_level ?? 'LOW'); ?> RISK</span>
                </div>
                <p class="text-muted m-0"><i class="fas fa-envelope me-1"></i> <?php echo $user->email; ?></p>
                <p class="text-muted m-0"><i class="fas fa-building me-1"></i> <?php echo $user->department ?: 'Unassigned'; ?></p>
                <div class="mt-2">
                    <span class="badge bg-primary-soft text-primary border border-primary px-2 py-1"><i class="fas fa-chart-line me-1"></i> <?php echo $score_data['performance_level']; ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="aa-card text-center mb-0 border-primary" style="background-color: #f0f7ff;">
                    <div class="small fw-bold text-primary mb-1 uppercase">Engagement Score</div>
                    <div class="h1 mb-0 text-primary fw-800"><?php echo round($score_data['overall_score']); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4 g-3">
            <div class="col-md-3">
                <div class="bg-white p-3 rounded border shadow-sm text-center">
                    <h6 class="text-muted small uppercase fw-bold mb-2">Time Spent</h6>
                    <div class="h3 text-dark mb-0"><?php echo $score_data['stats']['time_spent']; ?> <small class="fs-6">min</small></div>
                    <div class="x-small text-muted mt-1">Est. platform activity</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded border shadow-sm text-center">
                    <h6 class="text-muted small uppercase fw-bold mb-2">Quiz Performance</h6>
                    <div class="h3 text-success mb-0"><?php echo $score_data['stats']['avg_quiz_grade']; ?>%</div>
                    <div class="x-small text-muted mt-1"><?php echo $score_data['stats']['quizzes_taken']; ?> assessments taken</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded border shadow-sm text-center">
                    <h6 class="text-muted small uppercase fw-bold mb-2">Login Consistency</h6>
                    <div class="h3 text-info mb-0"><?php echo $score_data['scores']['consistency']; ?>%</div>
                    <div class="x-small text-muted mt-1"><?php echo $score_data['stats']['active_days']; ?> active days (30d)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white p-3 rounded border shadow-sm text-center">
                    <h6 class="text-muted small uppercase fw-bold mb-2">Progress History</h6>
                    <div class="h3 text-success mb-0">
                        <?php echo $progress_data['total_completions']; ?> <small class="fs-6">Milestones</small>
                    </div>
                    <div class="x-small text-muted mt-1">
                        <?php echo $progress_data['last_completion'] ? 'Last achievement: ' . date('d M Y', strtotime($progress_data['last_completion'])) : 'No milestones yet'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-7">
                <h5 class="fw-bold mb-3"><i class="fas fa-clipboard-check text-success me-2"></i> Mandatory Training Status</h5>
                <div class="table-responsive">
                    <table class="aa-table">
                        <thead><tr><th>Requirement</th><th>Enrolled</th><th>Status</th><th>Date Completed</th></tr></thead>
                        <tbody>
                            <?php 
                            $mandatory = $DB->get_records('local_aa_compliance', ['is_mandatory' => 1]);
                            foreach($mandatory as $m): 
                                $c = $DB->get_record('course', ['id' => $m->courseid], 'id, fullname');
                                if (!$c) continue;
                                $is_enrolled = $DB->record_exists_sql("SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = ? AND e.courseid = ?", [$userid, $m->courseid]);
                                $comp = $DB->get_record('course_completions', ['userid' => $userid, 'course' => $m->courseid]);
                                $is_comp = ($comp && $comp->timecompleted);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c->fullname); ?></strong></td>
                                <td><?php echo $is_enrolled ? '<span class="text-success"><i class="fas fa-check me-1"></i> Yes</span>' : '<span class="text-muted">No</span>'; ?></td>
                                <td>
                                    <?php if($is_comp): ?>
                                        <span class="badge bg-success">COMPLIANT</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $is_comp ? userdate($comp->timecompleted, '%d %b %Y') : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-5">
                <h5 class="fw-bold mb-3"><i class="fas fa-stream text-primary me-2"></i> Recent Activity Timeline</h5>
                <div class="aa-timeline p-3 border rounded bg-white shadow-sm" style="max-height: 400px; overflow-y: auto;">
                    <?php 
                    $recent_logs = $DB->get_records_sql("
                        SELECT l.id, l.timecreated, l.action, l.eventname, c.fullname as coursename
                        FROM {logstore_standard_log} l
                        LEFT JOIN {course} c ON c.id = l.courseid
                        WHERE l.userid = ?
                        ORDER BY l.timecreated DESC
                        LIMIT 10
                    ", [$userid]);
                    
                    if (empty($recent_logs)): ?>
                        <div class="text-center py-4 text-muted small">No recent activity recorded.</div>
                    <?php else: foreach ($recent_logs as $log): 
                        $icon = 'fa-circle';
                        $color = 'primary';
                        if (strpos($log->eventname, 'course') !== false) { $icon = 'fa-book-open'; $color = 'info'; }
                        if (strpos($log->eventname, 'quiz') !== false) { $icon = 'fa-question-circle'; $color = 'warning'; }
                        if ($log->action == 'loggedin') { $icon = 'fa-sign-in-alt'; $color = 'success'; }
                        if ($log->action == 'viewed') { $icon = 'fa-eye'; $color = 'secondary'; }
                    ?>
                        <div class="d-flex mb-3 align-items-start border-bottom pb-2">
                            <div class="bg-<?php echo $color; ?>-soft text-<?php echo $color; ?> rounded-circle p-2 me-3" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?php echo $icon; ?> small"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="small fw-bold text-dark"><?php echo ucfirst($log->action); ?> <?php echo $log->coursename ? 'in ' . htmlspecialchars(substr($log->coursename, 0, 30)) . '...' : ''; ?></span>
                                    <span class="x-small text-muted"><?php echo userdate($log->timecreated, '%d %b, %H:%M'); ?></span>
                                </div>
                                <div class="x-small text-muted mt-1"><?php echo str_replace('\\', '/', substr($log->eventname, strrpos($log->eventname, '\\') + 1)); ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function render_active_learners_view($courseid, $dept, $search, $role_filter) {
    global $DB;
    $cutoff = time() - (30 * 24 * 3600);
    
    $params = [$cutoff];
    $where = "l.timecreated > ? AND u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
    }
    if ($courseid) {
        $where .= " AND l.courseid = ?";
        $params[] = $courseid;
    }
    if ($role_filter) {
        $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
        $params[] = $role_filter;
    }
    if ($search) {
        $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    $users = $DB->get_records_sql("
        SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.department, MAX(l.timecreated) as last_access
        FROM {logstore_standard_log} l
        JOIN {user} u ON u.id = l.userid
        WHERE $where
        GROUP BY u.id, u.firstname, u.lastname, u.email, u.department
        ORDER BY last_access DESC
        LIMIT 50
    ", $params);
    ?>
    <div class="aa-card">
        <div class="d-flex justify-content-between mb-4">
            <h5><i class="fas fa-bolt text-warning me-2"></i>Active Users (Last 30 Days)</h5>
            <a href="?view=learners" class="btn btn-sm btn-outline-secondary">Show All</a>
        </div>
        <div class="table-responsive">
            <table class="aa-table">
                <thead><tr><th>#</th><th>User</th><th>Email</th><th>Dept</th><th>Last Access</th></tr></thead>
                <tbody>
                    <?php $i = 1; foreach($users as $u): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><strong><?php echo fullname($u); ?></strong></td>
                        <td><?php echo $u->email; ?></td>
                        <td><?php echo $u->department ?: 'Unassigned'; ?></td>
                        <td><?php echo userdate($u->last_access, '%Y-%m-%d'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function render_departments_view($dept, $courseid, $role_filter, $search='') {
    global $DB;
    
    $params = [];
    $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
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
    
    $stats = $DB->get_records_sql("
        SELECT CASE WHEN u.department IS NULL OR u.department = '' THEN 'Unassigned' ELSE u.department END as dept_name, 
               COUNT(DISTINCT u.id) as total_employees,
               COALESCE(AVG(up.engagement_score), 0) as avg_performance,
               COALESCE(AVG(up.completion_percentage), 0) as completion_rate
        FROM {user} u
        LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id
        WHERE $where
        GROUP BY CASE WHEN u.department IS NULL OR u.department = '' THEN 'Unassigned' ELSE u.department END
        ORDER BY completion_rate DESC
    ", $params);
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="aa-card">
                <h5><i class="fas fa-chart-bar text-info me-2"></i> Departmental Performance Comparison</h5>
                <div class="aa-php-chart-v">
                    <?php 
                    $max_rate = 100; // Since it's completion rate percentage
                    foreach ($stats as $s): 
                        $val = round($s->completion_rate, 1);
                        $pct = $val; // Out of 100
                    ?>
                        <div class="aa-php-bar-v-container">
                            <div class="aa-php-bar-v" style="height: <?php echo $pct; ?>%; background: linear-gradient(to top, var(--aa-info), #93c5fd);" data-value="<?php echo $val; ?>%"></div>
                            <div class="aa-php-bar-label"><?php echo substr($s->dept_name, 0, 5); ?>..</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="aa-card">
        <h5><i class="fas fa-building me-2 text-primary"></i> Departmental Benchmarking</h5>
        <div class="table-responsive">
            <table class="aa-table">
                <thead><tr><th>#</th><th>Department</th><th>Employees</th><th>Completion Rate</th><th>Avg Engagement</th></tr></thead>
                <tbody>
                    <?php 
                    $chart_labels = [];
                    $chart_data = [];
                    $i = 1; 
                    foreach($stats as $s): 
                        $chart_labels[] = $s->dept_name;
                        $chart_data[] = round($s->completion_rate, 1);
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <a href="?view=learners&dept=<?php echo urlencode($s->dept_name); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="text-decoration-none fw-bold">
                                <i class="fas fa-search me-1 small opacity-50"></i> <?php echo $s->dept_name; ?>
                            </a>
                        </td>
                        <td><?php echo $s->total_employees; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $s->completion_rate; ?>%"></div>
                                </div>
                                <span><?php echo round($s->completion_rate); ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $s->avg_performance; ?>%"></div>
                                </div>
                                <span><?php echo round($s->avg_performance); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function render_compliance_view($courseid, $dept, $role_filter, $search='') {
    global $DB, $is_admin;
    
    // 1. Build Filtered Query for Counts
    $where = "1=1"; // Start with a safe true condition
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
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    // 1. Fetch Summary Data for Chart
    $sql_base = "SELECT COUNT(*) FROM {local_aa_user_compliance} uc JOIN {user} u ON u.id = uc.userid WHERE $where";
    $compliant_count = $DB->count_records_sql($sql_base . " AND uc.status = ?", array_merge($params, ['compliant']));
    $pending_count   = $DB->count_records_sql($sql_base . " AND uc.status = ?", array_merge($params, ['pending']));
    $overdue_count   = $DB->count_records_sql($sql_base . " AND uc.status = ?", array_merge($params, ['overdue']));
    
    $mandatory_ids = $DB->get_fieldset_select('local_aa_compliance', 'courseid', 'is_mandatory = 1');
    ?>
    <div class="row g-4">
        <div class="col-md-7">
            <div class="aa-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="m-0"><i class="fas fa-clipboard-check me-2 text-success"></i> Workforce Compliance Status <?php echo $dept ? "($dept)" : ""; ?></h5>
                    <span class="badge bg-light text-dark border">Updated Today</span>
                </div>
                <div class="aa-php-chart-h" style="height: 320px; justify-content: center;">
                    <?php 
                    $total_comp = $compliant_count + $pending_count + $overdue_count;
                    $total_div = max(1, $total_comp);
                    $comp_p = ($compliant_count / $total_div) * 100;
                    $pend_p = ($pending_count / $total_div) * 100;
                    $over_p = ($overdue_count / $total_div) * 100;
                    ?>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>COMPLIANT (<?php echo $compliant_count; ?>)</span><span><?php echo round($comp_p); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo $comp_p; ?>%; background: var(--aa-success);"></div></div>
                    </div>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>PENDING (<?php echo $pending_count; ?>)</span><span><?php echo round($pend_p); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo $pend_p; ?>%; background: var(--aa-warning);"></div></div>
                    </div>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>OVERDUE (<?php echo $overdue_count; ?>)</span><span><?php echo round($over_p); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo $over_p; ?>%; background: var(--aa-danger);"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="aa-card h-100">
                <h5 class="text-danger mb-4"><i class="fas fa-exclamation-triangle me-2"></i>Critical Compliance Alerts</h5>
                
                <a href="?view=compliance_overdue&dept=<?php echo urlencode($dept); ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none">
                    <div class="aa-alert-item mb-3 p-3 rounded border-start border-4 border-danger bg-light aa-card-clickable">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-800 text-danger h4 mb-0"><?php echo $overdue_count; ?></div>
                                <div class="small fw-bold text-muted uppercase">Users Overdue</div>
                            </div>
                            <i class="fas fa-clock fa-2x text-danger opacity-25"></i>
                        </div>
                    </div>
                </a>

                <a href="?view=compliance_detail&status=pending&dept=<?php echo urlencode($dept); ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none">
                    <div class="aa-alert-item mb-3 p-3 rounded border-start border-4 border-warning bg-light aa-card-clickable">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-800 text-warning h4 mb-0"><?php echo $pending_count; ?></div>
                                <div class="small fw-bold text-muted uppercase">Pending Completion</div>
                            </div>
                            <i class="fas fa-hourglass-half fa-2x text-warning opacity-25"></i>
                        </div>
                    </div>
                </a>

                <a href="?view=compliance_detail&status=compliant&dept=<?php echo urlencode($dept); ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="text-decoration-none">
                    <div class="aa-alert-item p-3 rounded border-start border-4 border-success bg-light aa-card-clickable">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-800 text-success h4 mb-0"><?php echo $compliant_count; ?></div>
                                <div class="small fw-bold text-muted uppercase">Fully Compliant</div>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                        </div>
                    </div>
                </a>
                
                <div class="mt-4 text-center">
                    <a href="?view=compliance_detail&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-primary w-100 py-2">
                        <i class="fas fa-list-check me-2"></i> View Full Compliance Audit
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="aa-card mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="m-0">Mandatory Course Requirements</h5>
            <div class="aa-badge bg-primary-soft text-primary px-3 py-1 rounded-pill small fw-bold">
                <?php echo count($mandatory_ids); ?> Courses Active
            </div>
        </div>
        <div class="table-responsive">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Req. Type</th>
                        <th>Total Enrolled</th>
                        <th>Compliance %</th>
                        <?php if($is_admin): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $course_filter_sql = "visible = 1";
                    $course_filter_params = [];
                    if ($courseid) {
                        $course_filter_sql .= " AND id = ?";
                        $course_filter_params[] = $courseid;
                    }
                    $all_courses = $DB->get_records_select('course', $course_filter_sql, $course_filter_params, 'fullname', 'id, fullname', 0, 50);
                    
                    // Build additional where for course-specific counts based on global filters
                    $c_where = "";
                    $c_params = [];
                    if ($dept) {
                        if ($dept === 'Unassigned') {
                            $c_where .= " AND (u.department IS NULL OR u.department = '')";
                        } else {
                            $c_where .= " AND u.department = ?";
                            $c_params[] = $dept;
                        }
                    }
                    if ($role_filter) {
                        $c_where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
                        $c_params[] = $role_filter;
                    }
                    if ($search) {
                        $c_where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
                        $c_params[] = '%' . $search . '%';
                        $c_params[] = '%' . $search . '%';
                        $c_params[] = '%' . $search . '%';
                    }

                    foreach($all_courses as $c): 
                        if($c->id == SITEID) continue;
                        $is_m = in_array($c->id, $mandatory_ids);
                        
                        $total_enrolled = 0;
                        $comp_pct = 0;
                        
                        if ($is_m) {
                            $enrol_sql = "SELECT COUNT(DISTINCT u.id) 
                                         FROM {user} u 
                                         JOIN {user_enrolments} ue ON ue.userid = u.id 
                                         JOIN {enrol} e ON e.id = ue.enrolid 
                                         WHERE e.courseid = ? AND u.deleted = 0 AND u.suspended = 0 $c_where";
                            $total_enrolled = $DB->count_records_sql($enrol_sql, array_merge([$c->id], $c_params));
                            
                            $comp_sql = "SELECT COUNT(DISTINCT u.id) 
                                        FROM {user} u 
                                        JOIN {course_completions} cc ON cc.userid = u.id 
                                        WHERE cc.course = ? AND cc.timecompleted IS NOT NULL AND u.deleted = 0 AND u.suspended = 0 $c_where";
                            $completed = $DB->count_records_sql($comp_sql, array_merge([$c->id], $c_params));
                            
                            $comp_pct = $total_enrolled > 0 ? round(($completed / $total_enrolled) * 100) : 0;
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($c->fullname); ?></div>
                            <div class="text-muted small">ID: <?php echo $c->id; ?></div>
                        </td>
                        <td>
                            <?php if($is_m): ?>
                                <span class="badge bg-danger shadow-sm">MANDATORY</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">OPTIONAL</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold"><?php echo $is_m ? $total_enrolled : '-'; ?></td>
                        <td>
                            <?php if($is_m): ?>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-2" style="height: 8px; background: #eaecf0;">
                                        <div class="progress-bar bg-<?php echo ($comp_pct >= 80 ? 'success' : ($comp_pct >= 50 ? 'warning' : 'danger')); ?>" style="width: <?php echo $comp_pct; ?>%"></div>
                                    </div>
                                    <span class="small fw-bold <?php echo ($comp_pct >= 80 ? 'text-success' : ($comp_pct >= 50 ? 'text-warning' : 'text-danger')); ?>"><?php echo $comp_pct; ?>%</span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">N/A</span>
                            <?php endif; ?>
                        </td>
                        <?php if($is_admin): ?>
                        <td>
                            <?php if($is_m): ?>
                                <a href="?unmark_mandatory=<?php echo $c->id; ?>&view=compliance&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-times me-1"></i> Unmark
                                </a>
                            <?php else: ?>
                                <a href="?mark_mandatory=<?php echo $c->id; ?>&view=compliance&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-check me-1"></i> Mark
                                </a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
}

function render_manager_view($courseid, $role_filter, $search='', $dept='') {
    global $USER, $DB;
    
    // Use selected department filter if present, otherwise fallback to own department
    $my_dept = $dept ?: $USER->department;
    
    if (empty($my_dept)) { 
        echo "<div class='aa-card bg-warning'><h4>No Department Set</h4><p>Please select a department from the filter above or update your profile to see team data.</p></div>"; 
        return; 
    }
    
    $params = [$my_dept];
    $where = "u.department = ? AND u.deleted = 0 AND u.suspended = 0";
    
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
    
    // Only show Managers and Admins (editing teachers, etc.)
    $where .= " AND EXISTS (
        SELECT 1 FROM {role_assignments} ra 
        JOIN {role} r ON r.id = ra.roleid 
        WHERE ra.userid = u.id AND r.shortname IN ('manager', 'editingteacher', 'coursecreator', 'admin')
    )";

    $team = $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.email, COALESCE(up.engagement_score, 0) as engagement_score, COALESCE(up.completion_percentage, 0) as completion_pct, COALESCE(up.risk_level, 'low') as risk_level FROM {user} u LEFT JOIN {local_aa_user_perf} up ON up.userid = u.id WHERE $where ORDER BY up.risk_level DESC, u.lastname ASC", $params);
    ?>
    <div class="aa-card mb-4" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white;"><h4 class="mb-1">Team Performance: <?php echo htmlspecialchars($my_dept); ?></h4></div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">Team Size</div><div class="aa-kpi-val text-primary"><?php echo count($team); ?></div></div></div>
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">Avg Engagement</div><div class="aa-kpi-val text-success"><?php $avg = count($team)>0 ? round(array_sum(array_column($team,'engagement_score'))/count($team),1) : 0; echo $avg; ?>%</div></div></div>
        <div class="col-md-4"><div class="aa-card text-center"><div class="aa-kpi-label">High Risk</div><div class="aa-kpi-val text-danger"><?php $high = count(array_filter($team, function($t){ return $t->risk_level == 'high'; })); echo $high; ?></div></div></div>
    </div>
    <div class="aa-card"><h5>Team Members</h5><div class="table-responsive"><table class="aa-table"><thead><tr><th>#</th><th>Member</th><th>Email</th><th>Engagement</th><th>Completion</th><th>Risk</th><th>Action</th></tr></thead><tbody><?php $i=1; foreach($team as $t): $risk_color = $t->risk_level == 'high' ? '#ef4444' : ($t->risk_level == 'medium' ? '#f59e0b' : '#10b981'); ?><tr><td><?php echo $i++; ?></td><td><strong><?php echo fullname($t); ?></strong></td><td><?php echo $t->email; ?></td><td><?php echo round($t->engagement_score); ?>%</td><td><?php echo round($t->completion_pct); ?>%</td><td><span class="aa-status-badge text-white" style="background:<?php echo $risk_color; ?>"><?php echo strtoupper($t->risk_level); ?></span></td><td><a href="?userid=<?php echo $t->id; ?>&view=manager" class="btn btn-sm btn-outline-primary">Details</a></td></tr><?php endforeach; ?></tbody></table></div></div><?php
}



function render_reports_view($courseid, $dept, $role_filter) {
    global $DB;
    
    $params = [];
    $where = "u.deleted = 0 AND u.suspended = 0 AND u.username != 'guest'";
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
    }
    if ($courseid) {
        $where .= " AND EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE ue.userid = u.id AND e.courseid = ?)";
        $params[] = $courseid;
    }
    if ($role_filter) {
        $where .= " AND EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = ?)";
        $params[] = $role_filter;
    }
    ?>
    <div class="aa-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="m-0"><i class="fas fa-file-export me-2 text-primary"></i> Enterprise Report Builder</h5>
            <div class="text-muted small fw-bold">Select format to download</div>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="p-4 border rounded text-center h-100 shadow-sm bg-white aa-export-card mb-3">
                    <i class="fas fa-file-excel fa-3x mb-3 text-success opacity-75"></i>
                    <h6 class="fw-bold">Excel Sheet</h6>
                    <p class="small text-muted mb-3">Organization-wide metrics</p>
                    <a href="export.php?type=compliance&format=excel&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 border rounded text-center h-100 shadow-sm bg-white aa-export-card mb-3">
                    <i class="fas fa-chart-pie fa-3x mb-3 text-info opacity-75"></i>
                    <h6 class="fw-bold">Risk Summary</h6>
                    <p class="small text-muted mb-3">PDF highlight report</p>
                    <a href="export.php?type=learners&format=pdf&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-info btn-sm w-100 text-white">
                        <i class="fas fa-file-pdf me-1"></i> Generate
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 border rounded text-center h-100 shadow-sm bg-white aa-export-card mb-3">
                    <i class="fas fa-globe fa-3x mb-3 text-primary opacity-75"></i>
                    <h6 class="fw-bold">Master Report</h6>
                    <p class="small text-muted mb-3">Full Org oversight (All Data)</p>
                    <a href="export.php?type=master&format=pdf&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="fas fa-file-pdf me-1"></i> Full PDF
                    </a>
                    <a href="export.php?type=master&format=excel&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fas fa-file-excel me-1"></i> Full Excel
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 border rounded text-center h-100 shadow-sm bg-white aa-export-card">
                    <i class="fas fa-calendar-check fa-3x mb-3 text-warning opacity-75"></i>
                    <h6 class="fw-bold">Automation</h6>
                    <p class="small text-muted mb-3">Schedule weekly email delivery</p>
                    <button class="btn btn-warning btn-sm w-100 text-white" onclick="document.getElementById('scheduler-panel').classList.toggle('d-none')">
                        <i class="fas fa-cog me-1"></i> Configure
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden Scheduler Panel -->
        <div id="scheduler-panel" class="d-none mt-4 p-4 border rounded bg-light">
            <h6 class="fw-bold mb-3">Create New Automated Report</h6>
            <form action="scheduler_handler.php" method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Report Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="master">Master (All Data)</option>
                        <option value="executive">Executive Summary</option>
                        <option value="compliance">Compliance Audit</option>
                        <option value="learners">Learner Performance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Frequency</label>
                    <select name="frequency" class="form-select form-select-sm">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Format</label>
                    <select name="format" class="form-select form-select-sm">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Save Schedule</button>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label small">Recipients (emails, separated by comma)</label>
                    <input type="text" name="recipients" class="form-control form-control-sm" placeholder="manager@example.com, hr@example.com">
                </div>
            </form>
            
            <div class="mt-4">
                <h6 class="fw-bold small text-muted">Active Schedules</h6>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead><tr><th>Report</th><th>Frequency</th><th>Format</th><th>Recipients</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php 
                            $schedules = $DB->get_records('local_aa_reports', ['status' => 1]);
                            foreach($schedules as $s): ?>
                            <tr>
                                <td><?php echo strtoupper($s->report_type); ?></td>
                                <td><?php echo strtoupper($s->frequency); ?></td>
                                <td><?php echo strtoupper($s->format); ?></td>
                                <td><?php echo $s->recipients; ?></td>
                                <td><a href="scheduler_handler.php?delete=<?php echo $s->id; ?>" class="text-danger"><i class="fas fa-trash"></i></a></td>
                            </tr>
                            <?php endforeach; if(empty($schedules)) echo "<tr><td colspan='5' class='text-center text-muted'>No schedules active.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="aa-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="m-0">Organizational Health</h5>
                    <i class="fas fa-heartbeat text-danger"></i>
                </div>
                <div class="aa-php-chart-h">
                    <?php
                    $health_where = "userid IN (SELECT id FROM {user} u WHERE $where)";
                    $h_risk = $DB->count_records_select('local_aa_user_perf', "risk_level = 'high' AND $health_where", $params);
                    $m_risk = $DB->count_records_select('local_aa_user_perf', "risk_level = 'medium' AND $health_where", $params);
                    $l_risk = $DB->count_records_select('local_aa_user_perf', "risk_level = 'low' AND $health_where", $params);
                    $total_h = max(1, $h_risk + $m_risk + $l_risk);
                    ?>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>High Risk</span><span><?php echo round(($h_risk/$total_h)*100); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo ($h_risk/$total_h)*100; ?>%; background: var(--aa-danger);"></div></div>
                    </div>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>Med Risk</span><span><?php echo round(($m_risk/$total_h)*100); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo ($m_risk/$total_h)*100; ?>%; background: var(--aa-warning);"></div></div>
                    </div>
                    <div class="aa-php-compliance-bar">
                        <div class="aa-php-compliance-header"><span>Low Risk</span><span><?php echo round(($l_risk/$total_h)*100); ?>%</span></div>
                        <div class="aa-php-bar-wrapper-h"><div class="aa-php-bar-h" style="width: <?php echo ($l_risk/$total_h)*100; ?>%; background: var(--aa-success);"></div></div>
                    </div>
                </div>
                <div class="mt-3 text-center small text-muted">Based on Risk Levels (High/Med/Low)</div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="aa-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="m-0"><i class="fas fa-history me-2 text-primary"></i> Recent Successes</h5>
                    <span class="badge bg-success-soft text-success px-3">Last 10 Completions</span>
                </div>
                <div class="table-responsive">
                    <table class="aa-table">
                        <thead>
                            <tr>
                                <th>Learner</th>
                                <th>Achievement</th>
                                <th>Completion Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent = $DB->get_records_sql("
                                SELECT cc.id, u.firstname, u.lastname, c.fullname as course, cc.timecompleted 
                                FROM {course_completions} cc 
                                JOIN {user} u ON u.id = cc.userid 
                                JOIN {course} c ON c.id = cc.course 
                                WHERE cc.timecompleted IS NOT NULL AND $where
                                ORDER BY cc.timecompleted DESC 
                                LIMIT 10
                            ", $params); 
                            foreach($recent as $r): ?>
                                <tr>
                                    <td><strong><?php echo fullname($r); ?></strong></td>
                                    <td><span class="text-primary fw-600"><?php echo htmlspecialchars(substr($r->course,0,45)); ?></span></td>
                                    <td class="text-muted"><?php echo userdate($r->timecompleted, '%d %b, %Y'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

function render_compliance_detail_view($courseid, $dept, $role_filter, $search='', $status_filter='') {
    global $DB, $OUTPUT;
    
    $where = "1=1";
    $params = [];
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
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
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($status_filter) {
        $where .= " AND uc.status = ?";
        $params[] = $status_filter;
    }
    
    $audit_data = $DB->get_records_sql("
        SELECT u.id, uc.compliance_percentage, uc.status, uc.completed_count, uc.total_mandatory, uc.timemodified,
               u.firstname, u.lastname, u.email, u.department, u.picture, u.imagealt,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
        FROM {user} u
        LEFT JOIN {local_aa_user_compliance} uc ON uc.userid = u.id
        WHERE $where
        ORDER BY uc.status ASC, u.lastname ASC
    ", $params, 0, 100);
    
    ?>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-800 mb-1">Compliance Audit Log <?php echo $search ? "- Results for '$search'" : ""; ?></h3>
                <p class="text-muted">Filtered results for Audit & Regulatory Reporting</p>
            </div>
            <a href="?view=compliance&dept=<?php echo urlencode($dept); ?>&course=<?php echo $courseid; ?>&role=<?php echo $role_filter; ?>" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="aa-card">
            <div class="table-responsive">
                <table class="aa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Learner</th>
                            <th>Department</th>
                            <th>Standing</th>
                            <th>Verification Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($audit_data as $row): 
                            $status_class = $row->status == 'compliant' ? 'success' : ($row->status == 'overdue' ? 'danger' : 'warning');
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3"><?php echo $OUTPUT->user_picture($row, ['size' => 40]); ?></div>
                                    <div>
                                        <div class="fw-bold"><?php echo fullname($row); ?></div>
                                        <div class="small text-muted"><?php echo $row->email; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo $row->department ?: 'Unassigned'; ?></span></td>
                            <td>
                                <span class="badge bg-<?php echo $status_class; ?> rounded-pill px-3 py-2 uppercase small shadow-sm">
                                    <i class="fas fa-<?php echo $row->status == 'compliant' ? 'check-circle' : ($row->status == 'overdue' ? 'exclamation-triangle' : 'clock'); ?> me-1"></i>
                                    <?php echo strtoupper($row->status); ?>
                                </span>
                            </td>
                            <td class="text-muted"><?php echo userdate($row->timemodified, '%d %b %Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($audit_data)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No search results match your criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function render_completions_report_view($courseid, $dept, $role_filter, $search='') {
    global $DB, $OUTPUT;
    
    $where = "u.deleted = 0 AND u.suspended = 0 AND cc.timecompleted IS NOT NULL";
    $params = [];
    
    if ($dept) {
        if ($dept === 'Unassigned') {
            $where .= " AND (u.department IS NULL OR u.department = '')";
        } else {
            $where .= " AND u.department = ?";
            $params[] = $dept;
        }
    }
    if ($courseid) {
        $where .= " AND cc.course = ?";
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

    $records = $DB->get_records_sql("
        SELECT cc.id as ccid, u.id, u.firstname, u.lastname, u.email, u.department, 
               u.picture, u.imagealt, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               c.fullname as coursename, cc.timecompleted
        FROM {course_completions} cc
        JOIN {user} u ON u.id = cc.userid
        JOIN {course} c ON c.id = cc.course
        WHERE $where
        ORDER BY cc.timecompleted DESC
        LIMIT 100
    ", $params);

    ?>
    <div class="aa-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-800 m-0"><i class="fas fa-check-circle text-success me-2"></i> Course Completions Log</h4>
            <span class="badge bg-primary rounded-pill"><?php echo count($records); ?> completions found</span>
        </div>
        <div class="table-responsive">
            <table class="aa-table">
                <thead><tr><th>#</th><th>User</th><th>Department</th><th>Course</th><th>Date Completed</th></tr></thead>
                <tbody>
                    <?php $i = 1; foreach($records as $r): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="me-2"><?php echo $OUTPUT->user_picture($r, ['size'=>35]); ?></div>
                                <div><div class="fw-bold"><?php echo fullname($r); ?></div><div class="small text-muted"><?php echo $r->email; ?></div></div>
                            </div>
                        </td>
                        <td><?php echo $r->department ?: 'Unassigned'; ?></td>
                        <td><?php echo htmlspecialchars($r->coursename); ?></td>
                        <td><?php echo userdate($r->timecompleted, '%d %b %Y'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($records)): ?><tr><td colspan="4" class="text-center py-5 text-muted">No course completions match your current filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function render_grade_report_view($courseid, $dept, $role_filter, $search='') {
    global $DB;
    $where = "gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL";
    $params = [];
    if($dept) { $where .= " AND u.department = ?"; $params[] = $dept; }
    if($courseid) { $where .= " AND gi.courseid = ?"; $params[] = $courseid; }
    if($search) {
        $where .= " AND (" . $DB->sql_like('u.firstname', '?', false) . " OR " . $DB->sql_like('u.lastname', '?', false) . " OR " . $DB->sql_like('u.email', '?', false) . ")";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }

    $grades = $DB->get_records_sql("
        SELECT gg.id, u.firstname, u.lastname, u.email, c.fullname as course, 
               CASE WHEN gg.finalgrade > 100 THEN 100 WHEN gg.finalgrade < 0 THEN 0 ELSE gg.finalgrade END as grade
        FROM {grade_grades} gg
        JOIN {user} u ON u.id = gg.userid
        JOIN {grade_items} gi ON gi.id = gg.itemid
        JOIN {course} c ON c.id = gi.courseid
        WHERE $where
        ORDER BY gg.timemodified DESC LIMIT 100
    ", $params);
    ?>
    <div class="aa-card"><h5>Grade Performance Report</h5><div class="table-responsive"><table class="aa-table"><thead><tr><th>User</th><th>Course</th><th>Grade</th></tr></thead><tbody><?php foreach($grades as $g): ?><tr><td><?php echo fullname($g); ?></td><td><?php echo htmlspecialchars(substr($g->course,0,40)); ?></td><td class="fw-bold text-<?php echo ($g->grade >= 70 ? 'success' : ($g->grade >= 50 ? 'warning' : 'danger')); ?>"><?php echo round($g->grade, 1); ?>%</td></tr><?php endforeach; ?></tbody></table></div></div>
    <?php
}

function render_compliance_overdue_view($dept='', $search='', $role_filter=0) {
    global $DB, $OUTPUT;
    
    $where = "uc.status = 'overdue'";
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
    
    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.department, uc.status, uc.expired_count,
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt
            FROM {user} u
            JOIN {local_aa_user_compliance} uc ON uc.userid = u.id
            WHERE $where
            ORDER BY uc.expired_count DESC, u.lastname ASC";
            
    $records = $DB->get_records_sql($sql, $params);
    
    ?>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-800 text-danger mb-0"><i class="fas fa-clock me-2"></i> Overdue Compliance Report</h3>
                <p class="text-muted">Users who have failed to complete mandatory training requirements within the deadline.</p>
            </div>
            <a href="?view=compliance&dept=<?php echo urlencode($dept); ?>&role=<?php echo $role_filter; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Monitor
            </a>
        </div>
        
        <div class="aa-card">
            <div class="table-responsive">
                <table class="aa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Expired Items</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($records as $r): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3"><?php echo $OUTPUT->user_picture($r, ['size' => 35]); ?></div>
                                    <div class="fw-bold"><?php echo fullname($r); ?></div>
                                </div>
                            </td>
                            <td><?php echo $r->email; ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo $r->department ?: 'Unassigned'; ?></span></td>
                            <td><span class="badge bg-danger rounded-pill px-3 py-1 uppercase small">OVERDUE</span></td>
                            <td class="text-center"><span class="fw-800 text-danger"><?php echo $r->expired_count; ?></span></td>
                            <td>
                                <a href="?userid=<?php echo $r->id; ?>&view=compliance_overdue" class="btn btn-sm btn-outline-primary">View Profile</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No overdue users found matching your criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
?>

<script>
/**
 * AJAX Navigation & Filtering for Advanced Analytics
 */
document.addEventListener('DOMContentLoaded', function() {
    const dashboardContent = document.getElementById('dashboard-content');
    const filterForm = document.getElementById('filterForm');

    // Helper to initialize charts - simplified check
    function reinitCharts() {
        // Find any scripts in the newly loaded content and execute them
        const scripts = dashboardContent.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    // Intercept Form Submission (using delegation)
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'filterForm') {
            e.preventDefault();
            const formData = new FormData(e.target);
            const params = new URLSearchParams(formData);
            const url = e.target.getAttribute('action') || window.location.pathname;
            loadPage(url + '?' + params.toString());
        }
    });

    async function loadPage(url, pushState = true) {
        dashboardContent.style.opacity = '0.5';
        dashboardContent.style.pointerEvents = 'none';

        try {
            const response = await fetch(url);
            const html = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // 1. Update Dashboard Content
            const newDashboardContent = doc.getElementById('dashboard-content');
            if (newDashboardContent) {
                dashboardContent.innerHTML = newDashboardContent.innerHTML;
            }

            // 2. Update Filter Bar
            const newFilterForm = doc.getElementById('filterForm');
            const oldFilterForm = document.getElementById('filterForm');
            if (newFilterForm && oldFilterForm) {
                oldFilterForm.innerHTML = newFilterForm.innerHTML;
            }

           if (pushState) {
                window.history.pushState({}, '', url);
            }

            // 4. Update Page Title
            document.title = doc.title;

            // 3. Update Sidebar Active State
            const urlObj = new URL(url, window.location.origin);
            const currentView = urlObj.searchParams.get('view') || 'executive';
            document.querySelectorAll('.aa-nav-item').forEach(item => {
                const itemUrl = new URL(item.href, window.location.origin);
                const itemView = itemUrl.searchParams.get('view') || 'executive';
                if (itemView === currentView) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Re-run any scripts contained in the new content
            reinitCharts();

        } catch (error) {
            console.error('Failed to load dashboard content:', error);
            window.location.href = url;
        } finally {
            dashboardContent.style.opacity = '1';
            dashboardContent.style.pointerEvents = 'auto';
        }
    }

    // Intercept Sidebar & Dashboard Links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;

        const url = new URL(link.href, window.location.origin);
        // Only intercept links within the advancedanalytics directory
        const isDashboardLink = url.origin === window.location.origin && 
                               url.pathname.includes('/local/advancedanalytics/');
        
        // Don't intercept external links, exports, or non-dashboard links
        if (isDashboardLink && !link.getAttribute('target') && !url.searchParams.has('export') && !url.pathname.includes('export.php')) {
            e.preventDefault();
            loadPage(link.href);
        }
    });

    // Handle Browser Back/Forward
    window.addEventListener('popstate', function() {
        loadPage(window.location.href, false);
    });
});
</script>

<?php
echo $OUTPUT->footer();
?>