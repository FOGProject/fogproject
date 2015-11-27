<?php
class RestrictUAA extends Hook {
    private $linkToFilter;
    public function __construct() {
        parent::__construct();
        $this->name = 'RestrictUAA';
        $this->description = 'Removes All users except the current user and ability to create/modify  users';
        $this->author = 'Rowlett';
        $this->active = true;
        $this->node = 'accesscontrol';
        $this->linksToFilter = array('users');
    }
    public function UserData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        foreach ($arguments['data'] AS $i => &$data) {
            if ($data['name'] == $_SESSION['FOG_USERNAME']) continue;
            unset($arguments['data'][$i]);
            unset($data);
        }
    }
    public function RemoveName($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        unset($arguments['data'][0]);
        unset($arguments['template'][0]);
    }
    public function RemoveCreate($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        foreach($arguments['submenu'] AS $node => &$link) {
            if (!in_array($node,(array)$this->linksToFilter)) continue;
            unset($arguments['submenu'][$node]['add']);
        }
        unset($link);
    }
}
$RestrictUAA = new RestrictUAA();
$HookManager->register('USER_DATA', array($RestrictUAA, 'UserData'));
$HookManager->register('USER_EDIT', array($RestrictUAA, 'RemoveName'));
$HookManager->register('SUB_MENULINK_DATA', array($RestrictUAA, 'RemoveCreate'));
