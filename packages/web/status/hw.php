<?php
require '../commons/base.inc.php';
header('Content-Type: text/event-stream');
header('Connection: close');
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
$hwinfo = $FOGCore->getHWInfo();
ob_start();
array_walk(
    $hwinfo,
    function(&$val,&$index) {
        echo "$val\n";
        unset($val);
    }
);
ob_end_flush();
