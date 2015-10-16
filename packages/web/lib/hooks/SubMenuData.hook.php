<?php
class SubMenuData extends Hook {
    public $name = 'SubMenuData';
    public $description = 'Example showing how to manipulate SubMenu Data. Adds Menu items under "Host Management"';
    public $author = 'Blackout';
    public $active = false;
    public $node = 'host';
    public function SubMenu($arguments) {
        if ($_REQUEST['node'] == $this->node) {
            $arguments['submenu'][$this->node]['http://www.google.com'] = 'Google';
            if ($_REQUEST['id']) $arguments['submenu'][$this->node]['id']['http://www.google.com'] = 'Google here';
        }
    }
    public function SubMenuNotes($arguments) {
        if ($_REQUEST['node'] == $this->node) {
            if ($_REQUEST['id']) {
                $arguments['title']['Example Bolded Header'] = _('Example data to insert');
                $arguments['title']['Example Add Description'] = $arguments['object']->get('description');
            }
        }
    }
}
$SubMenuData = new SubMenuData();
// Hook Event
$HookManager->register('SUB_MENULINK_DATA', array($SubMenuData, 'SubMenu'));
$HookManager->register('SUB_MENULINK_NOTES', array($SubMenuData, 'SubMenuNotes'));
