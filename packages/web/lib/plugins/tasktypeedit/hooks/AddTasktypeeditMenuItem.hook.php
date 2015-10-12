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
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('task',$arguments['main'],$this->node,array(_('Task Type Management'),'fa fa-th-list fa-2x'));
	}
    public function addSearch($arguments) {
		if (in_array($this->node,(array)$_SESSION['PluginsInstalled'])) array_push($arguments['searchPages'],$this->node);
    }
    public function removeActionBox($arguments) {
        if (in_array($this->node,(array)$_SESSION['PluginsInstalled']) && $_REQUEST['node'] == $this->node) $arguments['actionbox'] = '';
    }
}
$AddTasktypeeditMenuItem = new AddTasktypeeditMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA',array($AddTasktypeeditMenuItem,'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddTasktypeeditMenuItem,'addSearch'));
$HookManager->register('ACTIONBOX',array($AddTasktypeeditMenuItem,'removeActionBox'));
