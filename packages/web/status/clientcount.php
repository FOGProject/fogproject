<?php
require('../commons/base.inc.php');
$ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
if ($_REQUEST['id'] && is_numeric($_REQUEST['id']) && $_REQUEST['id'] > 0)
{
	$StorageNode = new StorageNode($_REQUEST['id']);
	if ($StorageNode && $StorageNode->isValid())
	{
		foreach($FOGCore->getClass('StorageNodeManager')->find(array('isEnabled' => 1,'storageGroupID' => $StorageNode->get('storageGroupID'))) AS $SN)
		{

			$ActivityActive += $SN->getUsedSlotCount();
			$ActivityQueued += $SN->getQueuedSlotCount();
			$ActivityTotalClients += $SN->get('maxClients');
		}
	}
}
$data = array(
	'ActivityActive' => $ActivityActive,
	'ActivityQueued' => $ActivityQueued,
	'ActivitySlots' => $ActivityTotalClients,
);
print json_encode($data);
