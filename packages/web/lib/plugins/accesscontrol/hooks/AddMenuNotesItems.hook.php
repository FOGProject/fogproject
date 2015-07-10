<?php
class AddMenuNotesItems extends Hook {
	public function __construct() {
		parent::__construct();
		$this->name = 'AddMenuNotesItems';
		$this->description = 'Add menu items to the management page';
		$this->author = 'Rowlett';
		$this->active = true;
		$this->node = 'accesscontrol';
	}
	public function AddMenuData($arguments) {
		if (in_array($this->node,$_SESSION[PluginsInstalled])) $arguments['main'] = $this->array_insert_after(user,$arguments['main'],$this->node,array(_('Access Control'),'fa fa-user-secret fa-2x'));
	}
	public function addSearch($arguments) {
		if (in_array($this->node,$_SESSION[PluginsInstalled])) array_push($arguments[searchPages],$this->node);
	}
}
$AddMenuNotesItems = new AddMenuNotesItems();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddMenuNotesItems, 'AddMenuData'));
$HookManager->register('SEARCH_PAGES', array($AddMenuNotesItems, 'AddSearch'));
