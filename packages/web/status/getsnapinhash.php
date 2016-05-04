<?php
if (!isset($_REQUEST['filepath'])) {
    echo '|0';
    exit;
}
$req = htmlentities($_REQUEST['filepath'],ENT_QUOTES,'utf-8');
$dir = dirname($req);
$name = basename($req);
$file = sprintf('%s/%s',$dir,$name);
$fileexist = file_exists($file);
$filesize = $hashes = array();
exec("sha512sum $file|awk '{print $1}'",$hashes);
exec("ls -l $file|awk '{print $5}'",$filesize);
printf('%s|%s',$fileexist ? array_shift($hashes) : '',$fileexist ? array_shift($filesize) : 0);
