<?php
class AddLDAPMenuItem extends Hook {
	public function __construct() {
		parent::__construct();
			$this->name = 'AddLDAPMenuItem';
			$this->description = 'Add menu item for LDAP';
			$this->author = 'Fernando Gietz';
			$this->active = true;
			$this->node = 'ldap';
	}
	public function MenuData($arguments) {
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('LDAP Management'),'fa fa-key fa-2x'));
	}
	public function addSearch($arguments) {
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddLDAPMenuItem = new AddLDAPMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLDAPMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddLDAPMenuItem,'addSearch'));
