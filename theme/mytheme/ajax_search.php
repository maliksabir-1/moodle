<?php
// theme/mytheme/ajax_search.php
define('AJAX_SCRIPT', true);

// Disable debugging output that might break JSON
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', false);

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get search term
$searchterm = optional_param('q', '', PARAM_TEXT);

// Validate search term
if (strlen($searchterm) < 2) {
    echo json_encode([]);
    die();
}

try {
    $searchterm = trim($searchterm);
    
    $params = [
        'siteid' => SITEID,
        'search1' => '%' . $DB->sql_like_escape($searchterm) . '%',
        'search2' => '%' . $DB->sql_like_escape($searchterm) . '%'
    ];
    
    $sql = "SELECT c.id, c.fullname, c.shortname, c.summary, 
                   cc.name as categoryname,
                   (SELECT COUNT(DISTINCT ue.userid) 
                    FROM {user_enrolments} ue 
                    JOIN {enrol} e ON e.id = ue.enrolid 
                    WHERE e.courseid = c.id 
                      AND ue.status = 0
                      AND e.status = 0) as studentcount
            FROM {course} c
            LEFT JOIN {course_categories} cc ON cc.id = c.category
            WHERE c.visible = 1 
              AND c.id != :siteid
              AND (c.fullname LIKE :search1 OR c.shortname LIKE :search2)
            ORDER BY c.fullname ASC
            LIMIT 10";
    
    $courses = $DB->get_records_sql($sql, $params);
    
    $results = [];
    foreach ($courses as $course) {
        // Get course image
        $image = '';
        try {
            $course_element = new core_course_list_element($course);
            $files = $course_element->get_course_overviewfiles();
            foreach ($files as $file) {
                if ($file && $file->is_valid_image()) {
                    $url = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    $image = $url->out(false);
                    break;
                }
            }
        } catch (Exception $e) {
            // No image found, continue
        }
        
        $results[] = [
            'id' => (int)$course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname ?? '',
            'summary' => shorten_text(strip_tags($course->summary ?? ''), 100),
            'image' => $image,
            'categoryname' => $course->categoryname ?? '',
            'url' => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            'studentcount' => (int)($course->studentcount ?? 0)
        ];
    }
    
    // Ensure we output valid JSON
    echo json_encode($results, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    
} catch (Exception $e) {
    // Log error but return empty array to user
    error_log('AJAX Search Error: ' . $e->getMessage());
    echo json_encode([]);
}
die();