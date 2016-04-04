<?php
header('Strict-Transport-Security: "max-age=15768000"');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('X-Robots-Tag: none');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-cache');
require_once('text.php');
require_once('init.php');
while (ob_get_level()) ob_end_clean();
ob_start(array('Initiator','sanitize_output'));
