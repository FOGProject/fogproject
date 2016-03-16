<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    if (!$Host->isValid()) throw new Exception('#!ih');
    $SnapinJob = $Host->get('snapinjob');
    if (!$Host->get('snapinjob')->isValid()) throw new Exception(_('Invalid Snapin Job'));
    if ($_REQUEST['getSnapnames']) $Snapins = implode(' ',(array)$FOGCore->getSubObjectIDs('Snapin',array('id'=>$FOGCore->getSubObjectIDs('SnapinTask',array('stateID'=>$FOGCore->getQueuedStates(),'jobID'=>$SnapinJob->get('id')),'snapinID')),'name'));
    else if ($_REQUEST['getSnapargs']) $Snapins = implode(' ',(array)$FOGCore->getSubObjectIDs('Snapin',array('id'=>$FOGCore->getSubObjectIDs('SnapinTask',array('stateID'=>$FOGCore->getQueuedStates(),'jobID'=>$SnapinJob->get('id')),'snapinID')),'args'));
    else $Snapins = FOGCore::getClass('SnapinTaskManager')->count(array('stateID'=>$FOGCore->getQueuedStates(),'jobID'=>$SnapinJob->get('id'))) ? 1 : 0;
    echo $Snapins;
} catch (Exception $e) {
    echo $e->getMessage();
}
