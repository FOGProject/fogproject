<?php
$path = '/'.trim($_SERVER['DOCUMENT_ROOT'],'/').'/client/';
if (isset($_REQUEST['legclient'])) {
	$filename = 'FogService.zip';
} else if (isset($_REQUEST['newclient'])) {
	$filename = 'FOGService.msi';
} else if (isset($_REQUEST['fogprep'])) {
	$filename = 'FogPrep.zip';
} else if (isset($_REQUEST['fogcrypt'])) {
	$filename = 'FOGCrypt.zip';
}
$fullpath = $path.$filename;
$filesize = filesize($fullpath);
header("Content-Disposition: attachment; filename=$filename");
header("Content-Length: $filesize");
header('Content-Type: application/force-download');
header('Connection: close');
readfile($fullpath);
