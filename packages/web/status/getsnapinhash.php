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
die(sprintf('%s|%s',$fileexist ? hash_file('sha512',$file) : '',$fileexist ? FOGCore::getFilesize($file) : 0));
