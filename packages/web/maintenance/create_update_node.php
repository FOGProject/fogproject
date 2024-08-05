<?php
/**
 * Creates or updates nodes.
 *
 * PHP version 5
 *
 * @category Create_Update_Node
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Creates or updates nodes.
 *
 * PHP version 5
 *
 * @category Create_Update_Node
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
foreach ((array)$_POST as $key => &$val) {
    if (!isset($val)) {
        continue;
    }
    $stripped[$key] = trim(
        base64_decode(filter_input(INPUT_POST, $key))
    );
    unset($val);
}
if (!isset($_POST['fogverified'])) {
    return;
}
$name = $ip = $stripped['ip'];
$path = $stripped['path'];
$ftppath = $stripped['ftppath'];
$sslpath = $stripped['sslpath'];
$snapinpath = $stripped['snapinpath'];
$maxClients = $stripped['maxClients'];
$user = $stripped['user'];
$pass = $stripped['pass'];
$interface = $stripped['interface'];
$bandwidth = $stripped['bandwidth'];
$webroot = $stripped['webroot'];
if (isset($_POST['newNode'])) {
    $exists = FOGCore::getClass('StorageNodeManager')
        ->exists($ip, '', 'ip');
    if ($exists) {
        return;
    }
    FOGCore::getClass('StorageNode')
        ->set('name', $name)
        ->set('path', $path)
        ->set('ftppath', $ftppath)
        ->set('snapinpath', $snapinpath)
        ->set('sslpath', $sslpath)
        ->set('ip', $ip)
        ->set('maxClients', $maxClients)
        ->set('user', $user)
        ->set('pass', $pass)
        ->set('interface', $interface)
        ->set('bandwidth', $bandwidth)
        ->set('webroot', $webroot)
        ->set('isEnabled', '1')
        ->save();
} elseif (isset($_POST['nodePass'])) {
    $ip = base64_decode($ip);
    $user = base64_decode($user);
    $pass = base64_decode($pass);
    Route::listem(
        'storagenode',
        ['ip' => $ip]
    );
    $StorageNodes = json_decode(
        Route::getData()
    );
    foreach ($StorageNodes->data as &$StorageNode) {
        if ($StorageNode->user === trim($user)
            && $StorageNode->pass === trim($pass)
        ) {
            continue;
        }
        self::getClass('StorageNode', $StorageNode->id)
            ->set('user', $user)
            ->set('pass', $pass)
            ->save();
        unset($StorageNode);
    }
}
