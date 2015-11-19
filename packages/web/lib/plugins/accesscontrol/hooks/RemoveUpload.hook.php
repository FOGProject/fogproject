<?php
class RemoveUpload extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'RemoveUpload';
        $this->description = 'Removes upload links for engineers';
        $this->author = 'Rowlett';
        $this->active = true;
        $this->node = 'accesscontrol';
    }
    public function UploadData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        if (!($_REQUEST['node'] == 'tasks' && $_REQUEST['sub'] == 'listhosts')) return;
        unset($arguments['headerData'][3],$arguments['templates'][3]);
    }
    public function EditTasks($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        unset($arguments['data'][1],$arguments['template'][1]);
        unset($arguments['data'][11],$arguments['template'][11]);
    }
    public function SubMenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        $i = 0;
        foreach((array)$arguments['submenu'][$_REQUEST['node']]['id'] AS $link => &$info) {
            if (!in_array($i++,array(0,5,10,3))) continue;
            unset($arguments['submenu'][$_REQUEST['node']]['id'][$link]);
        }
        unset($info);
    }
}
$RemoveUpload = new RemoveUpload();
$HookManager->register('HOST_DATA', array($RemoveUpload, 'UploadData'));
$HookManager->register('SUB_MENULINK_DATA', array($RemoveUpload, 'SubMenuData'));
$HookManager->register('HOST_EDIT_TASKS', array($RemoveUpload, 'EditTasks'));
$HookManager->register('HOST_EDIT_ADV', array($RemoveUpload, 'EditTasks'));
