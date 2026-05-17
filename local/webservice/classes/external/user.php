<?php
namespace local_webservice\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/grade/grade_item.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use context_course;
use completion_info;
use grade_item;

class user extends external_api {

    // ==================== 1. CREATE USER ====================
    
    public static function create_user_parameters() {
        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'Username'),
            'password' => new external_value(PARAM_RAW, 'Password'),
            'firstname' => new external_value(PARAM_TEXT, 'First name'),
            'lastname' => new external_value(PARAM_TEXT, 'Last name'),
            'email' => new external_value(PARAM_EMAIL, 'Email address'),
        ]);
    }

    public static function create_user($username, $password, $firstname, $lastname, $email) {
        global $CFG, $DB;
        
        $params = self::validate_parameters(self::create_user_parameters(), [
            'username' => $username,
            'password' => $password,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email
        ]);
        
        // Check if user exists
        $existing = $DB->get_record('user', ['username' => $params['username']]);
        if ($existing) {
            return [
                'id' => (int)$existing->id, 
                'status' => 'exists', 
                'message' => 'User already exists'
            ];
        }
        
        // Create user
        $user = (object)$params;
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->password = hash_internal_user_password($user->password);
        $user->timecreated = time();
        
        $userid = user_create_user($user);
        
        return [
            'id' => (int)$userid, 
            'status' => 'created', 
            'message' => 'User created successfully'
        ];
    }

    public static function create_user_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    // ==================== 2. ENROL USER ====================
    
    public static function enrol_user_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'roleid' => new external_value(PARAM_INT, 'Role ID (5 = student)', VALUE_DEFAULT, 5),
        ]);
    }

    public static function enrol_user($userid, $courseid, $roleid = 5) {
        global $DB;
        
        $params = self::validate_parameters(self::enrol_user_parameters(), [
            'userid' => $userid, 
            'courseid' => $courseid, 
            'roleid' => $roleid
        ]);
        
        // Get manual enrolment instance
        $instance = $DB->get_record('enrol', [
            'courseid' => $params['courseid'], 
            'enrol' => 'manual'
        ]);
        
        if (!$instance) {
            return ['success' => false, 'message' => 'No manual enrolment method found'];
        }
        
        $enrolmanual = enrol_get_plugin('manual');
        $enrolmanual->enrol_user($instance, $params['userid'], $params['roleid']);
        
        return [
            'success' => true, 
            'message' => 'User enrolled successfully',
            'userid' => $params['userid'],
            'courseid' => $params['courseid']
        ];
    }

    public static function enrol_user_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
        ]);
    }

    // ==================== 3. SUSPEND USER ====================
    
    public static function suspend_user_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'suspend' => new external_value(PARAM_BOOL, 'True to suspend, false to unsuspend', VALUE_DEFAULT, true),
        ]);
    }

    public static function suspend_user($userid, $courseid, $suspend = true) {
        global $DB;
        
        $params = self::validate_parameters(self::suspend_user_parameters(), [
            'userid' => $userid, 
            'courseid' => $courseid, 
            'suspend' => $suspend
        ]);
        
        // Get the enrolment instance for this course
        $enrol = $DB->get_record('enrol', [
            'courseid' => $params['courseid'], 
            'enrol' => 'manual'
        ]);
        
        if (!$enrol) {
            return ['success' => false, 'message' => 'No manual enrolment method found'];
        }
        
        // Get user enrolment record
        $userenrol = $DB->get_record('user_enrolments', [
            'userid' => $params['userid'],
            'enrolid' => $enrol->id
        ]);
        
        if (!$userenrol) {
            return ['success' => false, 'message' => 'User not enrolled in this course'];
        }
        
        // Update enrolment status
        $userenrol->status = $params['suspend'] ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
        $DB->update_record('user_enrolments', $userenrol);
        
        return [
            'success' => true, 
            'message' => $params['suspend'] ? 'User suspended' : 'User unsuspended',
            'userid' => $params['userid'],
            'courseid' => $params['courseid'],
            'suspended' => $params['suspend']
        ];
    }

    public static function suspend_user_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'suspended' => new external_value(PARAM_BOOL, 'Suspend state'),
        ]);
    }

    // ==================== 4. GET COURSE DETAILS (COMPLETE) ====================
    
    public static function get_course_details_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function get_course_details($courseid) {
        global $DB, $CFG;
        
        $params = self::validate_parameters(self::get_course_details_parameters(), ['courseid' => $courseid]);
        
        // Get course
        $course = $DB->get_record('course', ['id' => $params['courseid']]);
        if (!$course) {
            throw new \moodle_exception('invalidcourse', 'local_webservice');
        }
        
        $context = context_course::instance($course->id);
        
        // Get all enrolled users (students only)
        $enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email, u.username');
        
        // Get all course activities/modules
        $modinfo = get_fast_modinfo($course);
        $activities = [];
        
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->uservisible && $cm->has_view()) {
                $activities[] = $cm;
            }
        }
        
        $totalactivities = count($activities);
        $users = [];
        $totalprogress = 0;
        
        foreach ($enrolled_users as $user) {
            // Get course completion progress
            $completion = new completion_info($course);
            $progress = 0;
            $completedactivities = 0;
            $completedactivitieslist = [];
            
            if ($completion->is_enabled() && $totalactivities > 0) {
                foreach ($activities as $activity) {
                    $data = $completion->get_data($activity, false, $user->id);
                    $iscomplete = ($data->completionstate == COMPLETION_COMPLETE || 
                                   $data->completionstate == COMPLETION_COMPLETE_PASS);
                    
                    if ($iscomplete) {
                        $completedactivities++;
                        
                        // Get activity grade
                        $activitygrade = null;
                        $gradinginfo = grade_get_grades($course->id, 'mod', $activity->modname, $activity->instance, $user->id);
                        
                        if ($gradinginfo && isset($gradinginfo->items[0]) && isset($gradinginfo->items[0]->grades[$user->id])) {
                            $gradeobj = $gradinginfo->items[0]->grades[$user->id];
                            $activitygrade = $gradeobj->grade !== null ? round($gradeobj->grade, 2) : null;
                        }
                        
                        $completedactivitieslist[] = [
                            'name' => $activity->name,
                            'type' => $activity->modname,
                            'completion_date' => date('Y-m-d H:i:s', $data->timemodified),
                            'grade' => $activitygrade
                        ];
                    }
                }
                $progress = round(($completedactivities / $totalactivities) * 100);
                $totalprogress += $progress;
            }
            
            // Get total course grade
            $coursegrade = null;
            $coursegradepercentage = null;
            $courserawgrade = null;
            
            // Get grade item for the course
            $grade_item = grade_item::fetch_course_item($course->id);
            if ($grade_item) {
                $usergrade = new \grade_grade(array('itemid' => $grade_item->id, 'userid' => $user->id));
                if ($usergrade && $usergrade->finalgrade !== null) {
                    $courserawgrade = round($usergrade->finalgrade, 2);
                    if ($grade_item->grademax > 0) {
                        $coursegradepercentage = round(($usergrade->finalgrade / $grade_item->grademax) * 100, 2);
                        $coursegrade = $coursegradepercentage . '%';
                    }
                }
            }
            
            // Get enrolment date
            $enrolrecord = $DB->get_record('user_enrolments', ['userid' => $user->id]);
            $enrolmentdate = $enrolrecord ? date('Y-m-d H:i:s', $enrolrecord->timestart) : null;
            
            // Get suspended status
            $issuspended = $enrolrecord ? ($enrolrecord->status == ENROL_USER_SUSPENDED) : false;
            
            // Check if user completed the course
            $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
            $coursecompleted = $ccompletion->is_complete();
            
            $users[] = [
                'id' => (int)$user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'enrolment_date' => $enrolmentdate,
                'progress_percentage' => $progress,
                'completed_activities_count' => $completedactivities,
                'total_activities_count' => $totalactivities,
                'course_completed' => $coursecompleted,
                'suspended' => $issuspended,
                'course_grade' => $coursegrade,
                'course_grade_raw' => $courserawgrade,
                'course_grade_percentage' => $coursegradepercentage,
                'completed_activities' => $completedactivitieslist
            ];
        }
        
        $averageprogress = count($users) > 0 ? round($totalprogress / count($users)) : 0;
        
        return [
            'courseid' => (int)$course->id,
            'coursename' => $course->fullname,
            'courseshortname' => $course->shortname,
            'total_users' => count($users),
            'total_activities' => $totalactivities,
            'average_progress' => $averageprogress,
            'users' => $users
        ];
    }

    public static function get_course_details_returns() {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
            'courseshortname' => new external_value(PARAM_TEXT, 'Course short name'),
            'total_users' => new external_value(PARAM_INT, 'Total enrolled users'),
            'total_activities' => new external_value(PARAM_INT, 'Total activities in course'),
            'average_progress' => new external_value(PARAM_INT, 'Average course progress'),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'username' => new external_value(PARAM_USERNAME, 'Username'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                    'enrolment_date' => new external_value(PARAM_TEXT, 'Enrolment date', VALUE_OPTIONAL),
                    'progress_percentage' => new external_value(PARAM_INT, 'Progress percentage'),
                    'completed_activities_count' => new external_value(PARAM_INT, 'Number of completed activities'),
                    'total_activities_count' => new external_value(PARAM_INT, 'Total activities'),
                    'course_completed' => new external_value(PARAM_BOOL, 'Course completed?'),
                    'suspended' => new external_value(PARAM_BOOL, 'Is suspended?'),
                    'course_grade' => new external_value(PARAM_TEXT, 'Course grade (formatted)', VALUE_OPTIONAL),
                    'course_grade_raw' => new external_value(PARAM_FLOAT, 'Raw course grade', VALUE_OPTIONAL),
                    'course_grade_percentage' => new external_value(PARAM_FLOAT, 'Grade percentage', VALUE_OPTIONAL),
                    'completed_activities' => new external_multiple_structure(
                        new external_single_structure([
                            'name' => new external_value(PARAM_TEXT, 'Activity name'),
                            'type' => new external_value(PARAM_TEXT, 'Activity type'),
                            'completion_date' => new external_value(PARAM_TEXT, 'Completion date'),
                            'grade' => new external_value(PARAM_FLOAT, 'Activity grade', VALUE_OPTIONAL),
                        ])
                    ),
                ])
            ),
        ]);
    }
}