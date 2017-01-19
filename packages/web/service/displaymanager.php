<?php
/**
 * Display sender for the clients
 *
 * PHP version 5
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Display sender for the clients
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new DisplayManager(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
