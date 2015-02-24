<?php
class AddLDAPMenuItem extends Hook
{
	var $name = 'AddLDAPMenuItem';
	var $description = 'Add menu item for LDAP';
	var $author = 'Fernando Gietz';
	var $active = true;
	var $node = 'ldap';
	public function MenuData($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('LDAP Management'),'fa fa-key fa-2x'));
	}
	public function addSearch($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddLDAPMenuItem = new AddLDAPMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLDAPMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddLDAPMenuItem,'addSearch'));
