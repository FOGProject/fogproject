<?php
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
$kernelvers = function($kernel) {
    $basepath = escapeshellarg(preg_replace('#\\|/#','',sprintf('%s/service/ipxe/%s',BASEPATH,$kernel)));
    return exec(sprintf('strings %s | grep -A1 "Undefined video mode number:" | tail -1 | awk \'{print $1}\'',$basepath));
};
ob_start();
printf("bzImage Version: %s\n",$kernelvers('bzImage'));
printf("bzImage32 Version: %s\n",$kernelvers('bzImage32'));
die(ob_end_flush());
