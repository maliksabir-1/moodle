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
 * Ad-hoc task to send blog post notifications.
 */
class send_blog_emails extends \core\task\adhoc_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('sendblogemails', 'block_blogpost');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->postid)) {
            mtrace("Error: No post ID specified.");
            return;
        }

        $postid = $data->postid;
        mtrace("Starting immediate blog post email task for Post ID: {$postid}");

        // Fetch post
        $post = $DB->get_record('block_blogpost', ['id' => $postid]);
        if (!$post) {
            mtrace("Error: Blog post ID {$postid} not found.");
            return;
        }

        // Avoid duplicate emails if another task already processed this post
        if (!empty($post->email_sent)) {
            mtrace("Notice: Emails have already been sent for Post ID {$postid}. Skipping to prevent duplicates.");
            return;
        }

        // Fetch author
        $author = $DB->get_record('user', ['id' => $post->userid]);
        if (!$author) {
            mtrace("Error: Author for blog post ID {$postid} not found.");
            return;
        }

        // 1. Process mentions (@username)
        $mentionedusers = $this->get_mentioned_users($post);
        
        // 2. Process general/tag subscribers
        $subscribingusers = $this->get_subscribing_users($post, array_keys($mentionedusers));

        $from = $author;
        $authorname = fullname($author);

        // Send to mentioned users
        $mentionedcount = 0;
        foreach ($mentionedusers as $user) {
            if ($this->send_mention_email($user, $post, $authorname, $from)) {
                $mentionedcount++;
            }
        }
        mtrace("Sent {$mentionedcount} mention notifications.");

        // Send to other subscribing users
        $generalcount = 0;
        foreach ($subscribingusers as $user) {
            if ($this->send_general_email($user, $post, $authorname, $from)) {
                $generalcount++;
            }
        }
        mtrace("Sent {$generalcount} general/tag notifications.");

        // Mark post as processed
        $post->email_sent = 1;
        $DB->update_record('block_blogpost', $post);

        mtrace("Email task for Post ID {$postid} completed successfully.");
    }

    /**
     * Extract and fetch mentioned users (@username) in the post
     */
    private function get_mentioned_users($post) {
        global $DB;
        $mentioned = [];
        $text = $post->blog_heading . ' ' . $post->blog_text;

        // Pattern matches @username where username has typical moodle username chars
        if (preg_match_all('/@([a-zA-Z0-9_.-]+)/', $text, $matches)) {
            $usernames = array_unique($matches[1]);
            foreach ($usernames as $username) {
                // Fetch case-insensitively
                $user = $DB->get_record_select(
                    'user',
                    'LOWER(username) = :username AND deleted = 0 AND suspended = 0',
                    ['username' => strtolower($username)]
                );
                // Exclude author from self-notification
                if ($user && $user->id != $post->userid) {
                    $mentioned[$user->id] = $user;
                }
            }
        }
        return $mentioned;
    }

    /**
     * Get other subscribing users (excluding mentioned ones to avoid duplicates)
     */
    private function get_subscribing_users($post, $excludids) {
        global $DB;

        // Query users with preference record (excluding Guest user which has ID 1)
        $sql = "SELECT u.*, p.email_notifications, p.notify_tags
                FROM {user} u
                LEFT JOIN {block_blogpost_prefs} p ON u.id = p.userid
                WHERE u.deleted = 0 
                AND u.suspended = 0
                AND u.id > 1
                AND u.id != :authorid";
        
        $params = ['authorid' => $post->userid];
        $users = $DB->get_records_sql($sql, $params);

        $recipients = [];
        $posttags = !empty($post->tags) ? explode(',', $post->tags) : [];
        $posttags = array_map('trim', array_map('strtolower', $posttags));

        foreach ($users as $user) {
            // Exclude already-notified mentioned users
            if (in_array($user->id, $excludids)) {
                continue;
            }

            $wantsgeneral = ($user->email_notifications === null || $user->email_notifications == 1);
            $hastags = !empty($user->notify_tags);

            if ($wantsgeneral && !$hastags) {
                // General updates enabled with no tag filter
                $recipients[$user->id] = $user;
            } else if ($hastags && !empty($posttags)) {
                // Custom tag filter matches post tags
                $usertags = explode(',', $user->notify_tags);
                $usertags = array_map('trim', array_map('strtolower', $usertags));
                
                if (array_intersect($usertags, $posttags)) {
                    $recipients[$user->id] = $user;
                }
            }
        }

        return $recipients;
    }

    /**
     * Send email to a mentioned user
     */
    private function send_mention_email($user, $post, $authorname, $from) {
        $subject = get_string('mentionemailsubject', 'block_blogpost', $post->blog_heading);
        
        $messagehtml = $this->get_mention_email_html($user, $post, $authorname);
        $messagetext = $this->get_mention_email_text($user, $post, $authorname);
        
        return email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    }

    /**
     * Send standard update email
     */
    private function send_general_email($user, $post, $authorname, $from) {
        $subject = get_string('emailsubject', 'block_blogpost', $post->blog_heading);
        
        $messagehtml = $this->get_general_email_html($user, $post, $authorname);
        $messagetext = $this->get_general_email_text($user, $post, $authorname);
        
        return email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    }

    /**
     * HTML content for mention email
     */
    private function get_mention_email_html($user, $post, $authorname) {
        global $CFG;
        $html = "<html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 6px 6px 0 0; }
                .content { padding: 20px; background: #f9f9f9; border: 1px solid #eee; border-top: none; }
                .post-heading { color: #17a2b8; margin-top: 0; }
                .post-meta { color: #666; font-size: 12px; margin-bottom: 15px; }
                .post-text { margin: 20px 0; background: #ffffff; padding: 15px; border-left: 4px solid #17a2b8; border-radius: 4px; }
                .tags { margin: 15px 0; }
                .tag { background: #e0e0e0; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px; display: inline-block; }
                .footer { text-align: center; padding: 15px; font-size: 11px; color: #999; }
                .button { display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>You Were Mentioned</h2>
                </div>
                <div class='content'>
                    <p>Hi " . s($user->firstname) . ",</p>
                    <p><strong>" . s($authorname) . "</strong> mentioned you in a new blog post:</p>
                    <h3 class='post-heading'>" . s($post->blog_heading) . "</h3>
                    <div class='post-meta'>
                        Posted on: " . userdate($post->timecreated) . "
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
                        <a href='{$CFG->wwwroot}' class='button' style='color: white;'>View on Moodle</a>
                    </div>
                </div>
                <div class='footer'>
                    You are receiving this email because you were directly mentioned in a blog post.<br>
                    You can manage notification preferences in your Moodle profile settings.
                </div>
            </div>
        </body>
        </html>";
        return $html;
    }

    /**
     * Text content for mention email
     */
    private function get_mention_email_text($user, $post, $authorname) {
        global $CFG;
        $text = "YOU WERE MENTIONED\n";
        $text .= str_repeat("=", 50) . "\n\n";
        $text .= "Hi " . $user->firstname . ",\n\n";
        $text .= "{$authorname} mentioned you in a new blog post:\n\n";
        $text .= "Heading: " . s($post->blog_heading) . "\n";
        $text .= "Date: " . userdate($post->timecreated) . "\n\n";
        $text .= "Content:\n";
        $text .= str_repeat("-", 30) . "\n";
        $text .= strip_tags($post->blog_text) . "\n";
        $text .= str_repeat("-", 30) . "\n\n";
        $text .= "View on Moodle: {$CFG->wwwroot}\n";
        return $text;
    }

    /**
     * HTML content for general subscription email
     */
    private function get_general_email_html($user, $post, $authorname) {
        global $CFG;
        $html = "<html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 6px 6px 0 0; }
                .content { padding: 20px; background: #f9f9f9; border: 1px solid #eee; border-top: none; }
                .post-heading { color: #4a90e2; margin-top: 0; }
                .post-meta { color: #666; font-size: 12px; margin-bottom: 15px; }
                .post-text { margin: 20px 0; background: #ffffff; padding: 15px; border-left: 4px solid #4a90e2; border-radius: 4px; }
                .tags { margin: 15px 0; }
                .tag { background: #e0e0e0; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px; display: inline-block; }
                .footer { text-align: center; padding: 15px; font-size: 11px; color: #999; }
                .button { display: inline-block; padding: 10px 20px; background: #4a90e2; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Blog Post</h2>
                </div>
                <div class='content'>
                    <p>Hi " . s($user->firstname) . ",</p>
                    <h3 class='post-heading'>" . s($post->blog_heading) . "</h3>
                    <div class='post-meta'>
                        Posted by: <strong>" . s($authorname) . "</strong><br>
                        Date: " . userdate($post->timecreated) .
                    "</div>
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
                        <a href='{$CFG->wwwroot}' class='button' style='color: white;'>View on Moodle</a>
                    </div>
                </div>
                <div class='footer'>
                    You are receiving this email because you subscribed to updates from the Blog Post block.<br>
                    You can manage notification preferences in your Moodle profile settings.
                </div>
            </div>
        </body>
        </html>";
        return $html;
    }

    /**
     * Text content for general subscription email
     */
    private function get_general_email_text($user, $post, $authorname) {
        global $CFG;
        $text = "NEW BLOG POST\n";
        $text .= str_repeat("=", 50) . "\n\n";
        $text .= "Hi " . $user->firstname . ",\n\n";
        $text .= "Heading: " . s($post->blog_heading) . "\n";
        $text .= "Author: " . s($authorname) . "\n";
        $text .= "Date: " . userdate($post->timecreated) . "\n\n";
        $text .= "Content:\n";
        $text .= str_repeat("-", 30) . "\n";
        $text .= strip_tags($post->blog_text) . "\n";
        $text .= str_repeat("-", 30) . "\n\n";
        $text .= "View on Moodle: {$CFG->wwwroot}\n";
        return $text;
    }
}
