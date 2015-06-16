<?php
/** class LogViewerHook
 * Just allows user to add in any logs they feel they need on the log viewer
 *
 * NOTE: The log file will need web access to view.  This is given by the root
 * of the folder to read the contents and files of with:
 * chmod -R 755 <foldername>
 * list translates in ls -l to:
 * drwxr-xr-x
 * Also the file will need to be readable by everybody:
 * chmod +r <filename>
 */
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
					$logfile = "/var/log/syslog";
					$shortdesc = "System Log";
					if (file_exists($ftpstart.$logfile))
						$arguments['files'][$name][$shortdesc] = $ftpstart.$logfile;
					else
						$logfile = "/var/log/messages";
							if (file_exists($ftpstart.$logfile))
								$arguments['files'][$name][$shortdesc] = $ftpstart.$logfile;
			}
		}
}
$LogViewerHook = new LogViewerHook();
// Hook Event
$HookManager->register('LOG_VIEWER_HOOK', array($LogViewerHook, 'LogViewerAdd'));
