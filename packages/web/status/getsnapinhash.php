<?php
require '../commons/base.inc.php';
set_time_limit(0);
if (!isset($_REQUEST['filepath'])) {
    echo '|0';
    exit;
}
$req = $_REQUEST['filepath'];
$dir = dirname($req);
$name = basename($req);
$file = sprintf('%s/%s',$dir,$name);
$fileexist = file_exists($file);
printf('%s|%s',$fileexist ? exec("sha512sum $file | awk '{print $1}'") : '',$fileexist ? FOGCore::getFilesize($file) : 0);
