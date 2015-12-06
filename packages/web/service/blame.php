<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    $Task = $Host->get('task');
    if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'),$Host->get('mac')));
    $imagingTasks = in_array($Task->get('typeID'),array(1,2,8,15,16,17));
    $StorageGroup = $Task->getStorageGroup();
    if ($imagingTasks && !$StorageGroup->isValid()) throw new Exception(_('Invalid Storage Group'));
    $StorageNodes = $FOGCore->getClass('StorageNodeManager')->find(array('id'=>$StorageGroup->get('enablednodes')));
    if ($imagingTasks && !$StorageNodes) throw new Exception(_('Could not find a Storage Node. Is there one enabled within this Storage Group?'));
    foreach ($StorageNodes AS $StorageNode) {
        $blamed = $FOGCore->getAllBlamedNodes();
        if ($Task->get('NFSMemberID') && !in_array($Task->get('NFSMemberID'),(array)$blamed)) {
            $NodeFailure = $FOGCore->getClass('NodeFailure')
                ->set('storageNodeID',$Task->get('NFSMemberID'))
                ->set('taskID',$Task->get('id'))
                ->set('hostID',$Host->get('id'))
                ->set('groupID',$Task->get('NFSGroupID'))
                ->set('failureTime',$FOGCore->nice_date('+5 minutes')->format('Y-m-d H:i:s'));
            if ($NodeFailure->save()) $Task->set('stateID',$FOGCore->getQueuedState());
        } else $Task->set('stateID',$FOGCore->getQueuedState());
    }
    if ($Task->save()) echo '##';
} catch (Exception $e) {
    echo $e->getMessage();
}
