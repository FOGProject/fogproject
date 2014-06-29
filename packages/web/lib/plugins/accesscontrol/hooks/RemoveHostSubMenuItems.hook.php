<?php	
class RemoveHostSubMenuItems extends Hook
{
	var $name = 'RemoveHostSubMenuItems';
	var $description = 'Removes submenu items from the host page.';
	var $author = 'Rowlett';
	var $active = true;
	public function __construct()
	{
		parent::__construct();
		$this->linksToFilter = array('host');
	}
	public function SubMenuData($arguments)
	{
		foreach($arguments['submenu'] AS $node => $link)
		{
			if (in_array($node,$this->linksToFilter))
			{
				$linkformat = $_SERVER['PHP_SELF'].'?node='.$node.'&sub=edit&id='.$_REQUEST['id'];
				$delformat = $_SERVER['PHP_SELF'].'?node='.$node.'&sub=delete&id='.$_REQUEST['id'];
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-printers']);
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-service']);
				unset($arguments['submenu'][$node]['id'][$linkformat.'#host-virus-history']);
				if(!in_array($this->FOGUser->get('name'),array('fog')))
					unset($arguments['submenu'][$node]['id'][$delformat]);
			}
		}
	}
}
$RemoveHostSubMenuItems = new RemoveHostSubMenuItems();
// Register hooks
$HookManager->register('SUB_MENULINK_DATA', array($RemoveHostSubMenuItems, 'SubMenuData'));
