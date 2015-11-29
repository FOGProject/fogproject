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
            self::checkTasking($this->Task,$this->Host->get('name'),$this->Host->get('mac'));
            $this->imagingTask = in_array($this->Task->get('typeID'),array(1,2,8,15,16,17,24));
            $this->StorageGroup = $this->Task->getStorageGroup();
            if ($this->imagingTask) {
                $this->StorageNode = $this->Task->isUpload() || $this->Task->isMulticast() ? $this->StorageGroup->getMasterStorageNode() : $this->StorageGroup->getOptimalStorageNode();
                $this->HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this->Host,'StorageNode'=>&$this->StorageNode,'StorageGroup'=>&$this->StorageGroup));
                self::checkStorageGroup($this->StorageGroup);
                self::checkStorageNodes($this->StorageGroup);
                $this->Image = $this->Task->getImage();
                $this->StorageNodes = $this->getClass('StorageNodeManager')->find(array('id'=>$this->StorageGroup->get('enablednodes')));
                $this->Host->set('sec_tok',null)->set('pub_key',null)->save();
                if ($this->Task->isUpload() || $this->Task->isMulticast()) $this->StorageNode = $this->Image->getStorageGroup()->getMasterStorageNode();
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
    protected static function nodeFail($StorageNode,$Host) {
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
        if ($checkin === true) return $this->getClass('ImagingLog',@max($this->getSubObjectIDs('ImagingLog',array('hostID'=>$this->Host->get('id'),'type'=>$_REQUEST['type'],'complete'=>'0000-00-00 00:00:00'))))
            ->set('hostID',$this->Host->get('id'))
            ->set('start',$this->formatTime('','Y-m-d H:i:s'))
            ->set('image',$this->Image->get('name'))
            ->set('type',$_REQUEST['type'])
            ->save();
        return $this->getClass('ImagingLog',@max($this->getSubObjectIDs('ImagingLog',array('hostID'=>$this->Host->get('id')))))
            ->set('finish',$this->formatTime('','Y-m-d H:i:s'))
            ->save();
    }
}
