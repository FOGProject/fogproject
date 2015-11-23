<?php
require_once('../commons/base.inc.php');
try {
    $torrentFile = sprintf('%s.torrent',basename(mb_convert_encoding($_REQUEST['torrent'],'UTF-8','UTF-8')));
    $file = sprintf('%s%s%s%s',DIRECTORY_SEPARATOR,trim(str_replace(array('\\','/'),DIRECTORY_SEPARATOR,$FOGCore->getSetting('FOG_TORRENTDIR')),DIRECTORY_SEPARATOR),DIRECTORY_SEPARATOR,basename($torrentFile));
    if (file_exists($file) && is_readable($file)) {
        $filesize = filesize($file);
        $filename = basename($file);
        header("X-Sendfile: $file");
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Content-Length: $filesize");
        header("Content-Disposition: attachment; filename=$filename");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        if (false !== ($handle = fopen($file,'rb'))) {
            while (!feof($handle)) echo fread($handle,4*1024*1024);
        }
        exit;
    }
} catch (Exception $e) {
    $Datatosend = $e->getMessage();
}
