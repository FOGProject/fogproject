<?php
require('../commons/base.inc.php');
$kernelvers = function($kernel) {
    $basepath = escapeshellarg(preg_replace('#\\|/#','',sprintf('%s/service/ipxe/%s',BASEPATH,$kernel)));
    return exec("file $basepath | awk '/version/ {print \$9}'");
};
printf("bzImage Version: %s\n",$kernelvers('bzImage'));
printf("bzImage32 Version: %s\n",$kernelvers('bzImage32'));
