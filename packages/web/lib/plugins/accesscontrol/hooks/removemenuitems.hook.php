<?php
class RemoveMenuItems extends Hook {
    private $linksToFilter = array('accesscontrol','printer','service','about');
    public $name = 'RemoveMenuItems';
    public $description = 'Removes menu items and restricts the links from the page';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'accesscontrol';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        if (!in_array($_REQUEST['node'],(array)$this->linksToFilter)) return;
        unset($arguments['main']);
    }
    public function SubMenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        if (!in_array($_REQUEST['node'],(array)$this->linksToFilter)) return;
        $linkformat = $arguments['linkformat'];
        $delformat = $arguments['delformat'];
        unset($arguments['submenu']["$linkformat#host-printers"]);
        unset($arguments['submenu']["$linkformat#host-service"]);
        unset($arguments['submenu']["$linkformat#host-virus-history"]);
        unset($arguments['submenu'][$delformat]);
    }
    public function NotAllowed($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        if (!$this->FOGUser->isValid()) return;
        if (!in_array($this->FOGUser->get('type'),array(2))) return;
        if (!in_array($_REQUEST['node'],(array)$this->linksToFilter)) return;
        $this->setMessage(_('Not Allowed!'));
        $this->redirect('index.php');
    }
}
$RemoveMenuItems = new RemoveMenuItems();
$HookManager->register('MAIN_MENU_DATA',array($RemoveMenuItems,'MenuData'));
$HookManager->register('SUB_MENULINK_DATA',array($RemoveMenuItems,'SubMenuData'));
$HookManager->register('CONTENT_DISPLAY',array($RemoveMenuItems,'NotAllowed'));
