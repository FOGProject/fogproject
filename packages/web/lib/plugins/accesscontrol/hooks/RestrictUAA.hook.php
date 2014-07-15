<?php
class RestrictUAA extends Hook
{
    var $name = 'RestrictUAA';
    var $description = 'Removes All users except the current user and ability to create/modify users';
    var $author = 'Rowlett';
    var $active = true;
	var $node = 'accesscontrol';
	private $linkToFilter;
	public function __construct()
	{
		parent::__construct();
		$this->linksToFilter = array('users');
	}
 
    public function UserData($arguments)
    {
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			if (!in_array($this->FOGUser->get('type'),array(0)))
			{
				foreach ($arguments['data'] AS $i => $data)
				{
					if($arguments['data'][$i]['name'] != $this->FOGUser->get('name'))
						unset($arguments['data'][$i]);
				}
			}
		}
    }
	public function RemoveName($arguments)
    {
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			if (!in_array($this->FOGUser->get('type'),array(0)))
			{
				unset($arguments['data'][0]);
				unset($arguments['template'][0]);
			}
		}
	}
	
	public function RemoveCreate($arguments)
	{
		$plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $this->node,'installed' => 1,'state' => 1)));
		if ($plugin && $plugin->isValid())
		{
			foreach($arguments['submenu'] AS $node => $link)
			{
				if (in_array($node,(array)$this->linksToFilter))
				{
					if (!in_array($this->FOGUser->get('type'),array(0)))
						unset($arguments['submenu'][$node]['add']);
				}
			}
		}
	}
}
$RestrictUAA = new RestrictUAA();
// Register hooks
$HookManager->register('USER_DATA', array($RestrictUAA, 'UserData'));
$HookManager->register('USER_EDIT', array($RestrictUAA, 'RemoveName'));
$HookManager->register('SUB_MENULINK_DATA', array($RestrictUAA, 'RemoveCreate'));
