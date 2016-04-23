<?php
ob_start();
require_once('../commons/base.inc.php');
header('Content-Type: text/event-stream');
$kernelvers = function($kernel) {
    $basepath = escapeshellarg(preg_replace('#\\|/#','',sprintf('%s/service/ipxe/%s',BASEPATH,$kernel)));
    return exec("file $basepath | awk '/version/ {print \$9}'");
};
printf("bzImage Version: %s\n",$kernelvers('bzImage'));
printf("bzImage32 Version: %s\n",$kernelvers('bzImage32'));
header('Connection: close');
flush();
ob_flush();
ob_end_flush();
exit;
