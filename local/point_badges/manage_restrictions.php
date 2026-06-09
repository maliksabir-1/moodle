<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/point_badges/manage_restrictions.php'));
$PAGE->set_context($context);
$PAGE->set_title('Manage Premium & VIP Content');
$PAGE->set_heading('Manage Premium & VIP Content');

$action = optional_param('action', '', PARAM_ALPHA);
$cmid = optional_param('cmid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);

// Handle add/remove actions
if ($action == 'add' && $cmid && $type && confirm_sesskey()) {
    global $DB;
    $cm = $DB->get_record('course_modules', ['id' => $cmid]);
    if ($cm) {
        if ($type == 'premium') {
            // Remove VIP first — only one restriction type per activity.
            $DB->delete_records('local_pb_vip_restrictions', ['cmid' => $cmid]);
            \local_point_badges\restriction_manager::remove_vip($cmid);

            $now = time();
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->is_premium = 1;
            $record->courseid = $cm->course;
            $record->timemodified = $now;

            if ($existing = $DB->get_record('local_pb_premium_restrictions', ['cmid' => $cmid])) {
                $record->id = $existing->id;
                $DB->update_record('local_pb_premium_restrictions', $record);
            } else {
                $DB->insert_record('local_pb_premium_restrictions', $record);
            }

            \local_point_badges\restriction_manager::apply_premium($cmid);
            \core\notification::success('Activity marked as Premium!');
        } elseif ($type == 'vip') {
            // Remove Premium first — only one restriction type per activity.
            $DB->delete_records('local_pb_premium_restrictions', ['cmid' => $cmid]);
            \local_point_badges\restriction_manager::remove_premium($cmid);

            $now = time();
            $record = new stdClass();
            $record->cmid = $cmid;
            $record->is_vip_only = 1;
            $record->courseid = $cm->course;
            $record->early_access_days = 7;
            $record->timemodified = $now;

            if ($existing = $DB->get_record('local_pb_vip_restrictions', ['cmid' => $cmid])) {
                $record->id = $existing->id;
                $DB->update_record('local_pb_vip_restrictions', $record);
            } else {
                $DB->insert_record('local_pb_vip_restrictions', $record);
            }

            \local_point_badges\restriction_manager::apply_vip($cmid);
            \core\notification::success('Activity marked as VIP!');
        }
    }
    redirect($PAGE->url);
}

if ($action == 'remove' && $cmid && $type && confirm_sesskey()) {
    global $DB;
    if ($type == 'premium') {
        $DB->delete_records('local_pb_premium_restrictions', ['cmid' => $cmid]);
        \local_point_badges\restriction_manager::remove_premium($cmid);
        \core\notification::success('Premium restriction removed!');
    } elseif ($type == 'vip') {
        $DB->delete_records('local_pb_vip_restrictions', ['cmid' => $cmid]);
        \local_point_badges\restriction_manager::remove_vip($cmid);
        \core\notification::success('VIP restriction removed!');
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();

// Get all premium restrictions
$premium_sql = "SELECT pr.*, c.fullname as coursename, 
                       CASE 
                           WHEN q.name IS NOT NULL THEN CONCAT('Quiz: ', q.name)
                           WHEN a.name IS NOT NULL THEN CONCAT('Assignment: ', a.name)
                           WHEN f.name IS NOT NULL THEN CONCAT('Forum: ', f.name)
                           WHEN l.name IS NOT NULL THEN CONCAT('Lesson: ', l.name)
                           WHEN s.name IS NOT NULL THEN CONCAT('SCORM: ', s.name)
                           ELSE CONCAT('Activity ID: ', pr.cmid)
                       END as activity_name
                FROM {local_pb_premium_restrictions} pr
                JOIN {course_modules} cm ON cm.id = pr.cmid
                JOIN {course} c ON c.id = pr.courseid
                LEFT JOIN {quiz} q ON q.id = cm.instance
                LEFT JOIN {assign} a ON a.id = cm.instance
                LEFT JOIN {forum} f ON f.id = cm.instance
                LEFT JOIN {lesson} l ON l.id = cm.instance
                LEFT JOIN {scorm} s ON s.id = cm.instance
                ORDER BY c.fullname, pr.id";

$premium_restrictions = $DB->get_records_sql($premium_sql);

// Get all VIP restrictions
$vip_sql = "SELECT vr.*, c.fullname as coursename,
                   CASE 
                       WHEN q.name IS NOT NULL THEN CONCAT('Quiz: ', q.name)
                       WHEN a.name IS NOT NULL THEN CONCAT('Assignment: ', a.name)
                       WHEN f.name IS NOT NULL THEN CONCAT('Forum: ', f.name)
                       WHEN l.name IS NOT NULL THEN CONCAT('Lesson: ', l.name)
                       WHEN s.name IS NOT NULL THEN CONCAT('SCORM: ', s.name)
                       ELSE CONCAT('Activity ID: ', vr.cmid)
                   END as activity_name
            FROM {local_pb_vip_restrictions} vr
            JOIN {course_modules} cm ON cm.id = vr.cmid
            JOIN {course} c ON c.id = vr.courseid
            LEFT JOIN {quiz} q ON q.id = cm.instance
            LEFT JOIN {assign} a ON a.id = cm.instance
            LEFT JOIN {forum} f ON f.id = cm.instance
            LEFT JOIN {lesson} l ON l.id = cm.instance
            LEFT JOIN {scorm} s ON s.id = cm.instance
            ORDER BY c.fullname, vr.id";

$vip_restrictions = $DB->get_records_sql($vip_sql);

// Get all activities that can be restricted
$activities_sql = "SELECT cm.id, cm.course, c.fullname as coursename,
                          CASE 
                              WHEN q.name IS NOT NULL THEN CONCAT('📝 Quiz: ', q.name)
                              WHEN a.name IS NOT NULL THEN CONCAT('📄 Assignment: ', a.name)
                              WHEN f.name IS NOT NULL THEN CONCAT('💬 Forum: ', f.name)
                              WHEN l.name IS NOT NULL THEN CONCAT('📚 Lesson: ', l.name)
                              WHEN s.name IS NOT NULL THEN CONCAT('🎬 SCORM: ', s.name)
                              ELSE CONCAT('📌 Activity ID: ', cm.id)
                          END as activity_name,
                          CASE 
                              WHEN pr.id IS NOT NULL THEN 'premium'
                              WHEN vr.id IS NOT NULL THEN 'vip'
                              ELSE 'none'
                          END as current_restriction
                   FROM {course_modules} cm
                   JOIN {course} c ON c.id = cm.course
                   JOIN {modules} m ON m.id = cm.module
                   LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
                   LEFT JOIN {assign} a ON a.id = cm.instance AND m.name = 'assign'
                   LEFT JOIN {forum} f ON f.id = cm.instance AND m.name = 'forum'
                   LEFT JOIN {lesson} l ON l.id = cm.instance AND m.name = 'lesson'
                   LEFT JOIN {scorm} s ON s.id = cm.instance AND m.name = 'scorm'
                   LEFT JOIN {local_pb_premium_restrictions} pr ON pr.cmid = cm.id
                   LEFT JOIN {local_pb_vip_restrictions} vr ON vr.cmid = cm.id
                   WHERE cm.deletioninprogress = 0
                   AND c.visible = 1
                   AND (q.name IS NOT NULL OR a.name IS NOT NULL OR f.name IS NOT NULL OR l.name IS NOT NULL OR s.name IS NOT NULL)
                   ORDER BY c.fullname, cm.id";

$activities = $DB->get_records_sql($activities_sql);
?>

<style>
.restriction-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
}
.restriction-table th, .restriction-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.restriction-table th {
    background: #f5f5f5;
    font-weight: bold;
}
.badge-premium {
    background: #ff9800;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
}
.badge-vip {
    background: #9c27b0;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
}
.btn-remove {
    background: #f44336;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
}
.btn-add-premium {
    background: #ff9800;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
}
.btn-add-vip {
    background: #9c27b0;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
}
.section-title {
    margin-top: 30px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ddd;
}
</style>

<div class="manage-container">
    <h2>📋 Manage Premium & VIP Content</h2>
    <p>Mark course activities as Premium (requires unlock coupon) or VIP (requires special access).</p>

    <!-- Current Premium Restrictions -->
    <h3 class="section-title">⭐ Current Premium Content</h3>
    <?php if (!empty($premium_restrictions)): ?>
        <table class="restriction-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($premium_restrictions as $restriction): ?>
                    <tr>
                        <td><?php echo s($restriction->coursename); ?></td>
                        <td><?php echo s($restriction->activity_name); ?></td>
                        <td>
                            <a href="?action=remove&type=premium&cmid=<?php echo $restriction->cmid; ?>&sesskey=<?php echo sesskey(); ?>" 
                               class="btn-remove" 
                               onclick="return confirm('Remove premium restriction from this activity?')">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No premium content defined yet. Add some below!</p>
    <?php endif; ?>

    <!-- Current VIP Restrictions -->
    <h3 class="section-title">👑 Current VIP Content</h3>
    <?php if (!empty($vip_restrictions)): ?>
        <table class="restriction-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Activity</th>
                    <th>Early Access (days)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vip_restrictions as $restriction): ?>
                    <tr>
                        <td><?php echo s($restriction->coursename); ?></td>
                        <td><?php echo s($restriction->activity_name); ?></td>
                        <td><?php echo $restriction->early_access_days; ?></td>
                        <td>
                            <a href="?action=remove&type=vip&cmid=<?php echo $restriction->cmid; ?>&sesskey=<?php echo sesskey(); ?>" 
                               class="btn-remove" 
                               onclick="return confirm('Remove VIP restriction from this activity?')">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No VIP content defined yet. Add some below!</p>
    <?php endif; ?>

    <!-- Available Activities to Restrict -->
    <h3 class="section-title">📚 Available Activities</h3>
    <table class="restriction-table">
        <thead>
            <tr>
                <th>Course</th>
                <th>Activity</th>
                <th>Current Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?php echo s($activity->coursename); ?></td>
                    <td><?php echo s($activity->activity_name); ?></td>
                    <td>
                        <?php if ($activity->current_restriction == 'premium'): ?>
                            <span class="badge-premium">Premium</span>
                        <?php elseif ($activity->current_restriction == 'vip'): ?>
                            <span class="badge-vip">VIP</span>
                        <?php else: ?>
                            <span style="color: #999;">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($activity->current_restriction != 'premium'): ?>
                            <a href="?action=add&type=premium&cmid=<?php echo $activity->id; ?>&sesskey=<?php echo sesskey(); ?>" 
                               class="btn-add-premium">⭐ Mark as Premium</a>
                        <?php endif; ?>
                        <?php if ($activity->current_restriction != 'vip'): ?>
                            <a href="?action=add&type=vip&cmid=<?php echo $activity->id; ?>&sesskey=<?php echo sesskey(); ?>" 
                               class="btn-add-vip" style="margin-left: 5px;">👑 Mark as VIP</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
echo $OUTPUT->footer();
?>