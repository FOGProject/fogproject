<?php
if (isset($_REQUEST['legclient'])) $filename = 'FogService.zip';
if (isset($_REQUEST['newclient'])) $filename = 'FOGService.msi';
if (isset($_REQUEST['fogprep'])) $filename = 'FogPrep.zip';
if (isset($_REQUEST['fogcrypt'])) $filename = 'FOGCrypt.zip';
if (!file_exists($filename)) exit;
$filesize = filesize($filename);
$file = basename($filename);
header("X-Sendfile: $filename");
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header("Content-Length: $filesize");
header("Content-Disposition: attachment; filename=$file");
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
if (($fh = fopen($file,'rb')) === false) exit;
stream_set_blocking($fh,false);
while (feof($fh) === false) {
    if (($line = fread($fh,4092)) === false) break;
    echo $line;
}
fclose($fh);
exit;
