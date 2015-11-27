<?php
class ChangeItems extends Hook {
    public function __construct() {
        $this->name = 'ChangeItems';
        $this->description = 'Add Location to Active Tasks';
        $this->author = 'Rowlett';
        $this->active = true;
        $this->node = 'location';
    }
    public function StorageNodeSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LocAssocs = $this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id')));
        foreach ($LocAssocs AS $i => &$LA) {
            $arguments['StorageNode'] = $LA->getStorageNode();
            break;
            unset($LA);
        }
    }
    public function StorageGroupSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LocAssocs = $this->getClass('LocationAssociationManager')->find(array('hostID' => $arguments['Host']->get('id')));
        foreach ((array)$this->getClass('LocationAssociationManager')->find(array('hostID'=>$arguments['Host']->get('id'))) AS $i => &$LA) {
            $arguments['StorageGroup'] = $LA->getStorageGroup();
            break;
            unset($LA);
        }
    }
    public function BootItemSettings($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        foreach ((array)$this->getClass('LocationAssociationManager')->find(array('hostID'=>$arguments['Host']->get('id'))) AS $i => &$LA) {
            $Location = $LA->getLocation();
            if (!$Location->isValid()) continue;
            $StorageNode = $LA->getStorageNode();
            if (!$StorageNode->isValid()) continue;
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
}
$ChangeItems = new ChangeItems();
$HookManager->register('SNAPIN_NODE', array($ChangeItems, 'StorageNodeSetting'));
$HookManager->register('SNAPIN_GROUP', array($ChangeItems, 'StorageGroupSetting'));
$HookManager->register('BOOT_ITEM_NEW_SETTINGS', array($ChangeItems,'BootItemSettings'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS', array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('HOST_NEW_SETTINGS', array($ChangeItems,'StorageNodeSetting'));
$HookManager->register('HOST_NEW_SETTINGS', array($ChangeItems,'StorageGroupSetting'));
$HookManager->register('BOOT_TASK_NEW_SETTINGS', array($ChangeItems,'StorageNodeSetting'));
$HookManager->register('HOST_EDIT_AFTER_SAVE', array($ChangeItems,'HostEditAfterSave'));
