<?php
ob_start();
require_once('../commons/base.inc.php');
header('Content-Type: text/event-stream');
header('Connection: close');
$kernelvers = function($kernel) {
    $basepath = escapeshellarg(preg_replace('#\\|/#','',sprintf('%s/service/ipxe/%s',BASEPATH,$kernel)));
    return exec(sprintf('strings %s | grep -A1 "Undefined video mode number:" | tail -1 | awk \'{print $1}\'',$basepath));
};
printf("bzImage Version: %s\n",$kernelvers('bzImage'));
printf("bzImage32 Version: %s\n",$kernelvers('bzImage32'));
flush();
ob_flush();
ob_end_flush();
exit;
