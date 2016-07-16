<?php
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
if (!isset($_REQUEST['filepath'])) {
    echo '|0';
    exit;
}
function getfilesize($file) {
    $fh = fopen($file,'rb');
    $size = 0;
    $char = '';
    fseek($fh, 0, SEEK_SET);
    $count = 0;
    while (true) {
        fseek($fh, 1048576, SEEK_CUR);
        if (($char = fgetc($fh)) !== false) {
            $count++;
        } else {
            fseek($fh, -1048576, SEEK_CUR);
            break;
        }
    }
    $size = bcmul('1048577',$count);
    $fine = 0;
    while (false !== ($char = fgetc($fh))) {
        $fine++;
    }
    $size = bcadd($size,$fine);
    fclose($fh);
    return $size;
}
$req = $_REQUEST['filepath'];
$dir = dirname($req);
$name = basename($req);
$file = sprintf('%s/%s',$dir,$name);
$fileexist = file_exists($file);
if ($fileexist) {
    $filesize = getfilesize($file);
    $filehash = exec("sha512sum $file|awk '{print $1}'");
} else {
    $filesize = 0;
    $filehash = '';
}
printf('%s|%s',$filehash,$filesize);
exit;
