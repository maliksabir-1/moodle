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

namespace block_blogpost\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to send blog post notifications.
 */
class send_blog_notifications extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('sendnotifications', 'block_blogpost');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace("Starting fallback blog post notification task...");

        // Get all blog posts that haven't had notifications sent yet
        $posts = $DB->get_records('block_blogpost', ['email_sent' => 0], 'timecreated ASC');

        if (empty($posts)) {
            mtrace("No new blog posts to process.");
            return;
        }

        foreach ($posts as $post) {
            mtrace("Processing blog post ID: {$post->id} - {$post->blog_heading} via fallback...");
            
            try {
                $emailtask = new \block_blogpost\task\send_blog_emails();
                $emailtask->set_custom_data((object)['postid' => $post->id]);
                $emailtask->execute();
            } catch (\Exception $e) {
                mtrace("Error processing post ID {$post->id}: " . $e->getMessage());
            }
        }
        
        mtrace("Fallback blog post notification task completed.");
    }
    
    /**
     * Get users to notify for a specific post.
     *
     * @param object $post The blog post
     * @return array List of users to notify
     */
    private function get_users_to_notify($post) {
        global $DB;
        
        // Get users who want email notifications
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.lang, p.email_notifications, p.notify_tags
                FROM {user} u
                LEFT JOIN {block_blogpost_prefs} p ON u.id = p.userid
                WHERE u.deleted = 0 
                AND u.suspended = 0
                AND u.id != :authorid
                AND (p.email_notifications = 1 OR p.email_notifications IS NULL)";
        
        $users = $DB->get_records_sql($sql, ['authorid' => $post->userid]);
        
        // Filter users based on tag preferences
        $filteredusers = [];
        $posttags = !empty($post->tags) ? explode(',', $post->tags) : [];
        $posttags = array_map('trim', $posttags);
        
        foreach ($users as $user) {
            if (empty($user->notify_tags)) {
                // User has no tag preferences, receive all notifications
                $filteredusers[] = $user;
            } else {
                $usertags = explode(',', $user->notify_tags);
                $usertags = array_map('trim', $usertags);
                
                // Check if any of the user's tags match the post's tags
                if (!empty($posttags) && array_intersect($usertags, $posttags)) {
                    $filteredusers[] = $user;
                }
            }
        }
        
        return $filteredusers;
    }
    
    /**
     * Send email notification to a user.
     *
     * @param object $user The user to notify
     * @param object $post The blog post
     * @return bool Success status
     */
    private function send_email_notification($user, $post) {
        global $CFG;
        
        $postauthor = fullname($post);
        $subject = get_string('emailsubject', 'block_blogpost', $post->blog_heading);
        
        $messagehtml = $this->get_email_html($user, $post, $postauthor);
        $messagetext = $this->get_email_text($user, $post, $postauthor);
        
        $from = \core_user::get_noreply_user();
        
        // Fixed: Direct call to email_to_user without extra object
        return email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    }
    
    /**
     * Get HTML version of email.
     *
     * @param object $user Recipient
     * @param object $post Blog post
     * @param string $postauthor Author name
     * @return string HTML email content
     */
    private function get_email_html($user, $post, $postauthor) {
        global $CFG;
        
        $html = "<html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a90e2; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .post-heading { color: #4a90e2; margin-top: 0; }
                .post-meta { color: #666; font-size: 12px; margin-bottom: 15px; }
                .post-text { margin: 20px 0; }
                .tags { margin: 15px 0; }
                .tag { background: #e0e0e0; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px; display: inline-block; }
                .footer { text-align: center; padding: 15px; font-size: 11px; color: #999; }
                .button { display: inline-block; padding: 10px 20px; background: #4a90e2; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Blog Post</h2>
                </div>
                <div class='content'>
                    <h3 class='post-heading'>" . s($post->blog_heading) . "</h3>
                    <div class='post-meta'>
                        Posted by: <strong>" . s($postauthor) . "</strong><br>
                        Date: " . userdate($post->timecreated) . "
                    </div>
                    <div class='post-text'>
                        " . nl2br(s($post->blog_text)) . "
                    </div>";
        
        if (!empty($post->tags)) {
            $html .= "<div class='tags'>
                        <strong>Tags:</strong> ";
            $tags = explode(',', $post->tags);
            foreach ($tags as $tag) {
                $html .= "<span class='tag'>" . s(trim($tag)) . "</span>";
            }
            $html .= "</div>";
        }
        
        $html .= "<div style='text-align: center; margin-top: 30px;'>
                        <a href='{$CFG->wwwroot}' class='button'>Go to Moodle</a>
                    </div>
                </div>
                <div class='footer'>
                    You are receiving this email because you have enabled notifications for the Blog Post block.<br>
                    You can change your notification preferences in your Moodle profile.
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Get plain text version of email.
     *
     * @param object $user Recipient
     * @param object $post Blog post
     * @param string $postauthor Author name
     * @return string Plain text email content
     */
    private function get_email_text($user, $post, $postauthor) {
        global $CFG;
        
        $text = "NEW BLOG POST\n";
        $text .= str_repeat("=", 50) . "\n\n";
        $text .= "Heading: " . s($post->blog_heading) . "\n";
        $text .= "Author: " . s($postauthor) . "\n";
        $text .= "Date: " . userdate($post->timecreated) . "\n";
        
        if (!empty($post->tags)) {
            $text .= "Tags: " . $post->tags . "\n";
        }
        
        $text .= "\nContent:\n";
        $text .= str_repeat("-", 30) . "\n";
        $text .= strip_tags($post->blog_text) . "\n";
        $text .= str_repeat("-", 30) . "\n\n";
        $text .= "View on Moodle: {$CFG->wwwroot}\n\n";
        $text .= "You can change your notification preferences in your Moodle profile.\n";
        
        return $text;
    }
}