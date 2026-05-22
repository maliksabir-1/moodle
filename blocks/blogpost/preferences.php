<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

$PAGE->set_url('/blocks/blogpost/preferences.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('notificationpreferences', 'block_blogpost'));
$PAGE->set_heading(get_string('notificationpreferences', 'block_blogpost'));

require_login();

// Include form
require_once($CFG->dirroot . '/blocks/blogpost/classes/form/preferences_form.php');

$mform = new \block_blogpost\form\preferences_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $mform->get_data()) {
    global $DB, $USER;
    
    // Save preferences
    $preference = $DB->get_record('block_blogpost_prefs', ['userid' => $USER->id]);
    
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->email_updates = isset($data->email_updates) ? 1 : 0;
    $record->notify_tags = !empty($data->notify_tags) ? $data->notify_tags : '';
    
    if ($preference) {
        $record->id = $preference->id;
        $DB->update_record('block_blogpost_prefs', $record);
    } else {
        $DB->insert_record('block_blogpost_prefs', $record);
    }
    
    // Show success message
    \core\notification::success(get_string('preferencessaved', 'block_blogpost'));
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notificationpreferences', 'block_blogpost'));
$mform->display();
echo $OUTPUT->footer();