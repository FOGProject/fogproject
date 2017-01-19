<?php
/**
 * Autologout information client
 *
 * PHP version 5
 *
 * @category Autologout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Autologout information client
 *
 * @category Autologout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new Autologout(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
