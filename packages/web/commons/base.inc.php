<?php
header('Connection: close');
header('X-Frame-Options: sameorigin');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000');
header('Connection: close');
require('text.php');
require('init.php');
ob_start(array('Initiator','sanitize_output'));
