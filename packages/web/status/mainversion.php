<?php
/**
 * Gets version information
 *
 * PHP version 5
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Gets version information
 *
 * @category Mainversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
$url = 'https://fogproject.org/version/index.php';
$data = array(
    'version' => FOG_VERSION,
);
$res = $FOGURLRequests->process(
    $url,
    'POST',
    $data
);
$res = array_shift($res);
$res = json_encode($res);
echo $res;
exit;
