<?php
class TaskQueue extends TaskingElement {
    public function checkIn() {
        try {
            if ($this->imagingTask) {
                if ($this->Task->isMulticast()) {
                    $this->Task
                        ->set('checkinTime',$this->formatTime('','Y-md H:i:s'))
                        ->set('stateID',$this->getCheckedInState());
                    if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                    $MulticastSession = $this->getClass('MulticastSessions',@max($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->Task->get('id')),'msID')));
                    if (!$MulticastSession->isValid()) throw new Exception(_('Invalid Session'));
                    $clientCount = (int)$MulticastSession->get('clients');
                    $MulticastSession->set('clients',++$clientCount);
                    if (!$MulticastSession->save()) throw new Exception(_('Failed to update session'));
                    $CheckedInCount = (int)$this->getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MulticastSession->get('id')));
                    $sessionClientCount = (int)$MulticastSession->get('sessclients');
                    $SessionStateID = ($CheckedInCount == $clientCount || ($sessionClientCount > 0 && $clientCount > 0)) ? 3 : 1;
                    $this->Task->set('stateID',$this->getProgressState());
                    if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                    $MulticastSession->set('stateID',$SessionStateID);
                    if (!$MulticastSession->save()) throw new Exception(_('Failed to update Session'));
                    $this->Host->set('imageID',$MulticastSession->get('image'));
                } else if ($this->Task->isForced()) {
                    $this->HookManager->processEvent('TASK_GROUP',array('StorageGroup'=>&$this->StorageGroup,'Host'=>&$this->Host));
                    $this->StorageNode = $this->Image->getStorageGroup()->getOptimalStorageNode();
                    if ($this->Task->isUpload()) $this->StorageNode = $this->Image->StorageGroup->getMasterStorageNode();
                    $this->HookManager->processEvent('TASK_NODE',array('StorageNode'=>&$this->StorageNode,'Host'=>&$this->Host));
                } else {
                    $totalSlots = $this->StorageGroup->getTotalSupportedClients();
                    $usedSlots = $this->StorageGroup->getUsedSlotCount();
                    $inFront = $this->Task->getInFrontOfHostCount();
                    $groupOpenSlots = $totalSlots - $usedSlots;
                    if ($groupOpenSlots < 1) throw new Exception(sprintf('%s, %s %d %s',_('No open slots'),_('There are'),$inFront,_('before me.')));
                    if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %d %s',_('There are open slots, but'),$inFront,_('before me')));
                    $method = ($this->Task->isUpload() ? 'getMasterStorageNode' : 'getOptimalStorageNode');
                    $this->StorageNode = self::nodeFail($this->Image->getStorageGroup()->$method(),$this->Host->get('id'));
                    foreach ($this->StorageNodes AS $i => &$StorageNode) {
                        if (!$StorageNode->isValid()) continue;
                        if ($StorageNode->get('maxClients') < 1) continue;
                        $nodeAvailableSlots = (int)$StorageNode->get('maxClients') - (int)$StorageNode->getUsedSlotCount();
                        if ($nodeAvailableSlots < 1) continue;
                        if (!isset($tmpStorageNode)) {
                            $tmpStorageNode = self::nodeFail($StorageNode,$this->Host->get('id'));
                            continue;
                        }
                        if ($StorageNode->getClientLoad() < $tmpStorageNode->getClientLoad()) $tmpStorageNode = self::nodeFail($StorageNode,$this->Host->get('id'));
                        unset($StorageNode);
                    }
                }
                if ($tmpStorageNode instanceof StorageNode && $tmpStorageNode->get('id') != $this->StorageNode->get('id')) $this->StorageNode = $tmpStorageNode;
                if (!$this->StorageNode->isValid()) throw new Exception(_('Unable to find a auitable Storage Node for imaging!'));
                $this->Task
                    ->set('NFSMemberID',$this->StorageNode->get('id'));
                if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
            }
            $this->Task->set('stateID',$this->getProgressState());
            if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
            if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
            $this->EventManager->notify('HOST_CHECKIN',array('Host'=>&$this->Host));
            echo '##@GO';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
