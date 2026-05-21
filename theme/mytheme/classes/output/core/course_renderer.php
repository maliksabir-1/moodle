<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_mytheme\output\core;

defined('MOODLE_INTERNAL') || die();

// Since the core renderer is not autoloaded, we require it here to be absolutely safe.
global $CFG;
require_once($CFG->dirroot . '/course/renderer.php');

/**
 * Custom course renderer for mytheme to display courses as cards with user progress.
 *
 * @package    theme_mytheme
 * @copyright  2026 Malik Sabir
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer extends \core_course_renderer {

    /**
     * Renders HTML to display particular course category - list of its subcategories and courses.
     * Overridden to display modern cards with progress bars.
     *
     * @param int|stdClass|\core_course_category $category
     * @return string HTML content
     */
    public function course_category($category) {
        global $CFG, $DB, $USER;

        // Resolve the category object.
        $usertop = \core_course_category::user_top();
        if (empty($category)) {
            $coursecat = $usertop;
        } else if (is_object($category) && $category instanceof \core_course_category) {
            $coursecat = $category;
        } else {
            $coursecat = \core_course_category::get(is_object($category) ? $category->id : $category);
        }

        // Set up the page title and headings.
        if (\core_course_category::is_simple_site()) {
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title($strfulllistofcourses);
        } else if (!$coursecat->id || !$coursecat->is_uservisible()) {
            $strcategories = get_string('categories');
            $this->page->set_title($strcategories);
        } else {
            $strfulllistofcourses = get_string('fulllistofcourses');
            $this->page->set_title($strfulllistofcourses);
        }

        // Get subcategories.
        $subcategories = [];
        if ($coursecat->id) {
            $children = $coursecat->get_children();
        } else {
            $children = \core_course_category::top()->get_children();
        }

        foreach ($children as $child) {
            if ($child->is_uservisible()) {
                $subcategories[] = [
                    'id' => $child->id,
                    'name' => format_string($child->name),
                    'url' => (new \moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false)
                ];
            }
        }

        // Fetch all courses in this category (and subcategories recursively).
        $courses = [];
        if ($coursecat->id) {
            $courseslist = $coursecat->get_courses(['recursive' => true]);
        } else {
            $courseslist = \core_course_category::top()->get_courses(['recursive' => true]);
        }

        foreach ($courseslist as $c) {
            $coursecontext = \context_course::instance($c->id);
            if ($c->visible || has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                
                // Show only enrolled courses to students (real logged-in users who aren't admins/managers/teachers).
                if (isloggedin() && !isguestuser()) {
                    $isenrolled = is_enrolled($coursecontext, $USER->id, '', true);
                    $canmanage = has_capability('moodle/course:update', $coursecontext);
                    if (!$isenrolled && !$canmanage && !is_siteadmin()) {
                        continue;
                    }
                }

                // Extract course image.
                $courseimage = '';
                foreach ($c->get_course_overviewfiles() as $file) {
                    if ($file->is_valid_image()) {
                        $courseimage = \moodle_url::make_file_url(
                            "$CFG->wwwroot/pluginfile.php",
                            '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                            $file->get_filearea() . $file->get_filepath() . $file->get_filename(),
                            false
                        )->out(false);
                        break;
                    }
                }

                // Calculate progress accurately.
                $progress = null;
                $hasprogress = false;
                
                $completion = new \completion_info($c);
                if ($completion->is_enabled() && $completion->is_tracked_user($USER->id)) {
                    if ($completion->is_course_complete($USER->id)) {
                        $progress = 100;
                        $hasprogress = true;
                    } else {
                        $modules = $completion->get_activities();
                        $count = count($modules);
                        if ($count > 0) {
                            $totalcompleted = 0;
                            foreach ($modules as $mod) {
                                $completiondata = $completion->get_data($mod, true, $USER->id);
                                if ($completiondata->completionstate == COMPLETION_COMPLETE || 
                                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                                    $totalcompleted++;
                                }
                            }
                            $progress = round(min(100, ($totalcompleted / $count) * 100));
                            $hasprogress = true;
                        }
                    }
                }

                // Get category name.
                $catname = '';
                if ($cat = \core_course_category::get($c->category, IGNORE_MISSING)) {
                    $catname = format_string($cat->name);
                }

                // Clean summary.
                $summary = format_text($c->summary, $c->summaryformat, [
                    'noclean' => true,
                    'para' => false,
                    'overflowdiv' => true
                ]);
                $summary = strip_tags($summary);

                $courses[] = [
                    'id' => $c->id,
                    'fullname' => format_string($c->fullname),
                    'shortname' => format_string($c->shortname),
                    'viewurl' => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
                    'courseimage' => $courseimage,
                    'coursecategory' => $catname,
                    'hasprogress' => $hasprogress,
                    'progress' => $progress,
                    'summary' => $summary,
                    'visible' => $c->visible
                ];
            }
        }

        // Context context data.
        $context = [
            'config' => [
                'wwwroot' => $CFG->wwwroot
            ],
            'categoryname' => $coursecat->id ? format_string($coursecat->name) : 'All Courses',
            'hascategories' => !empty($subcategories),
            'subcategories' => $subcategories,
            'courses' => $courses,
            'hascourses' => !empty($courses),
            'parenturl' => ($coursecat->id && $coursecat->parent) ? (new \moodle_url('/course/index.php', ['categoryid' => $coursecat->parent]))->out(false) : null
        ];

        return $this->render_from_template('theme_mytheme/all_courses', $context);
    }
}
