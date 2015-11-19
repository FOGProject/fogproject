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
        return array_sum((array)$this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes')),'maxClients'));
    }
    public function getMasterStorageNode() {
        $masternode = $this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes'),'isMaster'=>1,'isEnabled'=>1),'id');
        $masternode = array_shift($masternode);
        if (!count($masternode)) $masternode = @min($this->get('enablednodes'));
        if (!count($masternode)) $masternode = @min($this->getSubObjectIDs('StorageNode',array('isEnabled'=>1),'id'));
        return $this->getClass('StorageNode',$masternode);
    }
    public function getOptimalStorageNode() {
        $winner = null;
        foreach ((array)$this->get('enablednodes') AS $i => &$StorageNode) {
            if ($this->getClass('StorageNode',$StorageNode)->get('maxClients') > 0) {
                if ($winner == null) $winner = $this->getClass('StorageNode',$StorageNode);
                else if ($this->getClass('StorageNode',$StorageNode)->getClientLoad() < $winner->getClientLoad()) $winner = $this->getClass('StorageNode',$StorageNode);
            }
        }
        unset($StorageNode);
        return $winner;
    }
    public function getUsedSlotCount() {
        return $this->getClass('TaskManager')->count(array(
            'stateID'=>3,
            'typeID'=>explode(',',$this->getSetting('FOG_USED_TASKS')),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
    public function getQueuedSlotCount() {
        return $this->getClass('TaskManager')->count(array(
            'stateID'=>array(-1,0,1,2),
            'typeID'=>array(1,2,8,15,16,17,24),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
}
