<?php
require_once('../commons/base.inc.php');
try {
    // Error checking
    $Host = $FOGCore->getHostItem(false);
    $Task = $Host->get(task);
    if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get(name),$Host->get(mac)));
    $imagingTasks = in_array($Task->get(typeID),array(1,2,8,15,16,17));
    // Get the Storage Group
    $StorageGroup = $Task->getStorageGroup();
    if ($imagingTasks && !$StorageGroup->isValid()) throw new Exception(_('Invalid Storage Group'));
    // Get the node.
    $StorageNodes = $StorageGroup->getStorageNodes();
    if ($imagingTasks && !$StorageNodes) throw new Exception(_('Could not find a Storage Node. Is there one enabled within this Storage Group?'));
    // Cycle through the nodes
    foreach ($StorageNodes AS $StorageNode) {
        // Get the nodes in blame.
        $blamed = $FOGCore->getAllBlamedNodes();
        if ($Task->get(NFSMemberID) && !in_array($Task->get(NFSMemberID),(array)$blamed)) {
            $NodeFailure = $FOGCore->getClass(NodeFailure)
                ->set(storageNodeID,$Task->get(NFSMemberID))
                ->set(taskID,$Task->get(id))
                ->set(hostID,$Host->get(id))
                ->set(groupID,$Task->get(NFSGroupID))
                ->set(failureTime,$FOGCore->nice_date()->format('Y-m-d H:i:s'));
            if ($NodeFailure->save()) $Task->set('stateID',1);
        } else $Task->set(stateID,1);
    }
    if ($Task->save()) print '##';
} catch (Exception $e) {
    print $e->getMessage();
}
