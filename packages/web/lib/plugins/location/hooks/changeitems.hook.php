<?php
class ChangeItems extends Hook {
    public $name = 'ChangeItems';
    public $description = 'Add Location to Active Tasks';
    public $author = 'Rowlett';
    public $active = true;
    public $node = 'location';
    public function StorageNodeSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LA = $this->getClass('LocationAssociation',@max($this->getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        $method = false;
        if ($arguments['Host']->get('task')->isValid() && ($arguments['Host']->get('task')->isUpload() || $arguments['Host']->get('task')->isMulticast())) $method = 'getMasterStorageNode';
        $arguments['StorageNode'] = $LA->getStorageNode();
        if (!$method) return;
        $arguments['StorageNode'] = $LA->getStorageGroup()->$method();
    }
    public function StorageGroupSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LA = $this->getClass('LocationAssociation',@max($this->getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        if (!$LA->getStorageGroup()->isValid()) return;
        $arguments['StorageGroup'] = $LA->getStorageGroup();
    }
    public function BootItemSettings($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LA = $this->getClass('LocationAssociation',@max($this->getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        $Location = $LA->getLocation();
        if (!$Location->isValid()) return;
        $StorageNode = $LA->getStorageNode();
        if (!$StorageNode->isValid()) return;
        $ip = $StorageNode->get('ip');
        $curroot = trim(trim($StorageNode->get('webroot'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        if (!$LA->isTFTP()) continue;
        $memtest = $arguments['memtest'];
        $memdisk = $arguments['memdisk'];
        $bzImage = $arguments['bzImage'];
        $initrd = $arguments['initrd'];
        $arguments['memdisk'] = "http://${ip}${webroot}service/ipxe/$memdisk";
        $arguments['memtest'] = "http://${ip}${webroot}service/ipxe/$memtest";
        $arguments['bzImage'] = "http://${ip}${webroot}service/ipxe/$bzImage";
        $arguments['imagefile'] = "http://${ip}${webroot}service/ipxe/$initrd";
    }
}
$ChangeItems = new ChangeItems();
$HookManager->register('SNAPIN_NODE',array($ChangeItems,'StorageNodeSetting'));
$HookManager->register('SNAPIN_GROUP',array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('BOOT_ITEM_NEW_SETTINGS',array($ChangeItems,'BootItemSettings'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS',array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('HOST_NEW_SETTINGS',array($ChangeItems,'StorageNodeSetting'));
$HookManager->register('HOST_NEW_SETTINGS',array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS',array($ChangeItems,'StorageNodeSetting'));
$HookManager->register('HOST_EDIT_AFTER_SAVE',array($ChangeItems,'HostEditAfterSave'));
