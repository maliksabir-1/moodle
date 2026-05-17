<?php
defined('MOODLE_INTERNAL') || die();

class block_blogpost extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_blogpost');
    }

    public function get_content() {
        global $USER, $PAGE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        
        // Add CSS
        $PAGE->requires->css('/blocks/blogpost/styles.css');
        
        // Form HTML
        $html = '<div id="blogpost-container" class="blogpost-block">';
        $html .= '  <div id="blogpost-message" class="alert" style="display:none;"></div>';
        $html .= '  <div class="blogpost-form mb-4">';
        $html .= '    <h5>' . get_string('blogpost', 'block_blogpost') . '</h5>';
        $html .= '    <div class="form-group mb-2">';
        $html .= '      <input type="text" id="blog_heading" class="form-control" placeholder="' . get_string('heading', 'block_blogpost') . '">';
        $html .= '    </div>';
        $html .= '    <div class="form-group mb-2">';
        $html .= '      <textarea id="blog_text" class="form-control" rows="3" placeholder="' . get_string('text', 'block_blogpost') . '"></textarea>';
        $html .= '    </div>';
        $html .= '    <div class="form-group mb-2">';
        $html .= '      <input type="text" id="blog_tags" class="form-control" placeholder="' . get_string('tagsplaceholder', 'block_blogpost') . '">';
        $html .= '      <small class="form-text text-muted">' . get_string('tagshelp', 'block_blogpost') . '</small>';
        $html .= '    </div>';
        $html .= '    <input type="hidden" id="blog_userid" value="' . $USER->id . '">';
        $html .= '    <input type="hidden" id="blog_username" value="' . fullname($USER) . '">';
        $html .= '    <input type="hidden" name="sesskey" value="' . sesskey() . '">';
        $html .= '    <button id="blogpost_submit" class="btn btn-primary btn-block w-100">' . get_string('submit', 'block_blogpost') . '</button>';
        $html .= '  </div>';

        // Add preference link
        $prefurl = new \moodle_url('/blocks/blogpost/preferences.php');
        $html .= '<div class="text-right mb-2">';
        $html .= '<a href="' . $prefurl->out() . '" class="btn btn-sm btn-link">' . get_string('notificationpreferences', 'block_blogpost') . '</a>';
        $html .= '</div>';

        // Existing Blogs List
        $blogs = $this->get_blogs();
        $admin_blogs = [];
        $user_blogs = [];
        $all_tags = [];

        foreach ($blogs as $blog) {
            if (is_siteadmin($blog->userid)) {
                $admin_blogs[] = $blog;
            } else {
                $user_blogs[] = $blog;
            }

            // Extract tags
            if (!empty($blog->tags)) {
                $tags = explode(',', $blog->tags);
                foreach ($tags as $tag) {
                    $trimmed = trim($tag);
                    if ($trimmed !== '') {
                        $lower_trimmed = strtolower($trimmed);
                        if (!isset($all_tags[$lower_trimmed])) {
                            $all_tags[$lower_trimmed] = [
                                'name' => $trimmed,
                                'count' => 0
                            ];
                        }
                        $all_tags[$lower_trimmed]['count']++;
                    }
                }
            }
        }

        // Tag Filter Bar
        if (!empty($all_tags)) {
            $html .= '<div class="tag-filter-container mb-3 p-2 bg-light rounded d-flex flex-wrap align-items-center">';
            $html .= '  <span class="filter-label mr-2 font-weight-bold text-muted small"><i class="fa fa-filter"></i> ' . get_string('filterbytag', 'block_blogpost') . '</span>';
            $html .= '  <span class="badge badge-primary tag-filter-badge mr-1 cursor-pointer py-1 px-2 mb-1 active" data-tag="all" style="cursor: pointer;">' . get_string('tagfilterall', 'block_blogpost') . '</span>';
            foreach ($all_tags as $taginfo) {
                $html .= '  <span class="badge badge-light tag-filter-badge mr-1 cursor-pointer py-1 px-2 mb-1" data-tag="' . s(strtolower($taginfo['name'])) . '" style="cursor: pointer; opacity: 0.85;">' . s($taginfo['name']) . ' (' . $taginfo['count'] . ')</span>';
            }
            $html .= '</div>';
        }

        // Admin Blogs Section
        $html .= '  <div class="blog-section mt-3">';
        $html .= '    <h6 class="section-title border-bottom pb-1 mb-2 text-primary"><i class="fa fa-shield"></i> ' . get_string('adminblogs', 'block_blogpost') . '</h6>';
        $html .= '    <div id="blogpost-list-admin">';
        if (empty($admin_blogs)) {
            $html .= '<p class="text-muted small">No admin posts yet.</p>';
        }
        foreach ($admin_blogs as $blog) {
            $html .= $this->render_blog_card($blog);
        }
        $html .= '    </div>';
        $html .= '  </div>';

        // User Blogs Section
        $html .= '  <div class="blog-section mt-3">';
        $html .= '    <h6 class="section-title border-bottom pb-1 mb-2 text-info"><i class="fa fa-users"></i> ' . get_string('userblogs', 'block_blogpost') . '</h6>';
        $html .= '    <div id="blogpost-list-user">';
        if (empty($user_blogs)) {
            $html .= '<p class="text-muted small">No user posts yet.</p>';
        }
        foreach ($user_blogs as $blog) {
            $html .= $this->render_blog_card($blog);
        }
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        $this->content->text = $html;
        $this->content->footer = '';

        // Include JS
        $js_url = new moodle_url('/blocks/blogpost/js/block_blogpost.js');
        $PAGE->requires->js($js_url);
        
        return $this->content;
    }

    private function get_blogs() {
        global $DB;
        
        $sql = "SELECT u.*, b.*, b.id AS id, u.id AS userid, b.tags
                FROM {block_blogpost} b 
                JOIN {user} u ON b.userid = u.id 
                ORDER BY b.timecreated DESC";
        return $DB->get_records_sql($sql, null, 0, 10);
    }

    private function format_blog_text($text) {
        global $DB, $CFG;
        
        // Escape text first to avoid XSS
        $formatted = s($text);
        
        // Regex for @username
        $formatted = preg_replace_callback('/@([a-zA-Z0-9_.-]+)/', function($matches) use ($DB, $CFG) {
            $username = strtolower($matches[1]);
            $user = $DB->get_record_select('user', 'LOWER(username) = :username AND deleted = 0 AND suspended = 0', ['username' => $username], 'id, firstname, lastname');
            if ($user) {
                $fullname = fullname($user);
                $profileurl = new \moodle_url('/user/profile.php', ['id' => $user->id]);
                return '<a href="' . $profileurl->out() . '" class="mention-link badge badge-info p-1" title="' . s($fullname) . '"><i class="fa fa-user"></i> @' . s($username) . '</a>';
            }
            return '@' . $matches[1];
        }, $formatted);
        
        return nl2br($formatted);
    }

    private function render_blog_card($blog) {
        $author = fullname($blog);
        $time = userdate($blog->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        
        // Lowercase tags for easy exact matching in JS
        $cardtags = '';
        if (!empty($blog->tags)) {
            $tags = explode(',', $blog->tags);
            $tags = array_map('trim', array_map('strtolower', $tags));
            $cardtags = implode(',', $tags);
        }
        
        $html = '<div class="blog-card card mb-2" data-tags="' . s($cardtags) . '">';
        $html .= '  <div class="card-body p-3">';
        $html .= '    <h6 class="card-title mb-1">' . s($blog->blog_heading) . '</h6>';
        $html .= '    <p class="card-subtitle mb-2 text-muted small">';
        $html .= '      <i class="fa fa-user"></i> ' . s($author) . ' | <i class="fa fa-clock-o"></i> ' . $time;
        
        if (!empty($blog->tags)) {
            $html .= ' | <i class="fa fa-tags"></i> ';
            $tags = explode(',', $blog->tags);
            foreach ($tags as $tag) {
                $html .= '<span class="badge badge-secondary cursor-pointer" style="cursor:pointer;" data-tag-click="' . s(strtolower(trim($tag))) . '">' . s(trim($tag)) . '</span> ';
            }
        }
        
        $html .= '    </p>';
        $html .= '    <p class="card-text">' . $this->format_blog_text($blog->blog_text) . '</p>';
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}