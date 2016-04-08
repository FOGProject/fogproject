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
        return array_sum($this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes')),'maxClients'));
    }
    public function getMasterStorageNode() {
        $masternode = $this->getSubObjectIDs('StorageNode',array('id'=>$this->get('enablednodes'),'isMaster'=>1,'isEnabled'=>1),'id');
        $masternode = array_shift($masternode);
        if (!$masternode > 0) $masternode = @min($this->get('enablednodes'));
        return static::getClass('StorageNode',$masternode);
    }
    public function getOptimalStorageNode($image) {
        $this->winner = null;
        array_map(function(&$StorageNode) use ($image) {
            if (!$StorageNode->isValid()) return;
            if (!in_array($image,$StorageNode->get('images'))) return;
            if ($StorageNode->get('maxClients') < 1) return;
            if ($this->winner == null || !$this->winner->isValid()) {
                $this->winner = $StorageNode;
                return;
            }
            if ($StorageNode->getClientLoad() < $this->winner->getClientLoad()) $this->winner = $StorageNode;
            unset($StorageNode);
        },(array)static::getClass('StorageNodeManager')->find(array('id'=>$this->get('enablednodes'))));
        return $this->winner;
    }
    public function getUsedSlotCount() {
        return static::getClass('TaskManager')->count(array(
            'stateID'=>$this->getProgressState(),
            'typeID'=>explode(',',$this->getSetting('FOG_USED_TASKS')),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
    public function getQueuedSlotCount() {
        return static::getClass('TaskManager')->count(array(
            'stateID'=>$this->getQueuedStates(),
            'typeID'=>array(1,2,8,15,16,17,24),
            'NFSGroupID'=>$this->get('id'),
        ));
    }
}
