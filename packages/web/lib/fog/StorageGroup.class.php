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
	// Allow setting / getting of these additional fields
	public $additionalFields = array(
			);
	// Custom functions: Storage Group
	public function getStorageNodes() {return $this->getClass('StorageNodeManager')->find(array('isEnabled' => 1, 'storageGroupID' => $this->get('id')));}
	public function getTotalSupportedClients() {
		$clients = 0;
		foreach ($this->getStorageNodes() AS $StorageNode) $clients += $StorageNode->get('maxClients');
		return $clients;
	}
	public function getMasterStorageNode() {
		if ($this->getClass('StorageNodeManager')->count(array('isEnabled' => 1,'storageGroupID' => $this->get('id'))) >= 1)
			foreach ($this->getClass('StorageNodeManager')->find(array('isEnabled' => 1,'storageGroupID' => $this->get('id'))) AS $StorageNode) {
				if ($StorageNode->isValid() && $StorageNode->get('isMaster')) {
					$Node = $StorageNode;
					break;
				}
			}
		if (!$Node || !$Node->isValid()) $Node = current($this->getStorageNodes());
		return $Node;
	}
	public function getOptimalStorageNode() {
		$StorageNodes = $this->getStorageNodes();
		$winner = null;
		foreach ($StorageNodes AS $StorageNode) {
			if ($StorageNode->get('maxClients') > 0) {
				if ($winner == null) $winner = $StorageNode;
				else if ($StorageNode->getClientLoad() < $winner->getClientLoad()) $winner = $StorageNode;
			}
		}
		return $winner;
	}
	public function getUsedSlotCount() {
		return $this->getClass('TaskManager')->count(array(
					'stateID'	=> 3,
					'typeID'	=> array(1,15,17), // Only download tasks are Used! Uploads/Multicast can be as many as needed.
					'NFSGroupID'	=> $this->get('id'),
					));
	}
	public function getQueuedSlotCount() {
		return $this->getClass('TaskManager')->count(array(
					'stateID' => array(1,2),
					'typeID' => array(1,2,8,15,16,17), // Just so we can see what's queued we get all tasks (Upload/Download/Multicast).
					'NFSGroupID' => $this->get('id'),
					));
	}
}
