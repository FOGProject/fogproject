<?php
class AddClientMenuItem extends Hook
{
	var $name = 'AddClientMenuItem';
		var $description = 'Add menu item for Client';
		var $author = 'Tom Elliott';
		var $active = true;
		var $node = 'nodeclient';
		public function MenuData($arguments)
		{
			if (in_array($this->node,$_SESSION['PluginsInstalled']))
			{
				$arguments['main'] = $this->array_insert_after('service',$arguments['main'],$this->node,array(_('Node Client Management'),'fa fa-weixin fa-2x'));
			}
		}
	public function addSearch($arguments)
	{
		if (in_array($this->node,$_SESSION['PluginsInstalled']))
			array_push($arguments['searchPages'],$this->node);
	}
}
$AddClientMenuItem = new AddClientMenuItem();
// Register hooks
$HookManager->register('MAIN_MENU_DATA', array($AddClientMenuItem, 'MenuData'));
$HookManager->register('SEARCH_PAGES', array($AddClientMenuItem, 'addSearch'));
