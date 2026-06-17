<?php
// local/advancedanalytics/db/index.php
// Additional performance indexes

defined('MOODLE_INTERNAL') || die();


// local/advancedanalytics/index.php
// Main dashboard entry point - with COURSES DISPLAY

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Set context FIRST - THIS IS CRITICAL
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/advancedanalytics/index.php');

// Include analytics engine and db_access
require_once($CFG->dirroot . '/local/advancedanalytics/classes/analytics_engine.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/db_access.php');

require_login();

$PAGE->set_title(get_string('dashboard', 'local_advancedanalytics'));
$PAGE->set_heading(get_string('dashboard', 'local_advancedanalytics'));
$PAGE->navbar->add(get_string('dashboard', 'local_advancedanalytics'));

// Rest of your code remains the same...

// These indexes will be created automatically by Moodle from install.xml
// This file just documents the key indexes for performance

/*
Key Performance Indexes:

1. local_aa_summary:
   - INDEX(date) - For daily aggregation queries
   - INDEX(department) - For department filtering
   - INDEX(courseid) - For course-specific queries

2. local_aa_user_perf:
   - INDEX(risk_level) - For at-risk learner queries
   - INDEX(userid) - For user lookup
   - INDEX(engagement_score) - For sorting by engagement

3. local_aa_dept_stats:
   - INDEX(date) - For time-based filtering
   - INDEX(department) - For department lookup
   - INDEX(compliance_rate) - For compliance sorting

4. local_aa_course_cache:
   - INDEX(completion_rate) - For completion sorting
   - INDEX(date) - For time-based queries
*/