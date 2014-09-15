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
	public $events = array(
		// FOG Configuration Hooks
		'PXE_BOOT_MENU',
		'CLIENT_UPDATE',
		'IMPORT',
		'IMPORT_POST',
		// Group Hooks
		'GROUP_DATA',
		'GROUP_SEARCH',
		'GROUP_ADD',
		'GROUP_ADD_POST',
		'GROUP_ADD_SUCCESS',
		'GROUP_ADD_FAIL',
		'GROUP_DATA_GEN',
		'GROUP_DATA_TASKS',
		'GROUP_DATA_ADV',
		'GROUP_HOST_NOT_IN_ME',
		'GROUP_HOST_NOT_IN_ANY',
		'GROUP_MEMBERSHIP',
		'GROUP_IMAGE',
		'GROUP_SNAP_ADD',
		'GROUP_SNAP_DEL',
		'GROUP_MODULES',
		'GROUP_DISPLAY',
		'GROUP_ALO',
		'GROUP_AD',
		'GROUP_ADD_PRINTER',
		'GROUP_REM_PRINTER',
		'GROUP_EDIT_POST',
		'GROUP_EDIT_SUCCESS',
		'GROUP_EDIT_FAIL',
		'GROUP_DELETE',
		'GROUP_DELETE_POST',
		'GROUP_DELETE_HOST_FORM',
		'GROUP_DELETE_SUCCESS',
		'GROUP_DELETE_FAIL',
		'GROUP_DEPLOY',
		// Host Hook Events
		'HOST_DATA',
		'HOST_HEADER_DATA',
		'HOST_ADD_GEN',
		'HOST_ADD_AD',
		'HOST_ADD_POST',
		'HOST_ADD_SUCCESS',
		'HOST_ADD_FAIL',
		'HOST_EDIT_GEN',
		'HOST_GROUP_JOIN',
		'HOST_EDIT_GROUP',
		'HOST_EDIT_TASKS',
		'HOST_EDIT_ADV',
		'HOST_EDIT_AD',
		'HOST_ADD_PRINTER',
		'HOST_EDIT_PRINTER',
		'HOST_SNAPIN_JOIN',
		'HOST_EDIT_SERVICE',
		'HOST_EDIT_DISPSERV',
		'HOST_EDIT_ALO',
		'HOST_INVENTORY',
		'HOST_VIRUS',
		'HOST_IMAGE_HIST',
		'HOST_SNAPIN_HIST',
		'HOST_USER_LOGIN',
		'HOST_EDIT_POST',
		'HOST_EDIT_SUCCESS',
		'HOST_EDIT_FAIL',
		'HOST_DEL',
		'HOST_DEL_POST',
		'HOST_DELETE_SUCCESS',
		'HOST_DELETE_FAIL',
		'HOST_IMPORT',
		'HOST_EXPORT',
		// Host Mobile Hooks
		'HOST_MOBILE_SEARCH',
		'HOST_MOBILE_DATA',
		// Image Hooks
		'IMAGE_DATA',
		'IMAGE_ADD',
		'IMAGE_ADD_POST',
		'IMAGE_ADD_SUCCESS',
		'IMAGE_ADD_FAIL',
		'IMAGE_EDIT',
		'IMAGE_EDIT_HOST',
		'IMAGE_EDIT_POST',
		'IMAGE_UPDATE_SUCCESS',
		'IMAGE_UPDATE_FAIL',
		'IMAGE_DELETE',
		'IMAGE_DELETE_POST',
		'IMAGE_DELETE_SUCCESS',
		'IMAGE_DELETE_FAIL',
		// Plugin Hooks
		'PLUGIN_DATA', // TODO: Actually name the hooks something else.
		// Printer Hooks
		'PRINTER_DATA',
		'PRINTER_SEARCH',
		'PRINTER_ADD',
		'PRINTER_ADD_POST',
		'PRINTER_ADD_SUCCESS',
		'PRINTER_ADD_FAIL',
		'PRINTER_EDIT',
		'PRINTER_EDIT_POST',
		'PRINTER_UPDATE_SUCCESS',
		'PRINTER_UPDATE_FAIL',
		'PRINTER_DELETE',
		'PRINTER_DELETE_POST',
		'PRINTER_DELETE_SUCCESS',
		'PRINTER_DELETE_FAIL',
		// Report Page Hooks TODO
		// ServerInfo Hooks
		'SERVER_INFO_DISP',
		// Service Page Hooks TODO
		'SERVICE_EDIT_POST',
		'SERVICE_EDIT_SUCCESS',
		'SERVICE_EDIT_FAIL',
		// Snapin Page Hooks
		'SNAPIN_DATA',
		'SNAPIN_SEARCH',
		'SNAPIN_ADD',
		'SNAPIN_ADD_POST',
		'SNAPIN_ADD_SUCCESS',
		'SNAPIN_ADD_FAIL',
		'SNAPIN_EDIT',
		'SNAPIN_EDIT_HOST',
		'SNAPIN_EDIT_POST',
		'SNAPIN_UPDATE_SUCCESS',
		'SNAPIN_UPDATE_FAIL',
		'SNAPIN_DELETE',
		'SNAPIN_DELETE_POST',
		'SNAPIN_DELETE_SUCCESS',
		'SNAPIN_DELETE_FAIL',
		// Storage Page Hooks
		'STORAGE_NODE_DATA',
		'STORAGE_NODE_ADD',
		'STORAGE_NODE_ADD_POST',
		'STORAGE_NODE_ADD_SUCCESS',
		'STORAGE_NODE_ADD_FAIL',
		'STORAGE_NODE_EDIT',
		'STORAGE_NODE_EDIT_POST',
		'STORAGE_NODE_EDIT_SUCCESS',
		'STORAGE_NODE_EDIT_FAIL',
		'STORAGE_NODE_DELETE',
		'STORAGE_NODE_DELETE_POST',
		'STORAGE_NODE_DELETE_SUCCESS',
		'STORAGE_NODE_DELETE_FAIL',
		'STORAGE_GROUP_DATA',
		'STORAGE_GROUP_ADD',
		'STORAGE_GROUP_ADD_POST',
		'STORAGE_GROUP_ADD_POST_SUCCESS',
		'STORAGE_GROUP_ADD_POST_FAIL',
		'STORAGE_GROUP_EDIT',
		'STORAGE_GROUP_EDIT_POST',
		'STORAGE_GROUP_EDIT_POST_SUCCESS',
		'STORAGE_GROUP_EDIT_POST_FAIL',
		'STORAGE_GROUP_DELETE',
		'STORAGE_GROUP_DELETE_POST',
		'STORAGE_GROUP_DELETE_POST_SUCCESS',
		'STORAGE_GROUP_DELETE_POST_FAIL',
		// Task Page Hooks
		'TASK_DATA',
		'TasksListGroupData',
		'TASK_FORCE',
		'TASK_CANCEL',
		'TaskActiveMulticastData',
		'TaskActiveSnapinsData',
		'TaskScheduleData',
		'TaskScheduledRemove',
		'TaskScheduledRemoveSuccess',
		'TaskScheduledRemoveFail',
		// Task Mobile Page Hooks TODO
		// User Page Hooks
		'USER_DATA',
		'USER_SEARCH',
		'USER_ADD',
		'USER_ADD_POST',
		'USER_ADD_SUCCESS',
		'USER_ADD_FAIL',
		'USER_EDIT',
		'USER_EDIT_POST',
		'USER_UPDATE_SUCCESS',
		'USER_UPDATE_FAIL',
		'USER_DELETE',
		'USER_DELETE_POST',
		'USER_DELETE_SUCCESS',
		'USER_DELETE_FAIL',
		// FOGCore getMessages Hook
		'MessageBox',
		// Mainmenu Data Hook
		'MAIN_MENU_DATA',
		// Submenu data Hook
		'SUB_MENULINK_DATA',
		// ProcessLogin Login Hooks
		'LoginSuccess',
		'LoginFail',
		'Login',
		// Main Index page Hooks
		'LOGOUT',
		'CONTENT_DISPLAY',
		'CSS',
		'SubMenuData',
		'JAVASCRIPT',
	);
	public function register($event, $function)
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
			$this->log(sprintf('Registering Hook: Event: %s, Function: %s', $event, $function[1]));
			$this->data[$event][] = $function;
			return true;
		}
		catch (Exception $e)
		{
			$this->log(sprintf('Could not register Hook: Error: %s, Event: %s, Function: %s', $e->getMessage(), $event, $function[1]));
		}
		return false;
	}
	public function processEvent($event, $arguments = array())
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
	public function load()
	{
		global $Init;
		foreach($Init->HookPaths AS $hookDirectory)
		{
			if (file_exists($hookDirectory))
			{
				$hookIterator = new DirectoryIterator($hookDirectory);
				foreach ($hookIterator AS $fileInfo)
				{
					$file = !$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-9) == '.hook.php' ? file($fileInfo->getPathname()) : null;
					$PluginName = preg_match('#plugins#i',$hookDirectory) ? basename(substr($hookDirectory,0,-6)) : null;
					$Plugin = current($this->FOGCore->getClass('PluginManager')->find(array('name' => $PluginName,'installed' => 1)));
					if ($Plugin)
						$className = (substr($fileInfo->getFilename(),-9) == '.hook.php' ? substr($fileInfo->getFilename(),0,-9) : null);
					else if ($file && !preg_match('#plugins#',$fileInfo->getPathname()))
					{
						$key = '$active';
						foreach($file AS $lineNumber => $line)
						{
							if (strpos($line,$key) !== false)
								break;
						}
						if(preg_match('#true#i',$file[$lineNumber]))
							$className = (substr($fileInfo->getFileName(),-9) == '.hook.php' ? substr($fileInfo->getFilename(),0,-9) : null);
					}
					if ($className)
						$class = new $className();
				}
			}
		}
	}
	private function log($txt, $level = 1)
	{
		if (!$this->isAJAXRequest() && $this->logLevel >= $level)
			printf('[%s] %s%s', $this->formatTime('now',"d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
	}
	public function isAJAXRequest()
	{
		return (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ? true : false);
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
