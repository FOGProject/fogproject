<?php
/**
 * Logtoview handles reading files
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
 * Logtoview handles reading files
 *
 * @category Logtoview
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require_once '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
if (!(isset($_POST['ip'])
    && is_string($_POST['ip']))
) {
    echo json_encode(_('Invalid IP'));
    exit;
}
if (!(isset($_POST['file'])
    && is_string($_POST['file']))
) {
    echo json_encode(_('Invalid File'));
    exit;
}
$file = '';
$lines = '';
/**
 * Returns vals.
 *
 * @param int         $reverse     Log reverse or forward.
 * @param HookManager $HookManager Hook manager item.
 * @param int         $lines       Lines to show.
 * @param string      $file        File to return.
 *
 * @return string
 */
function vals($reverse, $HookManager, $lines, $file)
{
    ini_set("auto_detect_line_endings", true);
    $folder = sprintf(
        '/%s/',
        trim(
            trim(
                dirname($file)
            ),
            '/'
        )
    );
    $pattern = sprintf(
        '#^%s$#',
        $folder
    );
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
    $HookManager->processEvent('LOG_FOLDERS', array('folders'=>&$folders));
    if (!preg_grep($pattern, $folders)) {
        return _('Invalid Folder');
    }
    $file = trim(basename($file));
    $path = sprintf('%s%s', $folder, $file);
    $path = trim($path);
    if (($fh = fopen($path, 'rb')) === false) {
        return _('Unable to open file for reading');
    }
    $buffer = 4096;
    fseek($fh, -1, SEEK_END);
    if (fread($fh, 1) != "\n") {
        $lines -= 1;
    }
    $output = '';
    $chunk = '';
    while (ftell($fh) > 0 && $lines >= 0) {
        $seek = min(ftell($fh), $buffer);
        fseek($fh, -$seek, SEEK_CUR);
        $output = ($chunk = fread($fh, $seek)).$output;
        fseek($fh, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        $lines -= substr_count($chunk, "\n");
    }
    while ($lines++ < 0) {
        $output = substr(
            $output,
            strpos(
                $output,
                "\n"
            )
            + 1
        );
    }
    fclose($fh);
    if ($reverse) {
        $output = implode(
            "\n",
            array_reverse(
                explode(
                    "\n",
                    $output
                )
            )
        );
    }
    return trim($output);
}
if (!(isset($_POST['ip'])
    && is_string($_POST['ip']))
) {
    echo _('Invalid IP');
    exit;
}
if (!(isset($_POST['file'])
    && is_string($_POST['file']))
) {
    echo _('Invalid File');
    exit;
}
if (!(isset($_POST['lines'])
    && is_numeric($_POST['lines']))
) {
    $_POST['lines'] = 20;
}
if (!(isset($_POST['reverse'])
    && is_numeric($_POST['reverse']))
) {
    $_POST['reverse'] = 0;
}
$ip = $_POST['ip'];
$file = sprintf(
    '%s%s%s',
    dirname($_POST['file']),
    DS,
    basename($_POST['file'])
);
$lines = $_POST['lines'];
$reverse = $_POST['reverse'];
$ip = base64_decode($ip);
$ip = FOGCore::resolveHostname($ip);
$ip = trim($ip);
if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
    return print json_encode(_('IP Passed is incorrect'));
}
if (false !== strpos(filter_input(INPUT_SERVER, 'HTTP_HOST'), $ip)) {
    $str = vals(
        $reverse,
        $HookManager,
        $lines,
        $file
    );
    echo json_encode($str);
    exit;
}
$url = sprintf(
    '%s://%s/fog/status/logtoview.php',
    FOGCore::$httpproto,
    $ip
);
$process = array(
    'ip' => base64_encode($ip),
    'file' => $file,
    'lines' => $lines,
    'reverse' => $reverse
);
$response = $FOGURLRequests->process(
    $url,
    'POST',
    $process
);
echo json_decode(
    json_encode(
        array_shift($response)
    ),
    true
);
