<?php
/**
 * Green fog script
 *
 * PHP version 5
 *
 * @category GreenFog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Green fog script
 *
 * @category GreenFog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
new GF(
    true,
    false,
    false,
    false,
    isset($_REQUEST['newService'])
);
