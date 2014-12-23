<?php
class LogViewerHook extends Hook
{
	var $name = 'LogViewerHook';
	var $description = 'Allows adding/removing log viewer files to the system';
	var $author = 'Tom Elliott/Lee Rowlett';
	var $active = false;
	public function LogViewerAdd($arguments)
	{
		foreach($arguments['files'] AS $name => $filearray)
		{
			$ftpstart = $arguments['ftpstart'][$name];
			$logfile = "/var/log/rsync.log";
			$shortdesc = "Rsync";
			if (file_exists($ftpstart.$logfile))
				$arguments['files'][$name][$shortdesc] = $ftpstart.$logfile;
		}
	}
}
$LogViewerHook = new LogViewerHook();
// Hook Event
$HookManager->register('LOG_VIEWER_HOOK', array($LogViewerHook, 'LogViewerAdd'));
