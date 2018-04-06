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
    $_POST[$key] = trim(
        base64_decode($val)
    );
    unset($val);
}
if (!isset($_POST['fogverified'])) {
    return;
}
if (isset($_POST['newNode'])) {
    $exists = FOGCore::getClass('StorageNodeManager')
        ->exists($_POST['ip'], '', 'ip');
    if ($exists) {
        return;
    }
    FOGCore::getClass('StorageNode')
        ->set('name', trim($_POST['ip']))
        ->set('path', trim($_POST['path']))
        ->set('ftppath', trim($_POST['ftppath']))
        ->set('snapinpath', trim($_POST['snapinpath']))
        ->set('sslpath', trim($_POST['sslpath']))
        ->set('ip', trim($_POST['ip']))
        ->set('maxClients', trim($_POST['maxClients']))
        ->set('user', trim($_POST['user']))
        ->set('pass', trim($_POST['pass']))
        ->set('interface', trim($_POST['interface']))
        ->set('bandwidth', trim($_POST['bandwidth']))
        ->set('webroot', trim($_POST['webroot']))
        ->set('isEnabled', '1')
        ->save();
} elseif (isset($_POST['nodePass'])) {
    $ip = filter_input(INPUT_POST, 'ip');
    $user = filter_input(INPUT_POST, 'user');
    $pass = filter_input(INPUT_POST, 'pass');
    Route::listem(
        'storagenode',
        ['ngmHostname' => $ip]
    );
    $StorageNodes = json_decode(
        Route::getData()
    );
    foreach ($StorageNodes->data as &$StorageNode) {
        if ($StorageNode->pass === trim($pass)) {
            continue;
        }
        self::getClass('StorageNode', $StorageNode->id)
            ->set('user', $user)
            ->set('pass', $pass)
            ->save();
        unset($StorageNode);
    }
}
