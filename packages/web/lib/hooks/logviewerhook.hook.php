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
class LogViewerHook extends Hook {
    public $name = 'LogViewerHook';
    public $description = 'Allows adding/removing log viewer files to the system';
    public $author = 'Tom Elliott/Lee Rowlett';
    public $active = false;
    public function LogViewerAdd($arguments) {
        $this->FOGFTP
            ->set('host',$arguments['StorageNode']->get('ip'))
            ->set('username',$arguments['StorageNode']->get('user'))
            ->set('password',$arguments['StorageNode']->get('pass'));
        if (!$this->FOGFTP->connect()) return;
        $fogfiles = array();
        $fogfiles = $this->FOGFTP->nlist('/var/log/');
        $this->FOGFTP->close();
        $systemlog = preg_grep('#(syslog$|messages$)#',$fogfiles);
        $systemlog = @array_shift($systemlog);
        if ($systemlog) $arguments['files'][$arguments['StorageNode']->get('name')]['System Log'] = $systemlog;
    }
    public function LogFolderAdd($arguments) {
        $arguments['folders'][] = '/var/log/';
    }
}
$LogViewerHook = new LogViewerHook();
// Hook Event
$HookManager->register('LOG_VIEWER_HOOK', array($LogViewerHook, 'LogViewerAdd'));
$HookManager->register('LOG_FOLDERS', array($LogViewerHook, 'LogFolderAdd'));
