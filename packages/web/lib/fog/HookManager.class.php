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
	public function __construct()
	{
		$dir = new RecursiveDirectoryIterator(BASEPATH,FilesystemIterator::SKIP_DOTS);
		$Iterator = new RecursiveIteratorIterator($dir);
		$regexp = '#processEvent\([\'\"](.*?)[\'\"]\,#';
		foreach($Iterator AS $file)
			preg_match_all($regexp,file_get_contents($file),$matches[]);
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
		$this->events = array_unique($this->events);
		return parent::__construct();
	}
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
					$Plugin = current($this->getClass('PluginManager')->find(array('name' => $PluginName,'installed' => 1)));
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
