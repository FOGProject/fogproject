<?php
require '../commons/base.inc.php';
if (isset($_POST['newNode'])) {
    if (FOGCore::getClass('StorageNodeManager')->count(array('ip'=>$_POST['ip'])) > 0) return;
    FOGCore::getClass('StorageNode')
        ->set('name',trim($_POST['ip']))
        ->set('ip',trim($_POST['ip']))
        ->set('user',trim($_POST['user']))
        ->set('pass',trim($_POST['pass']))
        ->set('interface',trim($_POST['interface']))
        ->set('description','Auto generated fog nfs group member')
        ->set('isEnabled','1')
        ->save();
}
if (isset($_POST['nodePass'])) {
    $Nodes = FOGCore::getClass('StorageNodeManager')->find(array('ip'=>$_POST['ip']));
    array_walk($Nodes,function(&$Node,&$index) {
        if (!$Node->isValid()) return;
        if ($Node->get('pass') === trim($_POST['pass'])) return;
        $Node
            ->set('pass',trim($_POST['pass']))
            ->set('user',trim($_POST['user']))
            ->save();
    });
}
