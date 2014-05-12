<?php
/****************************************************
 * FOG Hook Manager
 *	Author:		$Author: Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
class HookManager extends FOGBase
{
	public $logLevel = 0;
	private $data;
	private $events = array(
		// Global
		'CSS',
		'JavaScript',
		'MainMenuData',				// data => array
		'SubMenuData',				// FOGSubMenu => FOGSubMenu Object
		//'MessageBox',				// data => string
		
		// Host Management
		// List / Search
		'HOST_DATA',
		'HOST_HEADER_DATA',
		'HOST_ADD_GEN',
		'HOST_ADD_AD',
		// Edit
		'HostEditUpdate',			// host => Host Object
		'HostEditUpdateSuccess',		// host => Host Object
		'HostEditUpdateFail',			// host => Host Object
		'HostEditConfirmMACUpdate',		// host => Host Object
		'HostEditConfirmMACUpdateSuccess',	// host => Host Object, mac = MACAddress Object
		'HostEditConfirmMACUpdateFail',		// host => Host Object, mac = MACAddress Object
		'HostEditADUpdate',
		'HostEditADUpdateSuccess',
		'HostEditADUpdateFail',
		'HostEditAddSnapinUpdate',
		'HostEditAddSnapinUpdateSuccess',
		'HostEditAddSnapinUpdateFail',
		'HostEditRemoveSnapinUpdate',
		'HostEditRemoveSnapinUpdateSuccess',
		'HostEditRemoveSnapinUpdateFail',
		
		// Group Management
		'GROUP_DATA',	// Index/Search Group
		'GROUP_ADD',    // Adding a Group
		'GROUP_ADD_SUCCESS', // Success add Group
		'GROUP_ADD_FAIL', // Fail add Group
		'GROUP_DATA_GEN', // Group Edit General Field
		'GROUP_DATA_TAKS', // Group Tasks
		'GROUP_DATA_ADV', // Group Advanced Tasks
		'GROUP_MEMBERSHIP', // Group Membership
		'GROUP_IMAGE', // Group Image
		'GROUP_SNAP_ADD', // Group Snap-add
		'GROUP_SNAP_DEL', // Group Snap-del
		'GROUP_MODULES', // Group Service Modules
		'GROUP_DISPLAY', // Group Service Display settings
		'GROUP_ALO', // Group Service ALO settings

		// Image Management
		'IMAGE_DATA', // Index/Search Image
		'IMAGE_ADD', // Adding an Image
		'IMAGE_ADD_POST', // Add Post data
		'IMAGE_EDIT', // Editing an Image
		'IMAGE_EDIT_POST', // Edit Post data
		
		// Storage Node Management
		// All Storage Nodes
		'StorageGroupTableHeader',
		'StorageGroupData',
		'StorageGroupAfterTable',
		// All Storage Groups
		'StorageNodeTableHeader',
		'StorageNodeData',
		'StorageNodeAfterTable',
		
		// Snapin Management
		'SnapinTableHeader',
		'SnapinData',
		'SnapinAfterTable',
		
		// Printer Management
		'PrinterTableHeader',
		'PrinterData',
		'PrinterAfterTable',
		
		// Task Management
		// Active Tasks
		'TasksActiveTableHeader',
		'TasksActiveData',
		'TasksActiveAfterTable',
		'TasksActiveRemove',
		'TasksActiveRemoveSuccess',
		'TasksActiveRemoveFail',
		'TasksActiveForce',
		'TasksActiveForceSuccess',
		'TasksActiveForceFail',
		// Search
		'TaskData',
		'TasksSearchTableHeader',
		// List Hosts
		'TasksListHostTableHeader',
		'TasksListHostData',
		'TasksListHostAfterTable',
		// List Group
		'TasksListGroupTableHeader',
		'TasksListGroupData',
		'TasksListGroupAfterTable',
		// Scheduled Tasks
		'TasksScheduledTableHeader',
		'TasksScheduledData',
		'TasksScheduledAfterTable',
		'TasksScheduledRemove',
		'TasksScheduledRemoveSuccess',
		'TasksScheduledRemoveFail',
		// Active Multicast Tasks
		'TasksActiveMulticastTableHeader',
		'TasksActiveMulticastData',
		'TasksActiveMulticastAfterTable',
		// Active Snapins
		'TasksActiveSnapinsTableHeader',
		'TasksActiveSnapinsData',
		'TasksActiveSnapinsAfterTable',
		'TasksActiveSnapinsRemove',			// id => snapinID, hostID => hostID
		'TasksActiveSnapinsRemoveSuccess',		// id => snapinID, hostID => hostID
		'TasksActiveSnapinsRemoveFail',			// id => snapinID, hostID => hostID
		
		// User Management
		'USER_DATA',
		'USER_ADD_SUCCESS',				// User Object
		'USER_ADD_FAIL',				// User Object
		'USER_DELETE_SUCCESS',				// User Object
		'USER_DELETE_FAIL',				// User Object
		'USER_UPDATE_SUCCESS',				// User Object
		'USER_UPDATE_FAIL',				// User Object
		
		// Login
		'Login',					// username => string, password => string
		'LoginSuccess',					// username => string, password => string, user => User Object
		'LoginFail',					// username => string, password => string
		
		// Logout
		'Logout',
	);
	
	function __construct()
	{
		parent::__construct();
		spl_autoload_register(function ()
		{
			global $HookManager;
			$hookDirectory = BASEPATH . '/lib/hooks';
			$hookIterator = new DirectoryIterator($hookDirectory);
			foreach ($hookIterator AS $fileInfo)
			{
				if ($fileInfo->isFile() && substr($fileInfo->getFilename(), -8) == 'hook.php')
					include($hookDirectory . '/' . $fileInfo->getFilename());
			}
		});
	}
	
	function register($event, $function)
	{
		try
		{
			if (!is_array($function) || count($function) != 2)
				throw new Exception('Function is invalid');
			if (!method_exists($function[0], $function[1]))
				throw new Exception('Function does not exist');
			if (!in_array($event, $this->events))
				throw new Exception('Invalid event');
			if (!($function[0] instanceof Hook))
				throw new Exception('Not a valid hook class');
			$this->log(sprintf('Registering Hook: Event: %s, Function: %s', $event, print_r($function, 1)));
			$this->data[$event][] = $function;
			return true;
		}
		catch (Exception $e)
		{
			$this->log(sprintf('Could not register Hook: Error: %s, Event: %s, Function: %s', $e->getMessage(), $event, print_r($function, 1)));
		}
		return false;
	}
	
	function unregister($event)
	{
		try
		{
			if(!in_array($event, $this->events))
				throw new Exception('Invalid event');
			unset($this->data[$event]);
			return true;
		}
		catch (Exception $e) {}
		return false;
	}
	
	function processEvent($event, $arguments = array())
	{
		if ($this->data[$event])
		{
			foreach ($this->data[$event] AS $function)
			{
				// Is hook active?
				if ($function[0]->active)
				{
					$this->log(sprintf('Running Hook: Event: %s, Class: %s', $event, get_class($function[0]), $function[0]));
					call_user_func($function, array_merge(array('event' => $event), (array)$arguments));
				}
				else
					$this->log(sprintf('Inactive Hook: Event: %s, Class: %s', $event, get_class($function[0]), $function[0]));
			}
		}
	}
	
	// Moved to OutputManager - remove once all code has been converted
	function processHeaderRow($templateData, $attributeData = array(), $wrapper = 'td')
	{
		// Loop data
		foreach ($templateData AS $i => $content)
		{
			// Create attributes data
			$attributes = array();
			foreach ((array)$attributeData[$i] as $attributeName => $attributeValue)
				$attributes[] = sprintf('%s="%s"', $attributeName, $attributeValue);
			// Push into results array
			$result[] = sprintf('<%s%s>%s</%s>',	$wrapper,
								(count($attributes) ? ' ' . implode(' ', $attributes) : ''),
								$content,
								$wrapper);
			// Reset
			unset($attributes);
		}
		// Return result
		return implode("\n", $result);
	}
	private function log($txt, $level = 1)
	{
		if (!$this->isAJAXRequest() && $this->logLevel >= $level)
			printf('[%s] %s%s', date("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
	}
	function isAJAXRequest()
	{
		return (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ? true : false);
	}
}
