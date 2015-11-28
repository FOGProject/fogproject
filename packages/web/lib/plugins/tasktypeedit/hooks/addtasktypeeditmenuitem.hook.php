<?php
class AddTasktypeeditMenuItem extends Hook {
    public function __construct() {
        parent::__construct();
        $this->name = 'AddTasktypeeditMenuItem';
        $this->description = 'Add menu item for Task Type editing';
        $this->author = 'Tom Elliott';
        $this->active = true;
        $this->node = 'tasktypeedit';
    }
    public function MenuData($arguments) {
        if (!in_array($this->node,(array)$_SESSION['PluginsInstalled'])) return;
        $arguments['main'] = $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Task Type Management'),'fa fa-th-list fa-2x'));
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
