<?php
class SubMenuData extends Hook {
    public $name = 'SubMenuData';
    public $description = 'Example showing how to manipulate SubMenu Data. Adds Menu items under "Host Management"';
    public $author = 'Blackout';
    public $active = false;
    public $node = 'host';
    public function SubMenu($arguments) {
        if ($_REQUEST['node'] != $this->node) return;
        $arguments['menu']['http://www.google.com'] = 'Google';
        if (!$arguments['object']) return;
        $arguments['submenu']['http://www.google.com'] = 'Google here';
        $arguments['notes'][_('Example Bolded Header')] = $arguments['object']->get('description');
        $arguments['notes']['Example Add Description'] = $arguments['object']->get('description');
    }
}
$HookManager->register('SUB_MENULINK_DATA',array(new SubMenuData(),'SubMenu'));
