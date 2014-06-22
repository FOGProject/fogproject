<?php
/****************************************************
 * FOG Hook
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision$
 *	Last Update:	$LastChangedDate$
 ***/
 
abstract class Hook extends FOGBase
{
	public $name;
	public $description;
	public $author;
	public $active = true;
	public $logLevel = 0;
	public $logToFile = false;
	public $logToBrowser = true;
	public function run($arguments)
	{
	}
	public function log($txt, $level = 1)
	{
		$log = trim(preg_replace(array("#\r#", "#\n#", "#\s+#", "# ,#"), array("", " ", " ", ","), $txt));
		
		if ($this->logToBrowser && $this->logLevel >= $level && !$this->isAJAXRequest())
			printf('%s<div class="debug-hook">%s</div>%s', "\n", $log, "\n");
		if ($this->logToFile)
			file_put_contents(BASEPATH . '/lib/hooks/' . get_class($this) . '.log', sprintf("[%s] %s\r\n", date("d-m-Y H:i:s"), $log), FILE_APPEND | LOCK_EX);
	}
	public function isAJAXRequest()
	{
		return (strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ? true : false);
	}
}
