<?php
class TaskQueue extends TaskingElement {
    public function checkIn() {
        if ($this->imagingTask) {
            if ($this->Task->getTaskType()->isMulticast()) { // If multicast task checkin
                if ((int)$this->Task->get(stateID) === 1 && !$this->Task->set(checkInTime,$this->nice_date()->format('Y-m-d H:i:s'))->set(stateID,2)->save()) throw new Exception(_('Failed to update task'));
                $MulticastSession = $this->getClass(MulticastSessions,@max($this->getClass(MulticastSessionsAssociationManager)->find(array(taskID=>$this->Task->get(id)),'','','','','','','msID')));
                $clientCount = (int)$MulticastSession->get(clients);
                $MulticastSession->set(clients,++$clientCount)->save();
                $CheckedInCount = (int)$this->getClass(MulticastSessionsAssociationManager)->count(array(msID=>$MulticastSession->get(id)));
                $this->Task->set(stateID,3);
                $sessionClientCount = (int)$MulticastSession->get(sessclients);
                $MulticastSession->set(stateID,1);
                if ($CheckedInCount == $clientCount || ($sessionClientCount > 0 && $clientCount > 0)) $MulticastSession->set(stateID,3);
                if (!$this->Task->save()) throw new Exception(_('Failed to update task'));
                if (!$MulticastSession->save()) throw new Exception(_('Failed to update Session Task'));
                $this->Host->set(imageID,$MulticastSession->get(image));
            } else if ($this->Task->get(isForced)) { // If the task is forced have it start
                if (!$this->Task->set(checkInTime,$this->nice_date()->format('Y-m-d H:i:s'))->set(stateID,3)->save()) throw new Exception(_('Forced Task: Failed to update'));
                $this->StorageNode = $this->StorageGroup->getMasterStorageNode();
            } else if (!$this->Task->get(isForced)) { // If the task was not forced
                // Queue Checking
                $totalSlots = $this->StorageGroup->getTotalSupportedClients();
                $usedSlots = $this->StorageGroup->getUsedSlotCount();
                $inFront = $this->Task->getInFrontOfHostCount();
                $groupOpenSlots = $totalSlots - $usedSlots;
                if ($groupOpenSlots <= 0) throw new Exception(sprintf('%s %s %s %s',_('Waiting on'),$inFront,_('other'),$inFront > 1 ? _('clients') : _('client')));
                if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %s %s %s',_('Open slots but there are'),$inFront,_('that are queued before me')));
                foreach ($this->StorageNodes AS $i => &$StorageNode) {
                    // No need to check this node, it can't do anything anyway
                    if ($StorageNode->get(maxClients) < 1) continue;
                    // Get the counts
                    $nodeAvailableSlots = $StorageNode->get(maxClients) - $StorageNode->getUsedSlotCount();
                    if ($nodeAvailableSlots > 0) {
                        if (!isset($this->StorageNode)) {
                            $this->StorageNode = self::nodeFail($StorageNode,$this->Host->get('id'));
                            continue;
                        }
                        if ($StorageNode->getClientLoad() < $this->StorageNode->getClientLoad()) $this->StorageNode = self::nodeFail($StorageNode,$this->Host);
                    }
                }
            }
            if (!$this->StorageNode->isValid()) throw new Exception(_('Unable to find a auitable Storage Node for imaging!'));
            // Everything has passed
            $this->Task
                ->set(NFSMemberID,$this->StorageNode->get(id));
            if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
        }
        $this->Task->set(stateID,3);
        if (!$this->Task->save()) throw new Exception(_('Failed to update Task'));
        if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
        $this->EventManager->notify(HOST_CHECKIN,array(Host=>&$this->Host));
        echo '##@GO';
    }
}
