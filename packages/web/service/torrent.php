<?php
require_once('../commons/base.inc.php');
try
{
	$torrentFile = ($_REQUEST['torrent'].'.torrent');
	// Assign the file for sending.
	if (file_exists(rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$torrentFile))
		$torrentFile = (rtrim($FOGCore->getSetting('FOG_SNAPINDIR'),'/').'/'.$torrentFile);
	// If it exists and is readable send it!
	if (file_exists($torrentFile) && is_readable($torrentFile))
	{
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Description: File Transfer");
		header("Content-Type: application/octet-stream");
		header("Content-Length: ".filesize($torrentFile));
		header("Content-Disposition: attachment; filename=".basename($torrentFile));
		@readfile($torrentFile);
	}
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
