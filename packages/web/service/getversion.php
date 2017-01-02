<?php
/**
 * Get version, used for multiple things.
 * The new fog client uses this to tell a client to update.
 * It also is used to return the current running FOG Version.
 * If the client update is disabled, it should return 0.0.0
 * as all clients use a numerical system of which 0.0.0 is below.
 *
 * PHP version 5
 *
 * @category Getversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Get version, used for multiple things.
 * The new fog client uses this to tell a client to update.
 * It also is used to return the current running FOG Version.
 * If the client update is disabled, it should return 0.0.0
 * as all clients use a numerical system of which 0.0.0 is below.
 *
 * @category Getversion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$clientUpdate = (bool)FOGCore::getSetting('FOG_CLIENT_AUTOUPDATE');
if (isset($_REQUEST['client'])) {
    $ver = (
        $clientUpdate ?
        '9.9.99' :
        '0.0.0'
    );
} elseif (isset($_REQUEST['clientver'])) {
    $ver = (
        $clientUpdate ?
        FOG_CLIENT_VERSION :
        '0.0.0'
    );
} elseif (isset($_REQUEST['url'])) {
    $url = $_REQUEST['url'];
    $res = $FOGURLRequests
        ->process($_REQUEST['url']);
    $ver = array_shift($res);
} else {
    $ver = FOG_VERSION;
}
echo $ver;
exit;
