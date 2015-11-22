<?php
if (isset($_REQUEST['legclient'])) {
	$filename = 'FogService.zip';
} else if (isset($_REQUEST['newclient'])) {
	$filename = 'FOGService.msi';
} else if (isset($_REQUEST['fogprep'])) {
	$filename = 'FogPrep.zip';
} else if (isset($_REQUEST['fogcrypt'])) {
	$filename = 'FOGCrypt.zip';
}
if (file_exists($filename)) {
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
    if (false !== ($handle = fopen($filename,'rb'))) {
        while (!feof($handle)) echo fread($handle,4*1024*1024);
    }
	exit;
}
