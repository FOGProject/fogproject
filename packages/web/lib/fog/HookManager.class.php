<?php
/****************************************************
 * FOG Hook Manager
 *	Author:		$Author: Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 2448 $
 *	Last Update:	$LastChangedDate: 2014-10-20 16:09:14 -0400 (Mon, 20 Oct 2014) $
 ***/
class HookManager extends FOGBase
{
	public $logLevel = 0;
	private $data;
	public $events;
	public function register($event, $function)
	{
		try
		{
			if (!is_array($function) || count($function) != 2)
				throw new Exception('Function is invalid');
			if (!method_exists($function[0], $function[1]))
				throw new Exception('Function does not exist');
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
	public function getEvents()
	{
		global $Init;
		$paths = array(BASEPATH.'/management');
		$paths = array_merge((array)$paths,(array)$Init->PagePaths,(array)$Init->FOGPaths);
		foreach($paths AS $path)
		{
			$dir = new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS);
			$Iterator = new RecursiveIteratorIterator($dir);
			$Iterator = new RegexIterator($Iterator,'/^.+\.php$/i',RecursiveRegexIterator::GET_MATCH);
			$regexp = '#processEvent\([\'\"](.*?)[\'\"]#';
			foreach($Iterator AS $file)
				preg_match_all($regexp,file_get_contents($file[0]),$matches[]);
			$matches = $this->array_filter_recursive($matches);
			foreach($matches AS $match => $value)
			{
				if ($matches[$match][1])
					$matching[] = $matches[$match][1];
			}
			foreach($matching AS $ind => $arr)
			{
				foreach($arr AS $val)
					$this->events[] = $val;
			}
		}
		foreach($this->getClass('ServiceManager')->getSettingCats() AS $CAT)
		{
			$divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',preg_replace('/\./','_',$CAT)));
			array_push($this->events,'CLIENT_UPDATE_'.$divTab);
		}
		foreach($this->getClass('PXEMenuOptionsManager')->find() AS $Menu)
		{
			$divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',preg_replace('/\./','_',$Menu->get('name'))));
			array_push($this->events,'BOOT_ITEMS_'.$divTab);
		}
		array_push($this->events,'HOST_DEL','HOST_DEL_POST','GROUP_DEL','GROUP_DEL_POST','IMAGE_DEL','IMAGE_DEL_POST','SNAPIN_DEL','SNAPIN_DEL_POST','PRINTER_DEL','PRINTER_DEL_POST','HOST_DEPLOY','GROUP_DEPLOY','HOST_EDIT_TASKS','GROUP_EDIT_TASKS','HOST_EDIT_ADV','GROUP_EDIT_ADV','HOST_EDIT_AD','GROUP_EDIT_AD');
		$this->events = array_unique($this->events);
		$this->events = array_values($this->events);
		asort($this->events);
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
		global $Init,$FOGCore;
		foreach($Init->HookPaths AS $hookDirectory)
		{
			if (file_exists($hookDirectory))
			{
				$hookIterator = new DirectoryIterator($hookDirectory);
				foreach ($hookIterator AS $fileInfo)
				{
					$file = !$fileInfo->isDot() && $fileInfo->isFile() && substr($fileInfo->getFilename(),-9) == '.hook.php' ? file($fileInfo->getPathname()) : null;
					$PluginName = preg_match('#plugins#i',$hookDirectory) ? basename(substr($hookDirectory,0,-6)) : null;
					$Plugin = current($FOGCore->getClass('PluginManager')->find(array('name' => $PluginName,'installed' => 1)));
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
			printf('[%s] %s%s', $this->nice_date()->format("d-m-Y H:i:s"), trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt)), "<br />\n");
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
