<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

set_config('enable_challenges', 1, 'local_point_badges');
echo "Enabled configured successfully\n";
