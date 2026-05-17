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
        $html .= '    <input type="hidden" id="blog_userid" value="' . $USER->id . '">';
        $html .= '    <input type="hidden" id="blog_username" value="' . fullname($USER) . '">';
        $html .= '    <button id="blogpost_submit" class="btn btn-primary btn-block w-100">' . get_string('submit', 'block_blogpost') . '</button>';
        $html .= '  </div>';

        // Existing Blogs List
        $blogs = $this->get_blogs();
        $admin_blogs = [];
        $user_blogs = [];

        foreach ($blogs as $blog) {
            if (is_siteadmin($blog->userid)) {
                $admin_blogs[] = $blog;
            } else {
                $user_blogs[] = $blog;
            }
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
        
        $sql = "SELECT u.*, b.*, b.id AS id, u.id AS userid
                FROM {block_blogpost} b 
                JOIN {user} u ON b.userid = u.id 
                ORDER BY b.timecreated DESC";
        return $DB->get_records_sql($sql, null, 0, 10);
    }

    private function render_blog_card($blog) {
        $author = fullname($blog);
        $time = userdate($blog->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
        
        $html = '<div class="blog-card card mb-2">';
        $html .= '  <div class="card-body p-3">';
        $html .= '    <h6 class="card-title mb-1">' . s($blog->blog_heading) . '</h6>';
        $html .= '    <p class="card-subtitle mb-2 text-muted small">';
        $html .= '      <i class="fa fa-user"></i> ' . s($author) . ' | <i class="fa fa-clock-o"></i> ' . $time;
        $html .= '    </p>';
        $html .= '    <p class="card-text">' . nl2br(s($blog->blog_text)) . '</p>';
        $html .= '  </div>';
        $html .= '</div>';
        return $html;
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}
