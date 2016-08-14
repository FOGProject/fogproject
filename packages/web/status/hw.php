<?php
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
$hwinfo = $FOGCore->getHWInfo();
ob_start();
array_walk($hwinfo,function(&$val,&$index) {
    echo "$val\n";
    unset($val);
});
die(ob_end_flush());
