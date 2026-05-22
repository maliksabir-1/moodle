<?php
namespace block_blogpost\task;

defined('MOODLE_INTERNAL') || die();

class send_reply_emails extends \core\task\adhoc_task {

    public function get_name() {
        return 'Send blog post reply emails';
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->replyid)) {
            // mtrace("Error: No reply ID specified.");
            return;
        }

        $replyid = $data->replyid;
        // mtrace("Starting blog post reply email task for Reply ID: {$replyid}");

        $reply = $DB->get_record('block_blogpost_replies', ['id' => $replyid]);
        if (!$reply) {
            // mtrace("Error: Reply ID {$replyid} not found.");
            return;
        }

        if (!empty($reply->email_sent)) {
            // mtrace("Notice: Emails have already been sent for Reply ID {$replyid}. Skipping.");
            return;
        }

        $post = $DB->get_record('block_blogpost', ['id' => $reply->postid]);
        if (!$post) {
            // mtrace("Error: Parent post ID not found.");
            return;
        }

        $replier = $DB->get_record('user', ['id' => $reply->userid]);
        if (!$replier) {
            // mtrace("Error: Replier ID not found.");
            return;
        }

        $postauthor = $DB->get_record('user', ['id' => $post->userid]);
        $repliername = fullname($replier);
        $from = $replier;

        // Process mentions in reply
        $mentionedusers = $this->get_mentioned_users($reply);
        
        $mentionedcount = 0;
        foreach ($mentionedusers as $user) {
            if ($this->send_mention_email($user, $reply, $post, $repliername, $from)) {
                $mentionedcount++;
            }
        }
        mtrace("Sent {$mentionedcount} mention notifications for reply.");

        // Find the person being replied to
        $target_user = null;
        if (!empty($reply->parentid)) {
            $parent = $DB->get_record('block_blogpost_replies', ['id' => $reply->parentid]);
            if ($parent) {
                $target_user = $DB->get_record('user', ['id' => $parent->userid]);
            }
        } else {
            // Top level reply, target is post author
            $target_user = $postauthor;
        }

        // Send to target user if different from replier and not already mentioned
        if ($target_user && $target_user->id != $replier->id && !isset($mentionedusers[$target_user->id]) && $target_user->id > 1 && !$target_user->deleted && !$target_user->suspended) {
            $this->send_reply_email($target_user, $reply, $post, $repliername, $from);
        }

        $reply->email_sent = 1;
        $DB->update_record('block_blogpost_replies', $reply);

        // mtrace("Email task for Reply ID {$replyid} completed successfully.");
    }

    private function get_mentioned_users($reply) {
        global $DB;
        $mentioned = [];
        $text = $reply->reply_text;

        if (preg_match_all('/@([a-zA-Z0-9_.-]+)/', $text, $matches)) {
            $usernames = array_unique($matches[1]);
            foreach ($usernames as $username) {
                $user = $DB->get_record_select(
                    'user',
                    'LOWER(username) = :username AND deleted = 0 AND suspended = 0',
                    ['username' => strtolower($username)]
                );
                if ($user && $user->id != $reply->userid) {
                    $mentioned[$user->id] = $user;
                }
            }
        }
        return $mentioned;
    }

    private function send_mention_email($user, $reply, $post, $repliername, $from) {
        global $CFG;
        $subject = s($repliername) . " mentioned you in a reply";
        $messagetext = "Hi {$user->firstname},\n\n{$repliername} mentioned you in a reply to the post: {$post->blog_heading}\n\nView on Moodle: {$CFG->wwwroot}";
        
        $messagehtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee;'>
            <div style='background: #17a2b8; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin:0;'>You Were Mentioned</h2>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <p>Hi " . s($user->firstname) . ",</p>
                <p><strong>" . s($repliername) . "</strong> mentioned you in a reply to the post: <em>" . s($post->blog_heading) . "</em></p>
                <div style='background: #ffffff; padding: 15px; border-left: 4px solid #17a2b8; border-radius: 4px; margin: 15px 0;'>
                    " . nl2br(s($reply->reply_text)) . "
                </div>
                <div style='text-align: center; margin-top: 25px;'>
                    <a href='{$CFG->wwwroot}' style='display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>View on Moodle</a>
                </div>
            </div>
        </div>";
        
        return email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    }

    private function send_reply_email($user, $reply, $post, $repliername, $from) {
        global $CFG;
        $is_nested = !empty($reply->parentid);
        $action = $is_nested ? "comment" : "post";
        $subject = s($repliername) . " replied to your " . $action;
        
        $messagetext = "Hi {$user->firstname},\n\n{$repliername} replied to your {$action} on: {$post->blog_heading}\n\nView on Moodle: {$CFG->wwwroot}";
        
        $messagehtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee;'>
            <div style='background: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2 style='margin:0;'>New " . ucfirst($action) . " Reply</h2>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <p>Hi " . s($user->firstname) . ",</p>
                <p><strong>" . s($repliername) . "</strong> replied to your " . $action . " on: <em>" . s($post->blog_heading) . "</em></p>
                <div style='background: #ffffff; padding: 15px; border-left: 4px solid #007bff; border-radius: 4px; margin: 15px 0;'>
                    " . nl2br(s($reply->reply_text)) . "
                </div>
                <div style='text-align: center; margin-top: 25px;'>
                    <a href='{$CFG->wwwroot}' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>View on Moodle</a>
                </div>
            </div>
        </div>";
                        
        return email_to_user($user, $from, $subject, $messagetext, $messagehtml);
    }
}
