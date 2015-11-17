<?php
$vals = function() {
    ini_set("auto_detect_line_endings", true);
    $folder = sprintf('/%s/',trim(trim(dirname($_REQUEST['file']),'/')));
    $pattern = sprintf('#^%s$#',$folder);
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2');
    if (!preg_grep($pattern,$folders)) return _('Invalid Folder');
    $lines = array();
    $line_count = is_numeric(trim($_REQUEST['lines'])) ? trim($_REQUEST['lines']) : 20;
    $block_size = 4096;
    $leftover = "";
    $file = trim(basename($_REQUEST['file']));
    $path = sprintf('%s%s',$folder,$file);
    $fh = fopen($path,'rb');
    if ($fh === false) return _('No data to read');
    fseek($fh, 0, SEEK_END);
    do {
        $can_read = $block_size;
        if (ftell($fh) < $block_size) $can_read = ftell($fh);
        fseek($fh, -$can_reed, SEEK_CUR);
        $data = fread($fh,$can_read);
        $data .= $leftover;
        fseek($fh, -$can_read, SEEK_CUR);
        $split_data = array_reverse(explode("\n",$data));
        $new_lines = array_slice($split_data, 0, -1);
        $lines = array_merge($lines, $new_lines);
        $leftover = $split_data[count($split_data)-1];
    } while (count($lines) < $line_count && ftell($fh) != 0);
    if (ftell($fh) == 0) $lines[] = $leftover;
    fclose($fh);
    return implode("\n",array_slice($lines,0,$line_count));
};
require('../commons/base.inc.php');
if (!$currentUser->isValid()) {
    echo json_encode(_('Must be logged in to view'));
    exit;
}
$ip = trim($FOGCore->aesdecrypt($_REQUEST['ip']));
if (filter_var($ip,FILTER_VALIDATE_IP) === false) {
    echo json_encode(_('IP Passed is incorrect'));
} else {
    $pat = sprintf('#%s#',$ip);
    if (preg_match($pat,$ip)) echo json_encode($vals());
    else {
        $url = sprintf('http://%s/fog/status/logtoview.php?ip=%s&file=%s',$ip,$_REQUEST['ip'],$_REQUEST['file']);
        $data = $FOGCore->FOGURLRequests($url,'GET');
        echo json_encode(array_shift($data));
    }
}
exit;
