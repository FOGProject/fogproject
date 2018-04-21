<?php
/**
 * Used for the ou plugin and only checks if it is enabled
 * or not.
 *
 * PHP version 5
 *
 * @category OUcheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Used for the OU plugin and only checks if it is enabled
 * or not.
 *
 * @category OUcheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
if (in_array('ou', FOGCore::$pluginsinstalled)) {
    echo '##';
}
exit;
