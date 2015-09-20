<?php
abstract class TaskingElement extends FOGBase {
    protected $Host;
    protected $Task;
    protected $Image;
    protected $StorageGroup;
    protected $StorageNode;
    protected $StorageNodes;
    protected $imagingTask;
    public function __construct() {
        parent::__construct();
        $this->Host = $this->getHostItem(false);
        $this->Task = $this->Host->get(task);
        try {
            // Shouldn't fail but just in case
            // Check the tasking, if not valid it will return
            // immediately
            self::checkTasking($this->Task,$this->Host->get(name),$this->Host->get(mac));
            // These are all the imaging task IDs
            $this->imagingTask = in_array($this->Task->get(typeID),array(1,2,8,15,16,17,24));
            // The tasks storage group to operate within
            $this->StorageGroup = $this->Task->getStorageGroup();
            if ($this->imagingTask) {
                // More error checking
                self::checkStorageGroup($this->StorageGroup);
                self::checkStorageNodes($this->StorageGroup);
                // All okay so far
                $this->Image = $this->Task->getImage();
                $this->StorageNodes = $this->getClass(StorageNodeManager)->find(array(id=>$this->StorageGroup->getStorageNodes()));
                // Clear the hosts data for the client
                $this->Host->set(sec_tok,null)->set(pub_key,null)->save();
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    protected static function checkTasking(&$Task,$name,$mac) {
        if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'),$name,$mac));
    }
    protected static function checkStorageGroup(&$StorageGroup) {
        if (!$StorageGroup->isValid()) throw new Exception(_('Invalid Storage Group'));
    }
    protected static function checkStorageNodes(&$StorageGroup) {
        $StorageNodeManager = new StorageNodeManager();
        if (!$StorageNodeManager->count(array(id=>$StorageGroup->getStorageNodes()))) throw new Exception(_('Could not find a Storage Node, is there one enabled within this group?'));
    }
    protected static function nodeFail(&$StorageNode,&$Host) {
        if ($StorageNode->getNodeFailure($Host)) {
            $StorageNode = $this->getClass(StorageNode,0);
            printf('%s %s (%s) %s',_('Storage Node'),$StorageNode->get(name),$StorageNode->get(ip),_('is open, but has recently failed for this Host'));
        }
        return $StorageNode;
    }
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
                            $this->StorageNode = self::nodeFail($StorageNode,$this->Host);
                            continue;
                        }
                        if ($StorageNode->getClientNode() < $this->StorageNode->getClientLoad()) $this->StorageNode = self::nodeFail($StorageNode,$this->Host);
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
    protected function TaskLog() {
        return $this->getClass(TaskLog,$this->Task)
            ->set(taskID,$this->Task->get(id))
            ->set(taskStateID,$this->Task->get(stateID))
            ->set(createdTime,$this->Task->get(createdTime))
            ->set(createdBy,$this->Task->get(createdBy))
            ->save();
    }
    protected function ImageLog($checkin = false) {
        if ($checkin === true) return $this->getClass(ImagingLog,@max($this->getClass(ImagingLogManager)->find(array(hostID=>$this->Host->get(id),type=>$_REQUEST[type],complete=>'0000-00-00 00:00:00'),'','','','','','','id')))
            ->set(hostID,$this->Host->get(id))
            ->set(start,$this->nice_date()->format('Y-m-d H:i:s'))
            ->set(image,$this->Task->getImage()->get(name))
            ->set(type,$_REQUEST[type])
            ->save();
        return $this->getClass(ImagingLog,@max($this->getClass(ImagingLogManager)->find(array(hostID=>$this->Host->get(id)),'','','','','','','id')))
            ->set(finish,$this->nice_date()->format('Y-m-d H:i:s'))
            ->save();
    }
    private function getOptimalNode() {
    }
}
