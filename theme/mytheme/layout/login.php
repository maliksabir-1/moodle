<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'logourl' => $OUTPUT->get_logo_url(),
    'issignup' => ($PAGE->pagetype === 'login-signup'),
    'loginurl' => new \moodle_url('/login/index.php')
];

echo $OUTPUT->render_from_template('theme_mytheme/login', $templatecontext);
