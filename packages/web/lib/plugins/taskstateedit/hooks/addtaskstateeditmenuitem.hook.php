<?php
class AddTaskstateeditMenuItem extends Hook {
    public $name = 'AddTaskstateeditMenuItem';
    public $description = 'Add menu item for Task State editing';
    public $author = 'Tom Elliott';
    public $active = true;
    public $node = 'taskstateedit';
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['main'] = $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Task State Management'),'fa fa-hourglass-start fa-2x'));
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
$AddTaskstateeditMenuItem = new AddTaskstateeditMenuItem();
$HookManager->register('MAIN_MENU_DATA',array($AddTaskstateeditMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddTaskstateeditMenuItem,'addSearch'));
$HookManager->register('ACTIONBOX',array($AddTaskstateeditMenuItem,'removeActionBox'));
$HookManager->register('PAGES_WITH_OBJECTS', array($AddTaskstateeditMenuItem, 'addPageWithObject'));
