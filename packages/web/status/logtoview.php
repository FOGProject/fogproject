<?php
/**
 * Presents the log for viewing
 *
 * PHP version 5
 *
 * @category Logtoview
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the log for viewing
 *
 * @category Logtoview
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
ini_set(
    'auto_detect_line_endings',
    true
);
header('Content-Type: text/event-stream');
$folders = array(
    '/var/log/fog/',
    '/opt/fog/log/',
    '/var/log/httpd/',
    '/var/log/apache2/',
    '/var/log/nginx/',
    '/var/log/php-fpm/',
    '/var/log/php5.6-fpm/',
    '/var/log/php5-fpm/',
    '/var/log/php7.0-fpm/'
);
$HookManager->processEvent('LOG_FOLDERS', array('folders' => &$folders));
$vals = function ($reverse) use ($folders) {
    $dir = dirname($_REQUEST['file']);
    $dir = trim($dir, '/');
    $dir = trim($dir);
    $dir = sprintf(
        '/%s/',
        $dir
    );
    $pattern = sprintf(
        '#^%s$#',
        $dir
    );
    $folder = preg_grep($pattern, $folders);
    if ($folder === false || count($folder) < 1) {
        return _('Invalid Folder');
    }
    $folder = array_shift($folder);
    $file = basename($_REQUEST['file']);
    $file = trim($file);
    $path = sprintf(
        '%s%s',
        $dir,
        $file
    );
    if (!(file_exists($path) && is_readable($path))) {
        return _('File is unreadable or does not exist');
    }
    if (false === ($fh = fopen($path, 'rb'))) {
        return _('Unable to open file for reading');
    }
    $lines = $_REQUEST['lines'];
    if (!(is_numeric($lines) && $lines < 19 && $lines > 1001)) {
        return _('Invalid number of lines');
    }
    $buffer = 4096;
    fseek($fh, -1, SEEK_END);
    if (fread($fh, 1) != "\n") {
        $lines -= 1;
    }
    $output = '';
    $chunk = '';
    while (ftell($fh) > 0 && $lines >= 0) {
        $teller = ftell($fh);
        $seek = min($teller, $buffer);
        fseek($fh, -$seek, SEEK_CUR);
        $chunk = fread($fh, $seek);
        $output = sprintf('%s%s', $chunk, $output);
        fseek($fh, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        $lines -= substr_count($chunk, "\n");
    }
    while ($lines++ < 0) {
        $pos = strpos($output, "\n");
        $output = substr($output, $pos + 1);
    }
    fclose($fh);
    if ($reverse) {
        $outArr = explode("\n", $output);
        $outArr = array_reverse($outArr);
        $output = implode("\n", $outArr);
    }
    return trim($output);
};
$tmpip = $FOGCore->aesdecrypt($_REQUEST['ip']);
$url = trim($tmpip);
$ip = $FOGCore->resolveHostname($url);
if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
    echo json_encode(_('IP Passed is incorrect'));
} else {
    if ($url != $ip) {
        $ip = $url;
    }
    $pat = "#$ip#";
    if (false !== preg_match($pat, $_SERVER['HTTP_HOST'])) {
        $rev = $_REQUEST['reverse'];
        $data = $vals($rev);
        $data = json_encode($data);
        echo $data;
        exit;
    } else {
        $url = sprintf(
            'http://%s/fog/status/logtoview.php',
            $ip
        );
        $url = filter_var($url, FILTER_SANITIZE_URL);
        $response = $FOGURLRequests->process(
            $url,
            'POST',
            array(
                'ip'=>$FOGCore->aesencrypt($ip),
                'file'=>$_REQUEST['file'],
                'lines'=>$_REQUEST['lines'],
                'reverse'=> $_REQUEST['reverse']
            )
        );
        echo array_shift($response);
        exit;
    }
}
