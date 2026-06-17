#!/usr/bin/env php
<?php
// local/advancedanalytics/cli/test_phase3.php
// Test Phase 3 Core Analytics Engine

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');  // ADD THIS - provides cli_heading() and cli_writeln()

// Helper function for headings if clilib doesn't have it
if (!function_exists('cli_heading')) {
    function cli_heading($text) {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "  " . $text . "\n";
        echo str_repeat('=', 60) . "\n";
    }
}

require_once($CFG->dirroot . '/local/advancedanalytics/classes/analytics_engine.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/analytics_calculations.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/kpi_calculator.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/department_analytics.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/learner_scoring.php');
require_once($CFG->dirroot . '/local/advancedanalytics/classes/compliance_engine.php');

cli_heading('PHASE 3: Core Analytics Engine Test');

// Test 1: Analytics Engine
cli_writeln("\n[1/6] Testing analytics_engine.php...");
try {
    $kpis = \local_advancedanalytics\analytics_engine::get_all_kpis();
    cli_writeln("   ✅ Total Users: " . ($kpis['total_users'] ?? 'N/A'));
    cli_writeln("   ✅ Active Users: " . ($kpis['active_users'] ?? 'N/A'));
    cli_writeln("   ✅ Completion Rate: " . ($kpis['completion_rate'] ?? 'N/A') . '%');
    cli_writeln("   ✅ Average Grade: " . ($kpis['average_grade'] ?? 'N/A') . '%');
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Test 2: Analytics Calculations
cli_writeln("\n[2/6] Testing analytics_calculations.php...");
try {
    $completion = \local_advancedanalytics\analytics_calculations::calculate_completion_rate();
    cli_writeln("   ✅ Total Users: " . ($completion['total_users'] ?? 'N/A'));
    cli_writeln("   ✅ Users Completed: " . ($completion['users_completed'] ?? 'N/A'));
    cli_writeln("   ✅ Completion Rate: " . ($completion['completion_rate'] ?? 'N/A') . '%');
    
    $active = \local_advancedanalytics\analytics_calculations::calculate_active_users('month');
    cli_writeln("   ✅ Active Users (30d): " . ($active['active_users'] ?? 'N/A'));
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Test 3: KPI Calculator
cli_writeln("\n[3/6] Testing kpi_calculator.php...");
try {
    $overview = \local_advancedanalytics\kpi_calculator::get_overview_kpis();
    cli_writeln("   ✅ Total Users: " . ($overview['total_users'] ?? 'N/A'));
    cli_writeln("   ✅ Active Users: " . ($overview['active_users'] ?? 'N/A'));
    cli_writeln("   ✅ Total Courses: " . ($overview['total_courses'] ?? 'N/A'));
    cli_writeln("   ✅ Completion Rate: " . ($overview['completion_rate'] ?? 'N/A') . '%');
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Test 4: Department Analytics
cli_writeln("\n[4/6] Testing department_analytics.php...");
try {
    $dept_analytics = \local_advancedanalytics\department_analytics::get_all_department_analytics();
    $dept_count = count($dept_analytics);
    cli_writeln("   ✅ Found " . $dept_count . " departments with analytics");
    
    if ($dept_count > 0) {
        $first_dept = array_key_first($dept_analytics);
        cli_writeln("   ✅ Sample Department: " . $first_dept);
        if ($dept_analytics[$first_dept]) {
            cli_writeln("   ✅ - Total Users: " . ($dept_analytics[$first_dept]['total_users'] ?? 'N/A'));
            cli_writeln("   ✅ - Completion Rate: " . ($dept_analytics[$first_dept]['completion_rate'] ?? 'N/A') . '%');
        }
    }
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Test 5: Learner Scoring
cli_writeln("\n[5/6] Testing learner_scoring.php...");
try {
    $sample_user = $DB->get_field_sql("
        SELECT id FROM {user} 
        WHERE deleted = 0 AND suspended = 0 AND username != 'guest' 
        LIMIT 1
    ");
    
    if ($sample_user) {
        $score = \local_advancedanalytics\learner_scoring::calculate_learner_score($sample_user);
        cli_writeln("   ✅ Sample User ID: " . $sample_user);
        cli_writeln("   ✅ Overall Score: " . ($score['overall_score'] ?? 'N/A'));
        cli_writeln("   ✅ Performance Level: " . ($score['performance_level'] ?? 'N/A'));
        cli_writeln("   ✅ Completed Courses: " . ($score['stats']['completed_courses'] ?? 'N/A'));
    } else {
        cli_writeln("   ⚠️ No users found for scoring test");
    }
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Test 6: Compliance Engine
cli_writeln("\n[6/6] Testing compliance_engine.php...");
try {
    $compliance_summary = \local_advancedanalytics\compliance_engine::get_compliance_summary();
    cli_writeln("   ✅ Total Mandatory Courses: " . ($compliance_summary['total_mandatory_courses'] ?? 'N/A'));
    cli_writeln("   ✅ Total Users Tracked: " . ($compliance_summary['total_users_tracked'] ?? 'N/A'));
    cli_writeln("   ✅ Fully Compliant: " . ($compliance_summary['fully_compliant'] ?? 'N/A'));
    cli_writeln("   ✅ Partially Compliant: " . ($compliance_summary['partially_compliant'] ?? 'N/A'));
    cli_writeln("   ✅ Non-Compliant: " . ($compliance_summary['non_compliant'] ?? 'N/A'));
} catch (Exception $e) {
    cli_writeln("   ❌ Error: " . $e->getMessage());
}

// Summary
cli_heading("PHASE 3 TEST COMPLETE");
cli_writeln("\n📊 If you see values above (not errors), Phase 3 is working correctly!");
cli_writeln("🎯 Next step: Access /local/advancedanalytics/phase3_dashboard.php in your browser\n");