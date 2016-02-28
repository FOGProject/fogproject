<?php
class StorageGroup extends FOGController {
    protected $databaseTable = 'nfsGroups';
    protected $databaseFields = array(
        'id' => 'ngID',
        'name' => 'ngName',
        'description' => 'ngDesc',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    protected $additionalFields = array(
        'allnodes',
        'enablednodes',
    );
    protected function loadAllnodes() {
        if ($this->get('id')) $this->set('allnodes',$this->getSubObjectIDs('StorageNode',array('storageGroupID'=>$this->get('id')),'id'));
    }
    protected function loadEnablednodes() {
        if ($this->get('id')) $this->set('enablednodes',$this->getSubObjectIDs('StorageNode',array('storageGroupID'=>$this->get('id'),'id'=>$this->get('allnodes'),'isEnabled'=>1)));
    }
    public function getTotalSupportedClients() {
        $count = $this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes')),'maxClients','','','','','array_sum');
        $count = array_shift($count);
        return $count;
    }
    public function getMasterStorageNode() {
        $masternode = $this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes'),'isMaster'=>1,'isEnabled'=>1),'id');
        $masternode = array_shift($masternode);
        if (!$masternode > 0) $masternode = @min($this->get('enablednodes'));
        //if (!$masternode > 0) throw New Exception(_('No Storage nodes enabled for this group'));
        return $this->getClass('StorageNode',$masternode);
    }
    public function getOptimalStorageNode() {
        $winner = null;
        foreach ((array)$this->getClass('StorageNodeManager')->find(array('id'=>$this->get('enablednodes'))) AS &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            if ($StorageNode->get('maxClients') < 1) continue;
            if ($winner == null || !$winner->isValid()) {
                $winner = $StorageNode;
                continue;
            }
            if ($StorageNode->getClientLoad() < $winner->getClientLoad()) $winner = $StorageNode;
            unset($StorageNode);
        }
        return $winner;
    }
    public function getUsedSlotCount() {
        return $this->getClass('TaskManager')->count(array(
            'stateID'=>$this->getProgressState(),
            'typeID'=>explode(',',$this->getSetting('FOG_USED_TASKS')),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
    public function getQueuedSlotCount() {
        return $this->getClass('TaskManager')->count(array(
            'stateID'=>$this->getQueuedStates(),
            'typeID'=>array(1,2,8,15,16,17,24),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
}
