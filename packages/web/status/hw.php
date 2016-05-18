<?php
ob_start();
require_once('../commons/base.inc.php');
header('Content-Type: text/event-stream');
header('Connection: close');
$hwinfo = $FOGCore->getHWInfo();
@array_walk($hwinfo,function(&$val,&$index) {
    echo "$val\n";
    unset($val);
});
flush();
ob_flush();
ob_end_flush();
exit;
