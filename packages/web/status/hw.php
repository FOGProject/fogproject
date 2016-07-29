<?php
require_once '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
$hwinfo = $FOGCore->getHWInfo();
array_walk($hwinfo,function(&$val,&$index) {
    echo "$val\n";
    unset($val);
});
exit;
