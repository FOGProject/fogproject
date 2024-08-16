<?php
/**
 * Just allows user to add in any logs they feel they need on the log viewer.
 *
 * NOTE: The log file will need web access to view.  This is given by the root
 * of the folder to read the contents and files of with:
 * chmod -R 755 <foldername>
 * list translates in ls -l to:
 * drwxr-xr-x
 * Also the file will need to be readable by everybody:
 * chmod +r <filename>
 *
 * PHP version 5
 *
 * @category LogViewerHook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Just allows user to add in any logs they feel they need on the log viewer.
 *
 * NOTE: The log file will need web access to view.  This is given by the root
 * of the folder to read the contents and files of with:
 * chmod -R 755 <foldername>
 * list translates in ls -l to:
 * drwxr-xr-x
 * Also the file will need to be readable by everybody:
 * chmod +r <filename>
 *
 * @category LogViewerHook
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LogViewerHook extends Hook
{
    /**
     * The name of the hook.
     *
     * @var string
     */
    public $name = 'LogViewerHook';
    /**
     * The description of the hook.
     *
     * @var string
     */
    public $description = 'Allows adding/removing log viewer files to the system';
    /**
     * Is this active?
     *
     * @var bool
     */
    public $active = false;
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'LOG_VIEWER_HOOK',
            [$this, 'logViewerAdd']
        )->register(
            'LOG_FOLDERS',
            [$this, 'logFolderAdd']
        );
    }
    /**
     * Function to add logs.
     *
     * @param mixed $arguments The items to modify for adding.
     *
     * @return void
     */
    public function logViewerAdd($arguments)
    {
        self::$FOGSSH->username = $arguments['StorageNode']->user;
        self::$FOGSSH->password = $arguments['StorageNode']->pass;
        self::$FOGSSH->host = $arguments['StorageNode']->ip;
        if (!self::$FOGSSH->connect()) {
            return;
        }
        $fogfiles = self::$FOGSSH->scanFilesystem("/var/log");
        self::$FOGSSH->disconnect();
        $systemlog = preg_grep('#(syslog$|messages$)#', $fogfiles);
        $systemlog = array_shift($systemlog);
        if ($systemlog) {
            $arguments['files'][$arguments['StorageNode']->name]['System Log']
                = $systemlog;
        }
        $dnflog = preg_grep('#(dnf.log$)#', $fogfiles);
        $dnflog = array_shift($dnflog);
        if ($dnflog) {
            $arguments['files'][$arguments['StorageNode']->name]['DNF Log']
                = $dnflog;
        }
    }
    /**
     * Add folder to search.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function logFolderAdd($arguments)
    {
        $arguments['folders'][] = '/var/log/';
    }
}
