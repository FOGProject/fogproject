<?php
class AddTasktypeeditMenuItem extends Hook {
    public $name = 'AddTasktypeeditMenuItem';
    public $description = 'Add menu item for Task Type editing';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'tasktypeedit';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Task Type Management'),'fa fa-th-list fa-2x'));
    }
    public function addSearch($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['searchPages'],$this->node);
    }
    public function removeActionBox($arguments) {
        if (in_array($this->node,(array)$_SESSION['PluginsInstalled']) && $_REQUEST['node'] == $this->node) $arguments['actionbox'] = '';
    }
    public function addPageWithObject($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        array_push($arguments['PagesWithObjects'],$this->node);
    }
}
$AddTasktypeeditMenuItem = new AddTasktypeeditMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddTasktypeeditMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddTasktypeeditMenuItem,'addSearch'));
$HookManager->register('ACTIONBOX',array($AddTasktypeeditMenuItem,'removeActionBox'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddTasktypeeditMenuItem, 'addPageWithObject'));
