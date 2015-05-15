<?php
$path = './client/';
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
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.basename($filename));
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: '.filesize($filename));
	readfile($filename);
	exit;
}
