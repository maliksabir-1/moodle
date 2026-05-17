<?php
defined('MOODLE_INTERNAL') || die();

echo $OUTPUT->header();
echo '<div class="container-fluid"><div class="row">';
echo '<div class="col-9">' . $OUTPUT->main_content() . '</div>';
echo '<div class="col-3">' . $OUTPUT->blocks('side-pre') . '</div>';
echo '</div></div>';
echo $OUTPUT->footer();