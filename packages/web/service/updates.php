<?php
/**
 * Legacy client handles module updates
 *
 * PHP version 5
 *
 * @category Updates
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Legacy client handles module updates
 *
 * @category Updates
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new UpdateClient(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
