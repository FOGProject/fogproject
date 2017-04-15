<?php
/**
 * Presents Hardware/Software information of the server.
 *
 * PHP version 5
 *
 * @category HardwareInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents Hardware/Software information of the server.
 *
 * @category HardwareInfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
$hwinfo = FOGCore::getHWInfo();
foreach ((array)$hwinfo as $index => &$val) {
    echo "$val\n";
    unset($val);
}
