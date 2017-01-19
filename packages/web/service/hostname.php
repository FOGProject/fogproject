<?php
/**
 * This is used by the client to determine
 * domain joining and changing hostname.
 *
 * PHP version 5
 *
 * @category Hostname
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * This is used by the client to determine
 * domain joining and changing hostname.
 *
 * @category Hostname
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new HostnameChanger(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
