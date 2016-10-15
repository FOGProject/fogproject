<?php
/**
 * Get's a snapin's hash.
 *
 * PHP version 5
 *
 * @category Getsnapinhash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Get's a snapin's hash.
 *
 * @category Getsnapinhash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
set_time_limit(0);
if (!isset($_REQUEST['filepath'])) {
    echo '|0';
    exit;
}
$req = $_REQUEST['filepath'];
$dir = dirname($req);
$name = basename($req);
$file = sprintf('%s/%s', $dir, $name);
if (!(file_exists($file)
    && is_readable($file))
) {
    echo '|0';
    exit;
}
$ret = sprintf(
    '%s|%s',
    hash_file('sha512', $file),
    FOGCore::getFilesize($file)
);
echo $ret;
exit;
