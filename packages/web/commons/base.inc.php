<?php
require_once('text.php');
require_once('init.php');
while (ob_get_level()) ob_end_clean();
ob_start(array('Initiator','sanitize_output'));
