<?php
class RemoveMenuItems extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'RemoveMenuItems';
        $this->description = 'Removes menu items and restricts the links from the page';
        $this->author = 'Tom Elliott';
        $this->active = true;
        $this->node = 'accesscontrol';
        $this->getLoggedIn();
    }
    public function getLoggedIn() {
        if ($this->FOGUser->isValid() && in_array($this->FOGUser->get('type'),array(2))) $this->linksToFilter = array('accesscontrol','printer','service','about');
    }
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        foreach((array)$this->linksToFilter AS $i => &$link) unset($arguments['main'][$link]);
        unset($link);
    }
    public function SubMenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        foreach($arguments['submenu'] AS $node => &$link) {
            if (in_array($node,(array)$this->linksToFilter)) {
                $linkformat = "?node=$node&sub=edit&id=".$_REQUEST[id];
                $delformat = "?node=$node&sub=delete&id=".$_REQUEST[id];
                unset($arguments[submenu][$node][id]["$linkformat#host-printers"]);
                unset($arguments[submenu][$node][id]["$linkformat#host-service"]);
                unset($arguments[submenu][$node][id]["$linkformat#host-virus-history"]);
                if(!in_array($this->FOGUser->get(name),array('fog'))) unset($arguments[submenu][$node][id][$delformat]);
            }
            unset($link);
        }
    }
    public function NotAllowed($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (in_array($_REQUEST['node'],(array)$this->linksToFilter)) {
            $this->setMessage('Not Allowed!');
            $this->redirect('index.php');
        }
    }
}
$RemoveMenuItems = new RemoveMenuItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($RemoveMenuItems, 'MenuData'));
$HookManager->register('SUB_MENULINK_DATA', array($RemoveMenuItems, 'SubMenuData'));
$HookManager->register('CONTENT_DISPLAY', array($RemoveMenuItems, 'NotAllowed'));
