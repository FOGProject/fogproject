<?php
class TaskQueue extends TaskingElement {
    public function checkIn() {
        if ($this->imagingTask) {
            if ($this->Task->getTaskType()->isMulticast()) {
                $this->Task
                    ->set('checkinTime',$this->formatTime('','Y-md H:i:s'))
                    ->set('stateID',2);
                if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                $MulticastSession = $this->getClass('MulticastSessions',@max($this->getSubObjectIDs('MulticastSessionsAssociation',array('taskID'=>$this->Task->get('id')),'msID')));
                if (!$MulticastSession->isValid()) throw new Exception(_('Invalid Session'));
                $clientCount = (int)$MulticastSession->get('clients');
                $MulticastSession->set('clients',++$clientCount);
                if (!$MulticastSession->save()) throw new Exception(_('Failed to update session'));
                $CheckedInCount = (int)$this->getClass('MulticastSessionsAssociationManager')->count(array('msID'=>$MulticastSession->get('id')));
                $sessionClientCount = (int)$MulticastSession->get('sessclients');
                $SessionStateID = ($CheckedInCount == $clientCount || ($sessionClientCount > 0 && $clientCount > 0)) ? 3 : 1;
                $this->Task->set('stateID',3);
                if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                $MulticastSession->set('stateID',$SessionStateID);
                if (!$MulticastSession->save()) throw new Exception(_('Failed to update Session'));
                $this->Host->set('imageID',$MulticastSession->get('image'));
            } else if ($this->Task->isForced()) {
                $this->HookManager->processEvent('TASK_GROUP',array('StorageGroup'=>&$this->StorageGroup,'Host'=>&$this->Host));
                $this->StorageNode = $this->Image->getStorageGroup()->getOptimalStorageNode();
                $this->HookManager->processEvent('TASK_NODE',array('StorageNode'=>&$this->StorageNode,'Host'=>&$this->Host));
                if ($this->Task->isUpload()) $this->StorageNode = $this->StorageGroup->getMasterStorageNode();
            } else {
                $totalSlots = $this->StorageGroup->getTotalSupportedClients();
                $usedSlots = $this->StorageGroup->getUsedSlotCount();
                $inFront = $this->Task->getInFrontOfHostCount();
                $groupOpenSlots = $totalSlots - $usedSlots;
                if ($groupOpenSlots <= 0) throw new Exception(sprintf('%s %s %s %s',_('Waiting on'),$inFront,_('other'),$inFront > 1 ? _('clients') : _('client')));
                if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %s %s %s',_('Open slots but there are'),$inFront,_('that are queued before me')));
                $this->StorageNode = self::nodeFail($this->Image->getStorageGroup()->getOptimalStorageNode(),$this->Host->get('id'));
                foreach ($this->StorageNodes AS $i => &$StorageNode) {
                    if ($StorageNode->get('maxClients') < 1) continue;
                    $nodeAvailableSlots = $StorageNode->get('maxClients') - $StorageNode->getUsedSlotCount();
                    if ($nodeAvailableSlots > 0) {
                        if (!isset($this->StorageNode)) {
                            $this->StorageNode = self::nodeFail($StorageNode,$this->Host->get('id'));
                            continue;
                        }
                        if ($StorageNode->getClientLoad() < $this->StorageNode->getClientLoad()) $this->StorageNode = self::nodeFail($StorageNode,$this->Host->get('id'));
                    }
                }
            }
            if (!$this->StorageNode->isValid()) throw new Exception(_('Unable to find a auitable Storage Node for imaging!'));
            $this->Task
                ->set('NFSMemberID',$this->StorageNode->get('id'));
            if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
        }
        $this->Task->set('stateID',3);
        if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
        if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
        $this->EventManager->notify('HOST_CHECKIN',array('Host'=>&$this->Host));
        echo '##@GO';
    }
}
