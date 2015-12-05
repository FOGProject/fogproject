<?php
while (ob_get_level()) ob_end_clean();
header('Strict-Transport-Security: "max-age=15768000"');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('X-Robots-Tag: none');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-cache');
require('text.php');
require('init.php');
