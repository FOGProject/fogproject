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
        $LA = self::getClass('LocationAssociation',@max(self::getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        $method = false;
        if ($arguments['Host']->get('task')->isValid() && ($arguments['Host']->get('task')->isCapture() || $arguments['Host']->get('task')->isMulticast())) $method = 'getMasterStorageNode';
        else if ($arguments['TaskType'] instanceof TaskType && $arguments['TaskType']->isValid() && ($arguments['TaskType']->isCapture() || $arguments['TaskType']->isMulticast())) $method = 'getMasterStorageNode';
        if ($LA->getStorageGroup()->isValid()) {
            if (!isset($arguments['snapin']) || ($arguments['snapin'] === true && self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') > 0)) $arguments['StorageNode'] = $LA->getStorageNode();
            if (!$method) return;
            $arguments['StorageNode'] = $LA->getStorageGroup()->{$method}();
        }
    }
    public function StorageGroupSetting($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LA = self::getClass('LocationAssociation',@max(self::getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        if (!$LA->getStorageGroup()->isValid()) return;
        $arguments['StorageGroup'] = $LA->getStorageGroup();
    }
    public function BootItemSettings($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['Host']->isValid()) return;
        $LA = self::getClass('LocationAssociation',@max(self::getSubObjectIDs('LocationAssociation',array('hostID'=>$arguments['Host']->get('id')))));
        if (!$LA->isValid()) return;
        $Location = $LA->getLocation();
        if (!$Location->isValid()) return;
        $StorageNode = $LA->getStorageNode();
        if (!$StorageNode->isValid()) return;
        $ip = $StorageNode->get('ip');
        $curroot = trim(trim($StorageNode->get('webroot'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        if (!$LA->isTFTP()) return;
        $memtest = $arguments['memtest'];
        $memdisk = $arguments['memdisk'];
        $bzImage = $arguments['bzImage'];
        $initrd = $arguments['initrd'];
        $arguments['webserver'] = $ip;
        $arguments['webroot'] = $webroot;
        $arguments['memdisk'] = "http://${ip}${webroot}service/ipxe/$memdisk";
        $arguments['memtest'] = "http://${ip}${webroot}service/ipxe/$memtest";
        $arguments['bzImage'] = "http://${ip}${webroot}service/ipxe/$bzImage";
        $arguments['imagefile'] = "http://${ip}${webroot}service/ipxe/$initrd";
    }
    public function AlterMasters($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['FOGServiceClass'] instanceof MulticastManager) return;
        $IDs = array_unique(array_filter(array_merge((array)$arguments['MasterIDs'],(array)self::getSubObjectIDs('Location','','storageNodeID'))));
        $arguments['StorageNodes'] = self::getClass('StorageNodeManager')->find(array('id'=>$IDs));
        foreach ($arguments['StorageNodes'] AS &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            if (!$StorageNode->get('isMaster')) $StorageNode->set('isMaster',1);
        }
    }
    public function MakeMaster($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$arguments['FOGServiceClass'] instanceof MulticastTask) return;
        $arguments['StorageNode']->set('isMaster',1);
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
$HookManager->register('TASK_LIMIT',array($ChangeItems,'SlotCount'));
$HookManager->register('CHECK_NODE_MASTERS',array($ChangeItems,'AlterMasters'));
$HookManager->register('CHECK_NODE_MASTER',array($ChangeItems,'MakeMaster'));
//$HookManager->register('HOST_EDIT_AFTER_SAVE',array($ChangeItems,'HostEditAfterSave'));
