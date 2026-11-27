<?php
/**
 * Downloads fog client and utilities files.
 *
 * PHP version 7.4+
 *
 * @category Download
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Downloads fog client and utilities files.
 *
 * @category Download
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * This file loads no bootstrap, so FOGBase::hasRequestParam() is not
 * available. Each test below is the same POST-then-GET check, inline.
 */
/**
 * If new client is clicked, prep variable as the new client MSI.
 */
if (isset($_POST['newclient']) || isset($_GET['newclient'])) {
    $filename = 'FOGService.msi';
}
/**
 * If fog prep is clicked, prep variable as the fogprep file.
 */
if (isset($_POST['fogprep']) || isset($_GET['fogprep'])) {
    $filename = 'FogPrep.zip';
}
/**
 * If fog crypt is clicked, prep variable as the fogcrypt file.
 */
if (isset($_POST['fogcrypt']) || isset($_GET['fogcrypt'])) {
    $filename = 'FOGCrypt.zip';
}
/**
 * If smart installer is clicked, prep variable as smartinstaller file.
 */
if (isset($_POST['smartinstaller']) || isset($_GET['smartinstaller'])) {
    $filename = 'SmartInstaller.exe';
}
/**
 * If the file doesn't exist exit the script.
 */
if (isset($filename) && !file_exists($filename)) {
    exit;
}
/**
 * Only use the base name in the case something else set the filename.
 */
if (isset($filename)) {
    $file = basename($filename);
    /**
     * Prep file download information headers.
     */
    header("X-Sendfile: $filename");
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=$file");
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    /**
     * If we cannot open the file exit the script.
     */
    if (($fh = fopen($file, 'rb')) === false) {
        exit;
    }
    /**
     * Read in the file so we can distribute it.
     * This method is also, essentially, size proof.
     */
    while (feof($fh) === false) {
        /**
         * If we cannot read the line break the loop.
         */
        if (($line = fread($fh, 4092)) === false) {
            break;
        }
        /**
         * Output the line in 4092 bit chunks.
         */
        echo $line;
        /**
         * Ensure it's pushed to the user.
         */
        flush();
    }
    /**
     * Close the opened file.
     */
    fclose($fh);
}
exit;
