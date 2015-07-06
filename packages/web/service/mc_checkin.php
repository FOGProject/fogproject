<?php
require_once('../commons/base.inc.php');
try {
    // Get the Host
    $Host = $FOGCore->getHostItem(false);
    // Get the task
    $Task = $Host->get(task);
    if (!$Task->isValid()) throw new Exception( sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get(name), $MACAddress) );
    if ($Task->get(typeID) == 8) {
        // Get the current Multicast Session
        $MulticastAssociation = current($FOGCore->getClass(MulticastSessionsAssociationManager)->find(array('taskID' => $Task->get(id))));
        $MultiSess = new MulticastSessions($MulticastAssociation->get(msID));
    }
    if ($Task->get(stateID) == 1) {
        // Check In Task for Host
        $Task->set(stateID,2)->set(checkInTime,$FOGCore->nice_date()->format('Y-m-d H:i:s'))->save();
        // If the state is queued, meaning the client has checked in increment clients
        $Task->get(typeID) == 8 ? $MultiSess->set(clients,$MultiSess->get(clients)+1)->save() : null;
    }
    // Get the count of total associations.
    $Task->get(typeID) == 8 ? $MSAs = $FOGCore->getClass(MulticastSessionsAssociationManager)->count(array('msID' => $MultiSess->get(id))) : null;
    // Set the task state for this host as in-progress.
    $Task->set(stateID,3);
    if ($Task->get(typeID) == 8) {
        // If client count is equal, place session task in-progress as it will likely start soon.
        if ($MSAs == $MultiSess->get(clients)|| ($MultiSess->get(sessclients) > 0 && $MultiSess->get(clients) > 0)) $MultiSess->set(stateID,3);
        else $MultiSess->set(stateID,1);
    }
    // Save the info.
    if ($Task->save() && ($Task->get(typeID) == 8 ? $MultiSess->save() : true)) {
        if ($MultiSess && $MultiSess->isValid()) $Host->set(imageID,$MultiSess->get(image));
        // Log it
        $id = $FOGCore->getClass(ImagingLogManager)->find(array('hostID' => $Host->get(id),'type' => $_REQUEST[type],'complete' => '0000-00-00 00:00:00'),'','','','','','','id');
        $FOGCore->getClass(ImagingLog,@max($id))
            ->set(hostID,$Host->get(id))
            ->set(start,$FOGCore->nice_date()->format('Y-m-d H:i:s'))
            ->set(image,$Task->getImage()->get(name))
            ->set(type,$_REQUEST[type])
            ->save();
        $FOGCore->getClass(TaskLog)
            ->set(taskID,$Task->get(id))
            ->set(taskStateID,$Task->get(stateID))
            ->set(createdTime,$Task->get(createdTime))
            ->set(createdBy,$Task->get(createdBy))
            ->save();
        print '##@GO';
    }
} catch (Exception $e) {
    print $e->getMessage();
}
