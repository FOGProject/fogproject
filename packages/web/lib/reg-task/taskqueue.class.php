<?php
class TaskQueue extends TaskingElement {
    public function checkIn() {
        try {
            if ($this->imagingTask) {
                $this->Task->getImage()->set('size','')->save();
                if ($this->Task->isMulticast()) {
                    $this->Task
                        ->set('checkinTime',$this->formatTime('','Y-m-d H:i:s'))
                        ->set('stateID',$this->getCheckedInState());
                    if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                    $MulticastSession = self::getClass('MulticastSessions',@max(self::getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->Task->get('id')),'msID')));
                    if (!$MulticastSession->isValid()) throw new Exception(_('Invalid Session'));
                    $clientCount = $MulticastSession->get('clients');
                    $MulticastSession->set('clients',++$clientCount);
                    if (!$MulticastSession->save()) throw new Exception(_('Failed to update session'));
                    $CheckedInCount = self::getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MulticastSession->get('id')));
                    $sessionClientCount = $MulticastSession->get('sessclients');
                    $SessionStateID = ($CheckedInCount == $clientCount || ($sessionClientCount > 0 && $clientCount > 0)) ? 3 : 1;
                    $this->Task->set('stateID',$this->getProgressState());
                    if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                    $MulticastSession->set('stateID',$SessionStateID);
                    if (!$MulticastSession->save()) throw new Exception(_('Failed to update Session'));
                    $this->Host->set('imageID',$MulticastSession->get('image'));
                } else if ($this->Task->isForced()) {
                    self::$HookManager->processEvent('TASK_GROUP',array('StorageGroup'=>&$this->StorageGroup,'Host'=>&$this->Host));
                    $this->StorageNode = $this->Image->getStorageGroup()->getOptimalStorageNode($this->Host->get('imageID'));
                    if ($this->Task->isUpload()) $this->StorageNode = $this->Image->StorageGroup->getMasterStorageNode();
                    self::$HookManager->processEvent('TASK_NODE',array('StorageNode'=>&$this->StorageNode,'Host'=>&$this->Host));
                } else {
                    if (in_array($this->Task->get('stateID'),array_merge((array)$this->getQueuedState(),(array)$this->getCheckedInState()))) {
                        $this->Task
                            ->set('stateID',$this->getCheckedInState());
                        if (!$this->validDate($this->Task->get('checkInTime'))) $this->Task->set('checkInTime',$this->formatTime('now','Y-m-d H:i:s'));
                        $this->Task->save();
                        $this->StorageNode = self::nodeFail(self::getClass('StorageNode',$this->Task->get('NFSMemberID')),$this->Host->get('id'));
                        self::$HookManager->processEvent('HOST_NEW_SETTINGS',array('StorageNode'=>&$this->StorageNode));
                        $totalSlots = $this->StorageNode->get('maxClients');
                        $usedSlots = $this->StorageNode->getUsedSlotCount();
                        $inFront = $this->Task->getInFrontOfHostCount();
                        $groupOpenSlots = $totalSlots - $usedSlots;
                        if ($groupOpenSlots < 1) throw new Exception(sprintf('%s, %s %d %s',_('No open slots'),_('There are'),$inFront,_('before me.')));
                        if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %d %s',_('There are open slots, but'),$inFront,_('before me on this node')));
                    }
                    if (!$this->StorageNode instanceof StorageNode || !$this->StorageNode->isValid()) throw new Exception(_('Unable to find a auitable Storage Node for imaging!'));
                }
                $this->Task
                    ->set('NFSMemberID',$this->StorageNode->get('id'));
                if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
            }
            $this->Task
                ->set('stateID',$this->getProgressState())
                ->set('checkInTime',$this->formatTime('now','Y-m-d H:i:s'));
            if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
            if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
            self::$EventManager->notify('HOST_CHECKIN',array('Host'=>&$this->Host));
            echo '##@GO';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
