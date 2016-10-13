<?php
/**
 * Downloads fog client and utilitie files.
 *
 * PHP version 5
 *
 * @category Download
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Downloads fog client and utilitie files.
 *
 * @category Download
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
if (isset($_REQUEST['legclient'])) {
    $filename = 'FogService.zip';
}
if (isset($_REQUEST['newclient'])) {
    $filename = 'FOGService.msi';
}
if (isset($_REQUEST['fogprep'])) {
    $filename = 'FogPrep.zip';
}
if (isset($_REQUEST['fogcrypt'])) {
    $filename = 'FOGCrypt.zip';
}
if (isset($_REQUEST['smartinstaller'])) {
    $filename = 'SmartInstaller.exe';
}
if (!file_exists($filename)) {
    exit;
}
$file = basename($filename);
header("X-Sendfile: $filename");
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename=$file");
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
if (($fh = fopen($file, 'rb')) === false) {
    exit;
}
while (feof($fh) === false) {
    if (($line = fread($fh, 4092)) === false) {
        break;
    }
    echo $line;
    flush();
}
fclose($fh);
exit;
