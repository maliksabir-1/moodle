<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

$enabled = get_config('local_point_badges', 'enable_challenges');
echo "Enable challenges config: '";
var_dump($enabled);
echo "'\n";
