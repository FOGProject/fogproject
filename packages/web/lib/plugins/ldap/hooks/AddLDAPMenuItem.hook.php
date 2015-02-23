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
		if ($_SESSION[$this->node])
			$arguments['main'] = $this->array_insert_after('storage',$arguments['main'],$this->node,array(_('LDAP Management'),'fa fa-key fa-2x'));
	}
	public function addSearch($arguments)
	{
		if ($_SESSION[$this->node])
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddLDAPMenuItem = new AddLDAPMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddLDAPMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES',array($AddLDAPMenuItem,'addSearch'));
