<?php
abstract class TaskingElement extends FOGBase {
    protected $Host;
    protected $Task;
    protected $Image;
    protected $StorageGroup;
    protected $imagingTask;
    public function __construct() {
        parent::__construct();
        $this->Host = $this->getHostItem(false);
        $this->Task = $this->Host->get(task);
        try {
            // Shouldn't fail but just in case
            self::checkTasking($this->Task,$this->Host->get(name),$this->Host->get(mac));
            $this->imagingTask = in_array($this->Task->get(typeID),array(1,2,8,15,16,17,24));
            $this->StorageGroup = $this->Task->getStorageGroup();
            if ($this->imagingTask) {
                // More error checking
                self::checkStorageGroup($this->StorageGroup);
                self::checkStorageNodes($this->StorageGroup);
                // All okay so far
                $this->Image = $this->Task->getImage();
                $StorageNodes = $this->getClass(StorageNodeManager)->find(array(id=>$this->StorageGroup->getStorageNodes()));
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
        if (!$this->getClass(StorageNodeManager)->count(array(id=>$StorageGroup->getStorageNodes()))) throw new Exception(_('Could not find a Storage Node, is there one enabled within this group?'));
    }
    protected function checkIn() {
        if ($this->Task->getTaskType()->isMulticast()) {
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
            if (!$this->TaskLog()) throw new Exception(_('Failed to update/create task log'));
            if (!$this->ImageLog(true)) throw new Exception(_('Failed to update/create image log'));
        } else if ($this->Task->get(isForced)) {
            if (!$this->Task->set(checkInTime,$this->nice_date()->format('Y-m-d H:i:s'))->set(stateID,3)->save()) throw new Exception(_('Forced Task: Failed to update'));
            $this->StorageNode = $this->StorageGroup->getMasterStorageNode();
        } else if (!$this->Task->get(isForced)) {
            // Queue Checking
            $totalSlots = $this->StorageGroup->getTotalSupportedClients();
            $usedSlots = $this->StorageGroup->getUsedSlotCount();
            $inFront = $this->Task->getInFrontOfHostCount();
            $groupOpenSlots = $totalSlots - $usedSlots;
            if ($groupOpenSlots <= 0) throw new Exception(sprintf('%s %s %s %s',_('Waiting on'),$inFront,_('other'),$inFront > 1 ? _('clients') : _('client')));
            if ($groupOpenSlots <= $inFront) throw new Exception(sprintf('%s %s %s %s',_('Open slots but there are'),$inFront,_('that are queued before me')));
        }
    }
    private function TaskLog() {
        return $this->getClass(TaskLog,$this->Task)
            ->set(taskID,$this->Task->get(id))
            ->set(taskStateID,$this->Task->get(stateID))
            ->set(createdTime,$this->Task->get(createdTime))
            ->set(createdBy,$this->Task->get(createdBy))
            ->save();
    }
    private function ImageLog($checkin = false) {
        if ($checkin === true) return $this->getClass(ImagingLog,@max($this->getClass(ImagingLogManager)->find(array(hostID=>$this->Host->get(id),type=>$_REQUEST[type],complete=>'0000-00-00 00:00:00'),'','','','','','','id')))
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
