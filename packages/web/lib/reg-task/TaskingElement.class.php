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
        try {
            $this->Host = $this->getHostItem(false);
            $this->Task = $this->Host->get('task');
            // Shouldn't fail but just in case
            // Check the tasking, if not valid it will return
            // immediately
            self::checkTasking($this->Task,$this->Host->get('name'),$this->Host->get('mac'));
            // These are all the imaging task IDs
            $this->imagingTask = in_array($this->Task->get('typeID'),array(1,2,8,15,16,17,24));
            // The tasks storage group to operate within
            $this->StorageGroup = $this->Task->getStorageGroup();
            if ($this->imagingTask) {
                // More error checking
                self::checkStorageGroup($this->StorageGroup);
                self::checkStorageNodes($this->StorageGroup);
                // All okay so far
                $this->Image = $this->Task->getImage();
                $this->StorageNodes = $this->getClass('StorageNodeManager')->find(array('id'=>$this->StorageGroup->get('enablednodes')));
                // Clear the hosts data for the client
                $this->Host->set('sec_tok',null)->set('pub_key',null)->save();
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            exit;
        }
    }
    protected static function checkTasking(&$Task,$name,$mac) {
        if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'),$name,$mac));
    }
    protected static function checkStorageGroup(&$StorageGroup) {
        if (!$StorageGroup->isValid()) throw new Exception(_('Invalid Storage Group'));
    }
    protected static function checkStorageNodes(&$StorageGroup) {
        if (!$StorageGroup->get('enablednodes')) throw new Exception(_('Could not find a Storage Node, is there one enabled within this group?'));
    }
    protected static function nodeFail(&$StorageNode,&$Host) {
        if ($StorageNode->getNodeFailure($Host)) {
            $StorageNode = $StorageNode->getClass('StorageNode',0);
            printf('%s %s (%s) %s',_('Storage Node'),$StorageNode->get('name'),$StorageNode->get('ip'),_('is open, but has recently failed for this Host'));
        }
        return $StorageNode;
    }
    protected function TaskLog() {
        return $this->getClass('TaskLog',$this->Task)
            ->set('taskID',$this->Task->get('id'))
            ->set('taskStateID',$this->Task->get('stateID'))
            ->set('createdTime',$this->Task->get('createdTime'))
            ->set('createdBy',$this->Task->get('createdBy'))
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
}
