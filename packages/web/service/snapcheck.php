<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    if (!$Host->isValid()) throw new Exception('#!ih');
    $SnapinJob = $Host->get(snapinjob);
    $SnapinTasks = $FOGCore->getClass('SnapinTaskManager')->find(array('stateID'=>array(-1,0,1),'jobID'=>$SnapinJob->get('id')));
    if ($SnapinJob && $SnapinJob->isValid()) {
        if ($_REQUEST['getSnapnames']) {
            foreach($SnapinTasks AS &$SnapinTask) {
                $Snapin = $SnapinTask->getSnapin();
                $SnapinNames[] = $Snapin->get('name');
            }
            unset($SnapinTask);
            $Snapins = implode(' ',(array)$SnapinNames);
        } else if ($_REQUEST['getSnapargs']) {
            foreach((array)$SnapinTasks AS &$SnapinTask) {
                $Snapin = $SnapinTask->getSnapin();
                $SnapinArgs[] = $Snapin->get('args');
            }
            unset($SnapinTask);
            $Snapins = implode(' ',(array)$SnapinArgs);
        } else {
            $SnapinTasks = count($SnapinTasks);
            $Snapins = ($SnapinTasks ? 1 : 0);
        }
    }
    echo $Snapins;
} catch (Exception $e) {
    echo $e->getMessage();
}
