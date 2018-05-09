<?php
/**
 * Get's size of file passed.
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
 * Get's size of file passed.
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
ignore_user_abort(true);
set_time_limit(0);
$file = filter_input(
    INPUT_POST,
    'file'
);
$file = base64_decode($file);
if (!file_exists($file)) {
    return 0;
}
echo FOGCore::getFilesize($file);
exit;
