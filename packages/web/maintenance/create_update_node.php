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

// Restrict to same-machine requests only — installer always calls via the
// server's own IP address, never from a remote host.
$_remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$_serverIp = $_SERVER['SERVER_ADDR'] ?? '';
if ($_remoteIp !== '127.0.0.1'
    && $_remoteIp !== '::1'
    && $_remoteIp !== $_serverIp
) {
    http_response_code(403);
    exit;
}
unset($_remoteIp, $_serverIp);

// Require the fogverified sentinel before doing any work.
if (!isset($_POST['fogverified'])) {
    exit;
}

foreach ((array)$_POST as $key => &$val) {
    if (!isset($val)) {
        continue;
    }
    $stripped[$key] = trim(
        base64_decode(filter_input(INPUT_POST, $key))
    );
    unset($val);
}
$name = $ip = $stripped['ip'] ?? '';
$user = $stripped['user'] ?? '';
$pass = $stripped['pass'] ?? '';
if (isset($_POST['newNode'])) {
    $path       = $stripped['path'] ?? '';
    $ftppath    = $stripped['ftppath'] ?? '';
    $sslpath    = $stripped['sslpath'] ?? '';
    $snapinpath = $stripped['snapinpath'] ?? '';
    $maxClients = $stripped['maxClients'] ?? 10;
    $interface  = $stripped['interface'] ?? '';
    $bandwidth  = $stripped['bandwidth'] ?? 1;
    $webroot    = $stripped['webroot'] ?? '/';
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
    // $ip, $user, $pass are already base64_decoded via the $stripped loop above.
    // Do NOT call base64_decode() again here.
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
        FOGCore::getClass('StorageNode', $StorageNode->id)
            ->set('user', $user)
            ->set('pass', $pass)
            ->save();
        unset($StorageNode);
    }
}
