<?php
// This file is part of the Point Badges System
// Professional Leaderboard with multiple ranking views

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();

// Get parameters
$courseid = optional_param('courseid', 0, PARAM_INT);
$type = optional_param('type', 'top', PARAM_ALPHA);
$department = optional_param('department', '', PARAM_TEXT);
$limit = optional_param('limit', 50, PARAM_INT);

// Set context
if ($courseid > 0) {
    $context = context_course::instance($courseid);
    $course = get_course($courseid);
    $PAGE->set_course($course);
} else {
    $context = context_system::instance();
}

$PAGE->set_url(new moodle_url('/local/point_badges/leaderboard.php', [
    'courseid' => $courseid, 
    'type' => $type,
    'department' => $department
]));
$PAGE->set_context($context);
$PAGE->set_title('Leaderboards - Point Badges');
$PAGE->set_heading('Point Badges Leaderboards');

echo $OUTPUT->header();

// Get user's level info using the manager class
$user_level_info = \local_point_badges\manager::get_user_level_info($USER->id);
$user_streak = \local_point_badges\manager::get_user_streak($USER->id);
$current_streak = $user_streak ? $user_streak->current_streak : 0;
$total_xp = $user_level_info['total_xp'];

// Use manager for level information
$correct_level = $user_level_info['current_level'];
$level_names = ['', 'Beginner', 'Intermediate', 'Advanced', 'Expert'];
$level_badge_colors = ['', '#cd7f32', '#c0c0c0', '#ffd700', '#e5e4e2'];
$level_icons = ['', '🥉', '🥈', '🥇', '💎'];
$correct_level_name = $level_names[$correct_level];

// Get next level details from manager
$next_level_info = \local_point_badges\manager::get_level_details($correct_level + 1);
$progress = $user_level_info['progress_percent'];
$xp_needed = $user_level_info['xp_needed_next_level'];
$next_level_name = isset($next_level_info['name']) ? $next_level_info['name'] : 'Max Level';

// Get all levels for badges tab
$all_levels = [];
for ($i = 1; $i <= 4; $i++) {
    $level_details = \local_point_badges\manager::get_level_details($i);
    $all_levels[] = (object)[
        'level_number' => $i,
        'level_name' => $level_names[$i],
        'min_xp' => $level_details['min_xp'],
        'max_xp' => $level_details['max_xp'],
        'badge_color' => $level_badge_colors[$i]
    ];
}
// Define level descriptions
$level_descriptions = [
    1 => "Start your learning journey by earning 100 XP!",
    2 => "Reach 300 XP to become an Intermediate learner!",
    3 => "Master advanced concepts with 700 XP!",
    4 => "Achieve expert status with 1500+ XP!",
];

// Get certificates
$certificates = \local_point_badges\certificate_manager::get_user_certificates($USER->id);

?>

<div class="lb-container">
    
    <!-- Hero Header -->
    <div class="lb-hero">
        <div class="lb-hero-content">
            <h1 class="lb-title">🏆 Leaderboards</h1>
            <p class="lb-subtitle">Track your progress, compete with peers, and climb to the top!</p>
        </div>
    </div>

    <!-- Stats Dashboard -->
    <div class="lb-stats-dashboard">
        <div class="lb-stat-card">
            <div class="lb-stat-icon">⭐</div>
            <div class="lb-stat-value"><?php echo number_format($total_xp); ?></div>
            <div class="lb-stat-label">Total XP</div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon">📊</div>
            <div class="lb-stat-value"><?php echo $correct_level_name; ?></div>
            <div class="lb-stat-label">Current Level</div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon">🔥</div>
            <div class="lb-stat-value"><?php echo $current_streak; ?></div>
            <div class="lb-stat-label">Day Streak</div>
        </div>
        <div class="lb-stat-card">
            <div class="lb-stat-icon">📈</div>
            <div class="lb-stat-value"><?php echo $progress; ?>%</div>
            <div class="lb-stat-label">To Next Level</div>
        </div>
    </div>

    <!-- Progress Bar (shown only in non-badges tabs) -->
    <?php if ($type != 'badges'): ?>
    <div class="lb-progress-section">
        <div class="lb-progress-label">
            <span>Progress to <?php echo $next_level_name; ?></span>
            <span><?php echo $progress; ?>%</span>
        </div>
        <div class="lb-progress-bar">
            <div class="lb-progress-fill" style="width: <?php echo $progress; ?>%;"></div>
        </div>
        <div class="lb-progress-hint">
            <?php echo $xp_needed; ?> XP needed to reach <?php echo $next_level_name; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="lb-tabs">
        <a href="?type=top&courseid=<?php echo $courseid; ?>" class="lb-tab <?php echo $type == 'top' ? 'active' : ''; ?>">
            <span class="tab-icon">🏅</span> Top Students
        </a>
        <a href="?type=weekly&courseid=<?php echo $courseid; ?>" class="lb-tab <?php echo $type == 'weekly' ? 'active' : ''; ?>">
            <span class="tab-icon">📅</span> Weekly Champions
        </a>
        <a href="?type=course&courseid=<?php echo $courseid; ?>" class="lb-tab <?php echo $type == 'course' ? 'active' : ''; ?>">
            <span class="tab-icon">📚</span> Course Ranking
        </a>
        <a href="?type=department&courseid=<?php echo $courseid; ?>" class="lb-tab <?php echo $type == 'department' ? 'active' : ''; ?>">
            <span class="tab-icon">🏢</span> Department Ranking
        </a>
        <a href="?type=badges&courseid=<?php echo $courseid; ?>" class="lb-tab <?php echo $type == 'badges' ? 'active' : ''; ?>">
            <span class="tab-icon">🎖️</span> Badges & Certificates
        </a>
    </div>

    <!-- Filters -->
    <?php if ($type == 'course'): ?>
    <div class="lb-filter-bar">
        <div class="lb-filter">
            <label>Select Course:</label>
            <form method="get" class="lb-filter-form">
                <input type="hidden" name="type" value="course">
                <select name="courseid" class="lb-select" onchange="this.form.submit()">
                    <option value="0">🌍 Global Leaderboard</option>
                    <?php
                    $courses = get_courses('all', 'c.fullname ASC', 'c.id,c.fullname');
                    foreach ($courses as $c) {
                        if ($c->id != SITEID) {
                            $selected = ($courseid == $c->id) ? 'selected' : '';
                            echo "<option value='{$c->id}' {$selected}>📖 " . format_string($c->fullname) . "</option>";
                        }
                    }
                    ?>
                </select>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($type == 'department'): ?>
    <div class="lb-filter-bar">
        <div class="lb-filter">
            <label>Select Department:</label>
            <form method="get" class="lb-filter-form">
                <input type="hidden" name="type" value="department">
                <select name="department" class="lb-select" onchange="this.form.submit()">
                    <option value="">🏢 All Departments</option>
                    <?php
                    $departments = get_departments_list();
                    foreach ($departments as $dept) {
                        $selected = ($department == $dept) ? 'selected' : '';
                        echo "<option value='{$dept}' {$selected}>🏛️ " . htmlspecialchars($dept) . "</option>";
                    }
                    ?>
                </select>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="lb-main-content">
        
        <?php if ($type == 'top'): ?>
            <?php display_top_leaderboard($courseid, $limit); ?>
        
        <?php elseif ($type == 'weekly'): ?>
            <?php display_weekly_leaderboard($courseid); ?>
        
        <?php elseif ($type == 'course'): ?>
            <?php display_course_leaderboard($courseid); ?>
        
        <?php elseif ($type == 'department'): ?>
            <?php display_department_leaderboard($department); ?>
        
        <?php elseif ($type == 'badges'): ?>
            <?php display_badges_tab($correct_level, $all_levels, $level_names, $level_icons, $level_badge_colors, $level_descriptions, $user_streak, $total_xp, $certificates); ?>
        <?php endif; ?>
        
    </div>

    <!-- Your Position Card (only shown for non-badges tabs) -->
    <?php if ($type != 'badges'): ?>
    <div class="lb-your-rank">
        <h3 class="lb-section-title">📌 Your Position</h3>
        <?php display_user_rank_card($USER->id, $courseid, $type, $department); ?>
    </div>
    <?php endif; ?>

</div>

<style>
/* [Keep all existing CSS styles here - they remain unchanged] */
.lb-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
}
.lb-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 30px;
    text-align: center;
    color: white;
}
.lb-title {
    font-size: 2.8rem;
    margin: 0 0 10px 0;
    font-weight: 700;
}
.lb-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}
.lb-stats-dashboard {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}
.lb-stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}
.lb-stat-card:hover {
    transform: translateY(-5px);
}
.lb-stat-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}
.lb-stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    color: #333;
}
.lb-stat-label {
    font-size: 0.85rem;
    color: #666;
    margin-top: 5px;
}
.lb-progress-section {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
}
.lb-progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.9rem;
    color: #555;
}
.lb-progress-bar {
    background: #e0e0e0;
    border-radius: 10px;
    height: 12px;
    overflow: hidden;
}
.lb-progress-fill {
    background: linear-gradient(90deg, #667eea, #764ba2);
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}
.lb-progress-hint {
    margin-top: 10px;
    font-size: 0.8rem;
    color: #888;
    text-align: right;
}
.lb-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 30px;
    flex-wrap: wrap;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0;
}
.lb-tab {
    padding: 12px 28px;
    text-decoration: none;
    color: #666;
    border-radius: 12px 12px 0 0;
    transition: all 0.3s ease;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.lb-tab:hover {
    background: #f5f5f5;
    color: #667eea;
}
.lb-tab.active {
    background: #667eea;
    color: white;
}
.tab-icon {
    font-size: 1.1rem;
}
.lb-filter-bar {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 15px 20px;
    margin-bottom: 25px;
}
.lb-filter {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.lb-filter label {
    font-weight: 500;
    color: #555;
}
.lb-select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 14px;
    min-width: 250px;
    background: white;
    cursor: pointer;
}
.lb-main-content {
    margin-bottom: 30px;
}
.lb-table-wrapper {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
.lb-table {
    width: 100%;
    border-collapse: collapse;
}
.lb-table th {
    text-align: left;
    padding: 16px 20px;
    background: #f8f9fa;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #e0e0e0;
}
.lb-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.lb-table tr:hover {
    background: #fafafa;
}
.lb-table .current-user {
    background: #fff8e1;
    border-left: 4px solid #ffd700;
}
.rank-cell {
    width: 70px;
    text-align: center;
    font-weight: bold;
    font-size: 1.1rem;
}
.badge-cell {
    width: 120px;
    text-align: center;
}
.badge-display {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.user-info {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-weight: 600;
    color: #333;
}
.user-email {
    font-size: 0.7rem;
    color: #999;
}
.xp-value {
    font-weight: 700;
    color: #667eea;
}
.weekly-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 30px;
    text-align: center;
    border-radius: 20px;
    margin-bottom: 25px;
}
.weekly-period {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 8px;
}
.rank-1-row {
    background: linear-gradient(90deg, #fff8e1 0%, #ffffff 100%);
    border-left: 4px solid #ffd700;
}
.rank-2-row {
    background: linear-gradient(90deg, #f5f5f5 0%, #ffffff 100%);
    border-left: 4px solid #c0c0c0;
}
.rank-3-row {
    background: linear-gradient(90deg, #fde9e0 0%, #ffffff 100%);
    border-left: 4px solid #cd7f32;
}
.weekly-xp-cell .xp-gain {
    color: #4caf50;
    font-weight: bold;
    font-size: 1.1rem;
}
.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.dept-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}
.dept-card:hover {
    transform: translateY(-3px);
}
.dept-rank {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 10px;
}
.dept-name {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}
.dept-stats {
    margin-bottom: 15px;
}
.dept-stat {
    padding: 5px 0;
    color: #666;
    font-size: 0.85rem;
}
.btn-view {
    display: inline-block;
    padding: 8px 16px;
    background: #667eea;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.85rem;
    transition: background 0.3s ease;
}
.btn-view:hover {
    background: #5a67d8;
    color: white;
}
.lb-your-rank {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-top: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.lb-section-title {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}
.your-rank-display {
    display: flex;
    align-items: baseline;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}
.your-rank-number {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
}
.your-rank-text {
    color: #666;
    font-size: 0.9rem;
}
.your-rank-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}
.your-rank-stat {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.your-rank-stat span {
    color: #666;
}
.your-rank-stat strong {
    color: #333;
}

/* Badges Tab Specific Styles */
.badges-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.badges-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}
.badges-header h2 {
    margin: 0 0 10px 0;
    color: #333;
}
.badges-header p {
    color: #666;
    margin: 0;
}
.level-badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.level-badge-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid #e0e0e0;
    position: relative;
    overflow: hidden;
}
.level-badge-card.earned {
    border-color: #4caf50;
    background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
}
.level-badge-card.locked {
    opacity: 0.7;
    filter: grayscale(0.2);
}
.level-badge-icon {
    font-size: 3rem;
    margin-bottom: 10px;
}
.level-badge-name {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 5px;
}
.level-badge-xp {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 10px;
}
.level-badge-desc {
    font-size: 0.8rem;
    color: #888;
    margin-bottom: 15px;
    min-height: 40px;
}
.level-badge-status {
    margin-top: 10px;
}
.earned-badge {
    color: #4caf50;
    font-weight: bold;
    font-size: 0.85rem;
}
.locked-badge-status {
    color: #999;
    font-size: 0.8rem;
}
.current-badge-status {
    color: #ff9800;
    font-weight: bold;
    font-size: 0.85rem;
}
.streak-badges {
    margin-top: 20px;
}
.certificates-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}
.certificates-section h3 {
    margin-bottom: 20px;
    color: #333;
}
.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}
.certificate-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}
.certificate-card:hover {
    transform: translateX(5px);
    background: #e8f5e9;
}
.certificate-icon {
    font-size: 2rem;
}
.certificate-info {
    flex: 1;
}
.certificate-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}
.certificate-date, .certificate-code {
    font-size: 0.75rem;
    color: #666;
}
.no-certificates {
    text-align: center;
    padding: 40px;
    color: #999;
}

.btn-download {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 15px;
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    color: white;
    text-decoration: none;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-download:hover {
    background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
}

@media (max-width: 768px) {
    .lb-stats-dashboard { grid-template-columns: repeat(2, 1fr); }
    .lb-title { font-size: 1.8rem; }
    .lb-tab { padding: 8px 16px; font-size: 0.85rem; }
    .your-rank-stats { grid-template-columns: 1fr; }
    .badge-cell { width: 100px; }
    .level-badges-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .lb-stats-dashboard { grid-template-columns: 1fr; }
    .rank-cell { width: 50px; }
    .badge-cell { width: 80px; }
    .badge-display { font-size: 0.65rem; padding: 2px 8px; }
}
</style>

<?php
echo $OUTPUT->footer();

// ========== HELPER FUNCTIONS ==========

/**
 * Get badge display (icon + name in one line)
 */
function get_badge_display($level) {
    $badges = [
        1 => ['icon' => '🥉', 'name' => 'Beginner', 'color' => '#cd7f32'],
        2 => ['icon' => '🥈', 'name' => 'Intermediate', 'color' => '#c0c0c0'],
        3 => ['icon' => '🥇', 'name' => 'Advanced', 'color' => '#ffd700'],
        4 => ['icon' => '💎', 'name' => 'Expert', 'color' => '#e5e4e2'],
    ];
    $b = $badges[$level] ?? $badges[1];
    $text_color = ($level == 2 || $level == 3 || $level == 4) ? '#333' : 'white';
    return '<span class="badge-display" style="background-color: ' . $b['color'] . '; color: ' . $text_color . ';">' . $b['icon'] . ' ' . $b['name'] . '</span>';
}

function get_complete_user($userrecord) {
    global $DB;
    if (isset($userrecord->firstnamephonetic)) {
        return $userrecord;
    }
    return $DB->get_record('user', ['id' => $userrecord->id]);
}

/**
 * Get departments list for dropdown
 */
function get_departments_list() {
    global $DB;
    return $DB->get_fieldset_sql("SELECT DISTINCT department FROM {user} WHERE department IS NOT NULL AND department != '' AND deleted = 0");
}

// ========== DISPLAY FUNCTIONS ==========

/**
 * Display Top Students Leaderboard
 */
function display_top_leaderboard($courseid, $limit) {
    global $OUTPUT, $USER;
    
    $leaderboard = \local_point_badges\manager::get_leaderboard($courseid ?: null, $limit ?: 100);
    
    if (empty($leaderboard)) {
        echo '<div class="lb-empty">No data available yet. Complete activities to earn XP!</div>';
        return;
    }
    
    echo '<div class="lb-table-wrapper">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Badge</th>
                        <th>Total XP</th>
                        <th>Streak</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($leaderboard as $user) {
        $is_current = ($user->id == $USER->id);
        $complete_user = get_complete_user($user);
        $streak = \local_point_badges\manager::get_user_streak($user->id);
        
        echo '<tr class="' . ($is_current ? 'current-user' : '') . '">
                <td class="rank-cell">' . $user->rank . '</td>
                <td class="user-cell">
                    <div class="user-avatar">' . $OUTPUT->user_picture($complete_user, ['size' => 40, 'link' => false]) . '</div>
                    <div class="user-info">
                        <div class="user-name">' . $user->fullname . '</div>
                        <div class="user-email">' . s($user->email) . '</div>
                    </div>
                 </td>
                <td class="badge-cell">' . get_badge_display($user->current_level) . '</td>
                <td class="xp-value"><strong>' . number_format($user->total_xp) . '</strong> XP</td>
                <td class="streak-value">🔥 ' . ($streak ? $streak->current_streak : 0) . ' days</td>
                </tr>';
    }
    echo '</tbody> 
            </table>
          </div>';
}

/**
 * Display Weekly Champions Leaderboard
 */
function display_weekly_leaderboard($courseid) {
    global $OUTPUT, $DB, $USER;
    
    $week_start = strtotime('monday this week');
    $week_end = strtotime('sunday this week');
    
    $start_date = date('F j', $week_start);
    $end_date = date('F j, Y', $week_end);
    
    $params = ['week_start' => $week_start];
    
    $course_filter_l = "";
    $course_filter_ux = "";
    if (!empty($courseid)) {
        $course_filter_l = "AND courseid = :courseid";
        $course_filter_ux = "WHERE courseid = :courseid";
        $params['courseid'] = $courseid;
    }

    $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename, u.email, u.picture, u.imagealt,
                   l.weekly_xp,
                   COALESCE(ux.total_xp, 0) as total_xp
            FROM {user} u
            INNER JOIN (
                SELECT userid, SUM(xp_amount) as weekly_xp
                FROM {local_pb_xp_log}
                WHERE timecreated >= :week_start AND xp_amount > 0 $course_filter_l
                GROUP BY userid
            ) l ON l.userid = u.id
            LEFT JOIN (
                SELECT userid, SUM(total_xp) as total_xp
                FROM {local_pb_user_xp}
                $course_filter_ux
                GROUP BY userid
            ) ux ON ux.userid = u.id
            WHERE u.deleted = 0
            ORDER BY l.weekly_xp DESC, ux.total_xp DESC
            LIMIT 50";
    
    $champions = $DB->get_records_sql($sql, $params);
    
    if (empty($champions)) {
        echo '<div class="lb-empty">No weekly data available yet. Complete activities this week to become a champion!</div>';
        return;
    }
    
    $champions_array = array_values($champions);
    
    echo '<div class="weekly-header">
            <div class="weekly-period">🏆 WEEKLY CHAMPIONS 🏆</div>
            <div>' . $start_date . ' - ' . $end_date . '</div>
          </div>
          <div class="lb-table-wrapper">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Badge</th>
                        <th>Weekly XP</th>
                        <th>Total XP</th>
                    </tr>
                </thead>
                <tbody>';
    
    $rank = 1;
    foreach ($champions_array as $user) {
        $is_current_user = ($user->id == $USER->id);
        $complete_user = $DB->get_record('user', ['id' => $user->id]);
        if (!$complete_user) $complete_user = $user;
        $user_picture = $OUTPUT->user_picture($complete_user, ['size' => 40, 'link' => false]);
        
        $row_class = '';
        if ($rank == 1) $row_class = 'rank-1-row';
        if ($rank == 2) $row_class = 'rank-2-row';
        if ($rank == 3) $row_class = 'rank-3-row';
        if ($is_current_user) $row_class .= ' current-user';
        
        // Dynamically calculate level to avoid MAX(level) aggregate split-XP bug
        $global_xp = $DB->get_field_sql("SELECT SUM(total_xp) FROM {local_pb_user_xp} WHERE userid = ?", [$user->id]) ?: 0;
        $user->current_level = \local_point_badges\manager::calculate_level($global_xp);
        
        echo '<tr class="' . $row_class . '">
                <td class="rank-cell">' . $rank . '</td>
                <td class="user-cell">
                    <div class="user-avatar">' . $user_picture . '</div>
                    <div class="user-info">
                        <div class="user-name">' . fullname($complete_user) . '</div>
                        <div class="user-email">' . s($complete_user->email) . '</div>
                    </div>
                 </td>
                <td class="badge-cell">' . get_badge_display($user->current_level) . '</td>
                <td class="weekly-xp-cell"><span class="xp-gain">+' . number_format($user->weekly_xp) . '</span> XP</td>
                <td class="total-xp-cell">' . number_format($user->total_xp) . ' XP</td>
                </tr>';
        $rank++;
    }
    echo '</tbody> 
            </table>
          </div>';
}

/**
 * Display Course Ranking Leaderboard
 */
function display_course_leaderboard($courseid) {
    global $DB, $OUTPUT, $USER;
    
    $leaderboard = \local_point_badges\manager::get_leaderboard($courseid ?: null, 100);
    
    if (empty($leaderboard)) {
        echo '<div class="lb-empty">No course data available yet.</div>';
        return;
    }
    
    echo '<div class="lb-table-wrapper">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Badge</th>
                        <th>Course XP</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($leaderboard as $user) {
        $is_current = ($user->id == $USER->id);
        $complete_user = get_complete_user($user);
        
        $level_info = \local_point_badges\manager::get_level_details($user->current_level);
        $progress = 0;
        $next_level = \local_point_badges\manager::get_level_details($user->current_level + 1);
        if ($next_level && isset($next_level['min_xp']) && $next_level['min_xp'] > 0) {
            $xp_for_current = $level_info['min_xp'];
            $xp_for_next = $next_level['min_xp'];
            
            // To prevent progress bar blowing past 100% due to course-specific vs global total differences
            $global_xp = $DB->get_field_sql("SELECT SUM(total_xp) FROM {local_pb_user_xp} WHERE userid = ?", [$user->id]) ?: 0;
            
            // Re-assign level to ensure it displays their true current level badge
            $user->current_level = \local_point_badges\manager::calculate_level($global_xp);
            $level_info = \local_point_badges\manager::get_level_details($user->current_level);
            $next_level = \local_point_badges\manager::get_level_details($user->current_level + 1);
            
            $xp_for_current = $level_info['min_xp'];
            $xp_for_next = isset($next_level['min_xp']) ? $next_level['min_xp'] : $global_xp;
            
            $xp_earned = max(0, $global_xp - $xp_for_current);
            $xp_needed_total = max(1, $xp_for_next - $xp_for_current);
            $progress = min(100, round(($xp_earned / $xp_needed_total) * 100));
        } else {
            $progress = 100;
        }
        
        echo '<tr class="' . ($is_current ? 'current-user' : '') . '">
                <td class="rank-cell">' . $user->rank . '</td>
                <td class="user-cell">
                    <div class="user-avatar">' . $OUTPUT->user_picture($complete_user, ['size' => 35, 'link' => false]) . '</div>
                    <div class="user-name">' . $user->fullname . '</div>
                 </td>
                <td class="badge-cell">' . get_badge_display($user->current_level) . '</td>
                <td class="xp-value">' . number_format($user->total_xp) . ' XP</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex:1; background:#e0e0e0; border-radius:5px; height:6px;">
                            <div style="width:' . $progress . '%; background:#4caf50; height:6px; border-radius:5px;"></div>
                        </div>
                        <span style="font-size:0.75rem;">' . $progress . '%</span>
                    </div>
                 </span>
               </tr>';
    }
    echo '</tbody> 
            </table>
          </div>';
}

/**
 * Display Department Ranking Leaderboard
 */
function display_department_leaderboard($department) {
    global $DB, $OUTPUT;
    
    if ($department) {
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       COALESCE(SUM(ux.total_xp), 0) as total_xp
                FROM {user} u
                LEFT JOIN {local_pb_user_xp} ux ON ux.userid = u.id
                WHERE u.deleted = 0 AND u.department = :department
                GROUP BY u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                         u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                ORDER BY total_xp DESC LIMIT 50";
        
        $users = $DB->get_records_sql($sql, ['department' => $department]);
        $rank = 1;
        
        if (empty($users)) {
            echo '<div class="lb-empty">No users found in this department.</div>';
            return;
        }
        
        echo '<div class="lb-table-wrapper">
                <table class="lb-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User</th>
                            <th>Badge</th>
                            <th>Total XP</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($users as $user) {
            $complete_user = get_complete_user($user);
            
            // Reassign global level mathematically
            $user->current_level = \local_point_badges\manager::calculate_level($user->total_xp);
            
            echo '<tr>
                    <td class="rank-cell">' . $rank . '</td>
                    <td class="user-cell">
                        <div class="user-avatar">' . $OUTPUT->user_picture($complete_user, ['size' => 35, 'link' => false]) . '</div>
                        <div class="user-name">' . fullname($complete_user) . '</div>
                     </td>
                    <td class="badge-cell">' . get_badge_display($user->current_level) . '</td>
                    <td class="xp-value">' . number_format($user->total_xp) . ' XP</td>
                   </tr>';
            $rank++;
        }
        echo '</tbody> 
            </table>
          </div>';
    } else {
        $sql = "SELECT department, COUNT(DISTINCT u.id) as user_count, COALESCE(SUM(ux.total_xp), 0) as total_xp
                FROM {user} u
                LEFT JOIN {local_pb_user_xp} ux ON ux.userid = u.id
                WHERE department IS NOT NULL AND department != '' AND u.deleted = 0
                GROUP BY department ORDER BY total_xp DESC";
        $departments = $DB->get_records_sql($sql);
        
        if (empty($departments)) {
            echo '<div class="lb-empty">No departments found.</div>';
            return;
        }
        
        echo '<div class="departments-grid">';
        $rank = 1;
        foreach ($departments as $dept) {
            echo '<div class="dept-card">
                    <div class="dept-rank">#' . $rank . '</div>
                    <div class="dept-name">🏛️ ' . s($dept->department) . '</div>
                    <div class="dept-stats">
                        <div class="dept-stat">👥 Students: ' . $dept->user_count . '</div>
                        <div class="dept-stat">⭐ Total XP: ' . number_format($dept->total_xp) . '</div>
                    </div>
                    <a href="?type=department&department=' . urlencode($dept->department) . '" class="btn-view">View Members →</a>
                  </div>';
            $rank++;
        }
        echo '</div>';
    }
}

/**
 * Display Badges Tab - All badges in one place
 */
function display_badges_tab($current_level, $all_levels, $level_names, $level_icons, $level_colors, $level_descriptions, $user_streak, $total_xp, $certificates) {
    global $USER;
    
    $current_streak = $user_streak ? $user_streak->current_streak : 0;
    $max_streak = $user_streak ? $user_streak->max_streak : 0;
    
    echo '<div class="badges-container">
            <div class="badges-header">
                <h2>🎖️ Your Badges & Certificates</h2>
                <p>Track your achievements and unlock new badges as you progress!</p>
            </div>';
    
    // Level Badges Section
    echo '<h3>📊 Level Badges</h3>
          <div class="level-badges-grid">';
    
    foreach ($all_levels as $level) {
        $level_num = is_object($level) ? $level->level_number : $level['level_number'];
        $level_name = is_object($level) ? $level->level_name : $level['level_name'];
        $min_xp = is_object($level) ? $level->min_xp : $level['min_xp'];
        $max_xp = is_object($level) ? $level->max_xp : $level['max_xp'];
        $badge_color = is_object($level) ? $level->badge_color : $level['badge_color'];
        
        $is_earned = ($current_level >= $level_num);
        $is_current = ($current_level == $level_num);
        $icon = $level_icons[$level_num] ?? '🏅';
        $description = "Complete requirements to unlock this badge.";
        
        // Custom descriptions for each level
        if ($level_num == 1) $description = "Start your learning journey by earning 100 XP!";
        if ($level_num == 2) $description = "Reach 300 XP to become an Intermediate learner!";
        if ($level_num == 3) $description = "Master advanced concepts with 700 XP!";
        if ($level_num == 4) $description = "Achieve expert status with 1500+ XP!";
        
        $card_class = 'level-badge-card';
        if ($is_earned) $card_class .= ' earned';
        if (!$is_earned) $card_class .= ' locked';
        
        echo '<div class="' . $card_class . '">
                <div class="level-badge-icon">' . $icon . '</div>
                <div class="level-badge-name" style="color: ' . $badge_color . ';">' . $level_name . '</div>
                <div class="level-badge-xp">' . $min_xp . ' - ' . $max_xp . ' XP</div>
                <div class="level-badge-desc">' . $description . '</div>
                <div class="level-badge-status">';
        
        if ($is_current) {
            echo '<span class="current-badge-status">⭐ CURRENT LEVEL</span>';
        } elseif ($is_earned) {
            echo '<span class="earned-badge">✓ UNLOCKED</span>';
        } else {
            $xp_needed = $min_xp - $total_xp;
            echo '<span class="locked-badge-status">🔒 ' . max(0, $xp_needed) . ' XP NEEDED</span>';
        }
        
        echo '    </div>
              </div>';
    }
    
    echo '</div>';
    
    // Streak Badges Section
    echo '<div class="streak-badges">
            <h3>🔥 Streak Badges</h3>
            <div class="level-badges-grid">
                <div class="level-badge-card ' . ($current_streak >= 7 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">🌱</div>
                    <div class="level-badge-name">7-Day Learner</div>
                    <div class="level-badge-xp">Log in for 7 consecutive days</div>
                    <div class="level-badge-status">' . ($current_streak >= 7 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 7 - $current_streak) . ' DAYS NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($current_streak >= 30 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">🌟</div>
                    <div class="level-badge-name">30-Day Champion</div>
                    <div class="level-badge-xp">Log in for 30 consecutive days</div>
                    <div class="level-badge-status">' . ($current_streak >= 30 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 30 - $current_streak) . ' DAYS NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($current_streak >= 100 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">🏆</div>
                    <div class="level-badge-name">100-Day Legend</div>
                    <div class="level-badge-xp">Log in for 100 consecutive days</div>
                    <div class="level-badge-status">' . ($current_streak >= 100 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 100 - $current_streak) . ' DAYS NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($max_streak >= 365 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">💪</div>
                    <div class="level-badge-name">Year of Learning</div>
                    <div class="level-badge-xp">Log in for 365 consecutive days</div>
                    <div class="level-badge-status">' . ($max_streak >= 365 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 BEST STREAK: ' . $max_streak . ' DAYS</span>') . '</div>
                </div>
            </div>
          </div>';
    
    // XP Milestone Badges
    echo '<div class="streak-badges">
            <h3>⭐ XP Milestone Badges</h3>
            <div class="level-badges-grid">
                <div class="level-badge-card ' . ($total_xp >= 100 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">🎯</div>
                    <div class="level-badge-name">Rookie</div>
                    <div class="level-badge-xp">Reach 100 XP</div>
                    <div class="level-badge-status">' . ($total_xp >= 100 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 100 - $total_xp) . ' XP NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($total_xp >= 500 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">⭐</div>
                    <div class="level-badge-name">Rising Star</div>
                    <div class="level-badge-xp">Reach 500 XP</div>
                    <div class="level-badge-status">' . ($total_xp >= 500 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 500 - $total_xp) . ' XP NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($total_xp >= 1000 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">🏅</div>
                    <div class="level-badge-name">Elite Learner</div>
                    <div class="level-badge-xp">Reach 1000 XP</div>
                    <div class="level-badge-status">' . ($total_xp >= 1000 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 1000 - $total_xp) . ' XP NEEDED</span>') . '</div>
                </div>
                <div class="level-badge-card ' . ($total_xp >= 5000 ? 'earned' : 'locked') . '">
                    <div class="level-badge-icon">👑</div>
                    <div class="level-badge-name">Master Learner</div>
                    <div class="level-badge-xp">Reach 5000 XP</div>
                    <div class="level-badge-status">' . ($total_xp >= 5000 ? '<span class="earned-badge">✓ UNLOCKED</span>' : '<span class="locked-badge-status">🔒 ' . max(0, 5000 - $total_xp) . ' XP NEEDED</span>') . '</div>
                </div>
            </div>
          </div>';
    
    // Certificates Section - FINAL VERSION
echo '<div class="certificates-section">
        <h3>📜 Your Certificates</h3>';

$certificates = \local_point_badges\certificate_manager::get_user_certificates($USER->id);

if (!empty($certificates)) {
    echo '<div class="certificates-grid">';
    foreach ($certificates as $cert) {
        $viewurl = \local_point_badges\certificate_manager::get_view_url($cert);
        $viewurl_str = $viewurl ? $viewurl->out() : '';
        echo '<div class="certificate-card">
            <div class="certificate-icon">📄</div>
            <div class="certificate-info">
                <div class="certificate-name">' . s($cert->certificate_name) . '</div>
                <div class="certificate-date">Issued: ' . userdate($cert->issued_date, get_string('strftimedate')) . '</div>
                <div class="certificate-code">Code: ' . $cert->certificate_code . '</div>';
        if ($viewurl_str) {
            echo '<a href="' . $viewurl->out(false) . '" class="btn-download">📥 ' .
                get_string('viewcertificate', 'local_point_badges') . '</a>';
        }
        echo '    </div>
              </div>';
    }
    echo '</div>';
} else {
    echo '<div class="no-certificates">
            <p>No certificates earned yet. Complete levels or purchase from the <a href="' . new \moodle_url('/local/point_badges/shop.php') . '">Reward Shop</a>!</p>
          </div>';
}

echo '</div>';
?>

<?php
}

/**
 * Display User Rank Card
 */
function display_user_rank_card($userid, $courseid, $type, $department) {
    global $DB;
    
    $user_level = \local_point_badges\manager::get_user_level_info($userid);
    $user_streak = \local_point_badges\manager::get_user_streak($userid);
    $total_xp = $user_level['total_xp'];
    
    if ($total_xp >= 0 && $total_xp <= 100) $current_level_num = 1;
    elseif ($total_xp >= 101 && $total_xp <= 300) $current_level_num = 2;
    elseif ($total_xp >= 301 && $total_xp <= 700) $current_level_num = 3;
    else $current_level_num = 4;
    
    $level_names = ['', 'Beginner', 'Intermediate', 'Advanced', 'Expert'];
    $current_level_name = $level_names[$current_level_num];
    
    $rank_display = 'Not Ranked';
    $rank_number = null;
    
    if ($type == 'top') {
        $leaderboard = \local_point_badges\manager::get_leaderboard(null, 1000);
        $rank = 1;
        foreach ($leaderboard as $user) {
            if ($user->id == $userid) {
                $rank_number = $rank;
                $rank_display = '<span class="your-rank-number">#' . $rank . '</span> <span class="your-rank-text">overall</span>';
                break;
            }
            $rank++;
        }
    } elseif ($type == 'course') {
        $leaderboard = \local_point_badges\manager::get_leaderboard($courseid ?: null, 1000);
        $rank = 1;
        foreach ($leaderboard as $user) {
            if ($user->id == $userid) {
                $rank_number = $rank;
                $rank_display = '<span class="your-rank-number">#' . $rank . '</span> <span class="your-rank-text">in ' . ($courseid ? 'this course' : 'all courses') . '</span>';
                break;
            }
            $rank++;
        }
    } elseif ($type == 'weekly') {
        $week_start = strtotime('monday this week');
        $sql = "SELECT u.id, COALESCE(SUM(l.xp_amount), 0) as weekly_xp
                FROM {user} u
                LEFT JOIN {local_pb_xp_log} l ON l.userid = u.id AND l.timecreated >= :week_start AND l.xp_amount > 0
                WHERE u.deleted = 0
                GROUP BY u.id
                HAVING COALESCE(SUM(l.xp_amount), 0) > 0
                ORDER BY weekly_xp DESC";
        
        $weekly_users = $DB->get_records_sql($sql, ['week_start' => $week_start]);
        $rank = 1;
        $found = false;
        foreach ($weekly_users as $user) {
            if ($user->id == $userid) {
                $rank_number = $rank;
                $rank_display = '<span class="your-rank-number">#' . $rank . '</span> <span class="your-rank-text">this week</span>';
                $found = true;
                break;
            }
            $rank++;
        }
        if (!$found) {
            $rank_display = '<span class="your-rank-number">Not Ranked</span> <span class="your-rank-text">earn XP this week</span>';
        }
    } elseif ($type == 'department') {
        if ($department) {
            $sql = "SELECT u.id, COALESCE(SUM(ux.total_xp), 0) as total_xp
                    FROM {user} u
                    LEFT JOIN {local_pb_user_xp} ux ON ux.userid = u.id
                    WHERE u.deleted = 0 AND u.department = :department
                    GROUP BY u.id
                    ORDER BY total_xp DESC";
            $dept_users = $DB->get_records_sql($sql, ['department' => $department]);
            $rank = 1;
            foreach ($dept_users as $user) {
                if ($user->id == $userid) {
                    $rank_number = $rank;
                    $rank_display = '<span class="your-rank-number">#' . $rank . '</span> <span class="your-rank-text">in department</span>';
                    break;
                }
                $rank++;
            }
        }
    }
    
    if ($rank_number == 1) {
        $rank_display = '<span class="your-rank-number" style="color: #ffd700;">#1 🥇</span> <span class="your-rank-text">' . ($type == 'weekly' ? 'this week' : 'overall') . '</span>';
    } elseif ($rank_number == 2) {
        $rank_display = '<span class="your-rank-number" style="color: #c0c0c0;">#2 🥈</span> <span class="your-rank-text">' . ($type == 'weekly' ? 'this week' : 'overall') . '</span>';
    } elseif ($rank_number == 3) {
        $rank_display = '<span class="your-rank-number" style="color: #cd7f32;">#3 🥉</span> <span class="your-rank-text">' . ($type == 'weekly' ? 'this week' : 'overall') . '</span>';
    }
    
    echo '<div class="your-rank-display">' . $rank_display . '</div>
          <div class="your-rank-stats">
              <div class="your-rank-stat"><span>📊 Total XP</span><strong>' . number_format($total_xp) . '</strong></div>
              <div class="your-rank-stat"><span>🎯 Current Level</span><strong>' . $current_level_name . '</strong></div>
              <div class="your-rank-stat"><span>🔥 Current Streak</span><strong>' . ($user_streak ? $user_streak->current_streak : 0) . ' days</strong></div>
              <div class="your-rank-stat"><span>🏆 Best Streak</span><strong>' . ($user_streak ? $user_streak->max_streak : 0) . ' days</strong></div>
              <div class="your-rank-stat"><span>📈 Progress to Next Level</span><strong>' . $user_level['progress_percent'] . '%</strong></div>
              <div class="your-rank-stat"><span>✨ XP to Next Level</span><strong>' . $user_level['xp_needed_next_level'] . ' XP</strong></div>
          </div>';
}
?>