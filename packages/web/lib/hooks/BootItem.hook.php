<?php
class BootItem extends Hook
{
	var $name = 'BootItem';
	var $description = 'Example how to tweak boot menu items.';
	var $author = 'Tom Elliott';
	var $active = false;
	public function tweaktask($arguments)
	{
		if ($arguments['ipxe']['task'])
			$arguments['ipxe']['task'][1] .= " capone=1";
	}
	public function tweakmenu($arguments)
	{
		// This is How the menu get's displayed:
		// 'ipxe' 'head' key's followed by the item.
		if ($arguments['ipxe']['head'])
		{
			$arguments['ipxe']['head'][0] = '#!ipxe';
			$arguments['ipxe']['head'][1] = 'cpuid --ext 29 && set arch x86_64 || set arch i386';
			$arguments['ipxe']['head'][2] = 'goto get_console';
			$arguments['ipxe']['head'][3] = ':console_set';
			$arguments['ipxe']['head'][4] = 'colour --rgb 0xff6600 2';
			$arguments['ipxe']['head'][5] = 'cpair --foreground 7 --background 2 2';
			$arguments['ipxe']['head'][6] = 'goto MENU';
			$arguments['ipxe']['head'][7] = ':alt_console';
			$arguments['ipxe']['head'][8] = 'cpair --background 0 1 && cpair --background 1 2';
			$arguments['ipxe']['head'][9] = 'goto MENU';
			$arguments['ipxe']['head'][10] = ':get_console';
		}
		// This is the start of the MENU information.
		// 'ipxe' 'menustart' key's followed by the item
		if ($arguments['ipxe']['menustart'])
		{
			$arguments['ipxe']['menustart'][0] = ':MENU';
			$arguments['ipxe']['menustart'][1] = 'menu';
			$arguments['ipxe']['menustart'][2] = 'colour --rgb 0x00ff00 0';
			$arguments['ipxe']['menustart'][3] = 'cpair --foreground 0 3';
			// 4th element should be left alone, though you could obtain the host info as needed.
			$arguments['ipxe']['menustart'][5] = 'item --gap -- -------------------------------------';
		}
		// The next subset of informations is about the item labels.  This is pulled from the db so some common values may be like:
		// item-<label-name>  so fog.local has item value of: item-fog.local
		// inside of the item label is an arrayed item of value [0] containing the label
		// so to tweak:
		foreach($this->getClass('PXEMenuOptionsManager')->find() AS $Menu)
		{
			if ($arguments['ipxe']['item-'.$Menu->get('name')] && $Menu->get('name') == 'fog.local')
				$arguments['ipxe']['item-fog.local'][0] = 'item fog.local THIS BOOTS TO DISK';
			// Similar to the item-<label-name>  The choices follow similar constructs
			if ($arguments['ipxe']['choice-'.$Menu->get('name')] && $Menu->get('name') == 'fog.local')
			{
				$arguments['ipxe']['choice-fog.local'][0] = ':fog.local';
				$arguments['ipxe']['choice-fog.local'][1] = 'sanboot --no-describe --drive 0x80 || goto MENU';
			}
		}
		// Default item is set to: 'ipxe' 'default'
		if ($arguments['ipxe']['default'])
			$arguments['ipxe']['default'] = 'choose --default fog.local --timeout 3000 target && goto ${target}';
	}
}
$BootItem = new BootItem();
// Hook Event
$HookManager->register('IPXE_EDIT', array($BootItem, 'tweaktask'));
$HookManager->register('IPXE_EDIT', array($BootItem, 'tweakmenu'));
