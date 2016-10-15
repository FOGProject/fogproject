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
$count = FOGCore::getClass('PluginManager')
    ->count(
        array(
            'installed' => 1,
            'state' => 1,
            'name' => 'location',
        )
    );
if ($count > 0) {
    echo '##';
}
exit;
