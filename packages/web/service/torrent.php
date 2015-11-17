<?php
require_once('../commons/base.inc.php');
try {
    $torrentFile = sprintf('%s.torrent',basename(htmlentities($_REQUEST['torrent'],ENT_QUOTES,'UTF-8')));
    // Assign the file for sending.
    if (file_exists(rtrim($FOGCore->getSetting(FOG_TORRENTDIR),'/').'/'.$torrentFile)) $torrentFile = (rtrim($FOGCore->getSetting(FOG_TORRENTDIR),'/').'/'.$torrentFile);
    // If it exists and is readable send it!
    if (file_exists($torrentFile) && is_readable($torrentFile)) {
        header('X-Content-Type-Options: nosniff');
        header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Frame-Options: deny');
        header('Cache-Control: no-cache');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Length: ".filesize($torrentFile));
        header("Content-Disposition: attachment; filename=".basename($torrentFile));
        @readfile($torrentFile);
    }
} catch (Exception $e) {
    $Datatosend = $e->getMessage();
}
