<?php
$vals = function($reverse,$HookManager) {
    ini_set("auto_detect_line_endings", true);
    $folder = sprintf('/%s/',trim(trim(dirname(htmlentities($_REQUEST['file'],ENT_QUOTES,'utf-8')),'/')));
    $pattern = sprintf('#^%s$#',$folder);
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2/');
    $HookManager->processEvent('LOG_FOLDERS',array('folders'=>&$folders));
    if (!preg_grep($pattern,$folders)) return _('Invalid Folder');
    $lines = array();
    $line_count = (int) $_REQUEST['lines'];
    $block_size = 4092;
    $leftover = "";
    $file = trim(basename(htmlentities($_REQUEST['file'],ENT_QUOTES,'utf-8')));
    $path = sprintf('%s%s',$folder,$file);
    $fh = fopen($path,'rb');
    if ($fh === false) return _('No data to read');
    fseek($fh,0,SEEK_END);
    if (fread($fh,1) != "\n") $line_count--;
    while (ftell($fh) > 0 && $line_count >= 0) {
        $can_read = min(ftell($fh),$block_size);
        fseek($fh,-$can_read,SEEK_CUR);
        $chunk = fread($fh,$can_read);
        $split_data = array_reverse(explode("\n",$chunk));
        $new_lines = array_slice($split_data,0,-1);
        $lines = array_merge($lines,$new_lines);
        fseek($fh,-mb_strlen($chunk,'8bit'),SEEK_CUR);
        $line_count -= substr_count($chunk,"\n");
        if ($line_count <= 0) break;
    }
    fclose($fh);
    $lines = array_slice($lines,0,(int)$_REQUEST['lines']);
    if (!$reverse) $lines = array_reverse($lines);
    return implode("\n",$lines);
};
require('../commons/base.inc.php');
$url = trim($FOGCore->aesdecrypt(htmlentities($_REQUEST['ip'],ENT_QUOTES,'utf-8')));
$ip = $FOGCore->resolveHostname($url);
if (filter_var($ip,FILTER_VALIDATE_IP) === false) {
    echo json_encode(_('IP Passed is incorrect'));
} else {
    if ($url != $ip) $ip = $url;
    $pat = sprintf('#%s#',$ip);
    if (preg_match($pat,$_SERVER['HTTP_HOST'])) echo json_encode($vals((int) $_REQUEST['reverse'],$HookManager));
    else {
        $url = sprintf('http://%s/fog/status/logtoview.php',$ip);
        $url = filter_var($url,FILTER_SANITIZE_URL);
        $response = $FOGURLRequests->process($url,'POST',array(
            'ip'=>htmlentities($FOGCore->aesencrypt($ip),ENT_QUOTES,'utf-8'),
            'file'=>htmlentities($_REQUEST['file'],ENT_QUOTES,'utf-8'),
            'lines'=>htmlentities($_REQUEST['lines'],ENT_QUOTES,'utf-8'),
            'reverse'=>(int) $_REQUEST['reverse'])
        );
        echo array_shift($response);
    }
}
exit;
