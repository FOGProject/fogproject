<?php
/**
 * Used for the location plugin and only checks if it is enabled
 * or not.
 *
 * PHP version 5
 *
 * @category Locationcheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Used for the location plugin and only checks if it is enabled
 * or not.
 *
 * @category Locationcheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
if (in_array('location', FOGCore::$pluginsinstalled)) {
    echo '##';
}
exit;
