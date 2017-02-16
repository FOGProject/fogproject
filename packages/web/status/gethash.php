<?php
/**
 * Get's hash of file passed.
 *
 * PHP version 5
 *
 * @category Gethash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Get's hash of file passed.
 *
 * PHP version 5
 *
 * @category Gethash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
if (!is_string($_POST['file'])) {
    return '';
}
$file = Initiator::sanitizeItems($_POST['file']);
if (!file_exists($file)) {
    return '';
}
echo FOGCore::getHash($file);
exit;
