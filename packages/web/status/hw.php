<?php
ob_start();
require_once('../commons/base.inc.php');
header('Content-Type: text/event-stream');
array_map(function(&$val) {
    echo "$val\n";
    unset($val);
},(array)$FOGCore->getHWInfo());
header('Connection: close');
flush();
ob_flush();
ob_end_flush();
exit;
