<?php
class ChangeItems extends Hook
{
	var $name = 'ChangeItems';
	var $description = 'Add Location to Active Tasks';
	var $author = 'Rowlett';
	var $active = true;
    var $node = 'location';	
	public function StorageNodeSetting($arguments)
	{
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
			if ($arguments['Host'] && $arguments['Host']->isValid() && $LA && $LA->isValid())
				$arguments['StorageNode'] = $LA->getStorageNode();
		}
	}
	public function StorageGroupSetting($arguments)
	{
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
			if ($arguments['Host'] && $arguments['Host']->isValid() && $LA && $LA->isValid())
				$arguments['StorageGroup'] = $LA->getStorageGroup();
		}
	}
	public function BootItemSettings($arguments)
	{
		$plugin = current($this->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			$LA = current($this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id'))));
			if ($arguments['Host'] && $arguments['Host']->isValid() && $LA && $LA->isValid())
			{
				if ($LA->isTFTP())
				{
					$ip = $LA->getStorageNode()->get('ip');
					$webroot = $arguments['webroot'];
					$memtest = $arguments['memtest'];
					$memdisk = $arguments['memdisk'];
					$bzImage = $arguments['bzImage'];
					$initrd = $arguments['initrd'];
					$arguments['memdisk'] = 'http://'.$ip.$webroot.'service/ipxe/'.$memdisk;
					$arguments['memtest'] = 'http://'.$ip.$webroot.'service/ipxe/'.$memtest;
					$arguments['bzImage'] = 'http://'.$ip.$webroot.'service/ipxe/'.$bzImage;
					$arguments['imagefile'] = 'http://'.$ip.$webroot.'service/ipxe/'.$initrd;
				}
			}
		}
	}
}
$ChangeItems = new ChangeItems();
// Register hooks
$HookManager->register('SNAPIN_NODE', array($ChangeItems, 'StorageNodeSetting'));
$HookManager->register('SNAPIN_GROUP', array($ChangeItems, 'StorageGroupSetting'));
$HookManager->register('BOOT_ITEM_NEW_SETTINGS', array($ChangeItems,'BootItemSettings'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS', array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS', array($ChangeItems,'StorageNodeSetting'));
