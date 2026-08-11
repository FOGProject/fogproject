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

// Restrict to same-machine requests only. This creates and updates storage
// nodes -- including their credentials -- and the fogverified check below is
// a sentinel the installer sends, not a secret, so on its own it stops
// nothing. The installer always posts from the server's own IP.
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
        ->set('name', trim($_POST['name']))
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
    foreach ((array)FOGCore::getClass('StorageNodeManager')
        ->find(array('ip' => $_POST['ip'])) as &$Node
    ) {
        if (($Node->get('pass') === trim($_POST['pass'])) &&
            ($Node->get('user') === trim($_POST['user']))) {
            continue;
        }
        $Node
            ->set('pass', trim($_POST['pass']))
            ->set('user', trim($_POST['user']))
            ->save();
        unset($Node);
    }
}
