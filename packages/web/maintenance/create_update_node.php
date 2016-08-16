<?php
require '../commons/base.inc.php';
array_walk($_POST, function (&$val, &$key) {
    if (isset($val)) {
        $val = trim(base64_decode($val));
    }
});
if (!isset($_POST['fogverified'])) {
    return;
}
if (isset($_POST['newNode'])) {
    if (FOGCore::getClass('StorageNodeManager')->count(array('ip'=>$_POST['ip'])) > 0) {
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
    $Nodes = FOGCore::getClass('StorageNodeManager')->find(array('ip'=>$_POST['ip']));
    array_walk($Nodes, function (&$Node, &$index) {
        if (!$Node->isValid()) {
            return;
        }
        if ($Node->get('pass') === trim($_POST['pass'])) {
            return;
        }
        $Node
            ->set('pass', trim($_POST['pass']))
            ->set('user', trim($_POST['user']))
            ->save();
    });
}
