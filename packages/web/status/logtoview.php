<?php
$vals = function($reverse) {
    ini_set("auto_detect_line_endings", true);
    $folder = sprintf('/%s/',trim(trim(dirname($_REQUEST['file']),'/')));
    $pattern = sprintf('#^%s$#',$folder);
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2/');
    if (!preg_grep($pattern,$folders)) return _('Invalid Folder');
    $lines = array();
    $line_count = is_numeric(trim($_REQUEST['lines'])) ? trim($_REQUEST['lines']) : 20;
    $block_size = 8192;
    $leftover = "";
    $file = trim(basename($_REQUEST['file']));
    $path = sprintf('%s%s',$folder,$file);
    $fh = fopen($path,'rb');
    if ($fh === false) return _('No data to read');
    fseek($fh, 0, SEEK_END);
    do {
        $can_read = $block_size;
        if (ftell($fh) < $block_size) $can_read = ftell($fh);
        fseek($fh, -$can_read, SEEK_CUR);
        $data = htmlentities(fread($fh,$can_read),ENT_QUOTES,'UTF-8');
        $data .= $leftover;
        fseek($fh, -$can_read, SEEK_CUR);
        $split_data = array_reverse(explode("\n",$data));
        $new_lines = array_slice($split_data, 0, -1);
        $lines = array_merge($lines, $new_lines);
        $leftover = $split_data[count($split_data)-1];
    } while (count($lines) < $line_count && ftell($fh) != 0);
    if (ftell($fh) == 0) $lines[] = $leftover;
    fclose($fh);
    return implode("\n",($reverse ? array_slice($lines,0,$line_count) : array_reverse(array_slice($lines,0,$line_count))));
};
require('../commons/base.inc.php');
$ip = trim($FOGCore->aesdecrypt(htmlentities($_REQUEST['ip'],ENT_QUOTES,'UTF-8')));
if (filter_var($ip,FILTER_VALIDATE_IP) === false) {
    echo json_encode(_('IP Passed is incorrect'));
} else {
    $pat = sprintf('#%s#',$ip);
    if (preg_match($pat,$_SERVER['HTTP_HOST'])) echo json_encode($vals(intval($_REQUEST['reverse'])));
    else {
        $url = sprintf('http://%s/fog/status/logtoview.php',$ip);
        $response = $FOGURLRequests->process($url,'POST',array('ip'=>htmlentities($_REQUEST['ip'],ENT_QUOTES,'UTF-8'),'file'=>htmlentities($_REQUEST['file'],ENT_QUOTES,'UTF-8'),'lines'=>htmlentities($_REQUEST['lines'],ENT_QUOTES,'UTF-8'),'reverse'=>intval($_REQUEST['reverse'])));
        echo array_shift($response);
    }
}
exit;
