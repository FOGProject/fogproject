<?php
class StorageGroup extends FOGController {
    // Table
    public $databaseTable = 'nfsGroups';
    // Name -> Database field name
    public $databaseFields = array(
        'id'		=> 'ngID',
        'name'		=> 'ngName',
        'description'	=> 'ngDesc'
    );
    // Additional Fields
    // Custom functions: Storage Group
    public function getStorageNodes() {
        return $this->getClass(StorageNodeManager)->find(array(isEnabled=>1, storageGroupID=>$this->get(id)),'','','','','','','id');
    }
    public function getTotalSupportedClients() {
        $clients = 0;
        foreach ($this->getStorageNodes() AS $i => &$StorageNode) $clients += $this->getClass(StorageNode,$StorageNode)->get(maxClients);
        unset($StorageNode);
        return $clients;
    }
    public function getMasterStorageNode() {
        foreach($this->getStorageNodes() AS $i => &$StorageNode) {
            $TmpNode = $this->getClass(StorageNode,$StorageNode);
            if ($TmpNode->isValid() && $TmpNode->get(isMaster) && $Node = $TmpNode) break;
        }
        unset($StorageNode);
        if (!$Node) $Node = $this->getClass(StorageNode,@min($this->getStorageNodes()));
        return $Node;
    }
    public function getOptimalStorageNode() {
        $StorageNodes = $this->getClass(StorageNodeManager)->find(array(id=>$this->getStorageNodes()));
        $winner = null;
        foreach ($StorageNodes AS $i => &$StorageNode) {
            if ($StorageNode->get(maxClients)>0) {
                if ($winner == null) $winner = $StorageNode;
                else if ($StorageNode->getClientLoad() < $winner->getClientLoad()) $winner = $StorageNode;
            }
        }
        unset($StorageNode);
        return $winner;
    }
    public function getUsedSlotCount() {
        return $this->getClass(TaskManager)->count(array(
            stateID=>3,
            typeID=>array(1,15,17), // Only download tasks are Used! Uploads/Multicast can be as many as needed.
            NFSGroupID=>$this->get(id),
        ));
    }
    public function getQueuedSlotCount() {
        return $this->getClass(TaskManager)->count(array(
            stateID=>array(1,2),
            typeID=>array(1,2,8,15,16,17), // Just so we can see what's queued we get all tasks (Upload/Download/Multicast).
            NFSGroupID=>$this->get(id),
        ));
    }
}
