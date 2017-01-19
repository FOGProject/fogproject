<?php
/**
 * Legacy client uses this to find out
 * if the module checked is usable.
 *
 * PHP version 5
 *
 * @category ServiceModule_Active
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Legacy client uses this to find out
 * if the module checked is usable.
 *
 * @category ServiceModule_Active
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new ServiceModule(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
