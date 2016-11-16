<?php
/**
 * Handles the fog linux services
 *
 * PHP version 5
 *
 * @category FOGService
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles the fog linux services
 *
 * @category FOGService
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class FOGService extends FOGBase
{
    /**
     * The path for the log
     *
     * @var string
     */
    public static $logpath = '';
    /**
     * Device (tty) to output to
     *
     * @var string
     */
    public static $dev = '';
    /**
     * The log file name.
     *
     * @var string
     */
    public static $log = '';
    /**
     * Sleep time
     *
     * @var int
     */
    public static $zzz = '';
    /**
     * Process references
     *
     * @var array
     */
    public $procRef = array();
    /**
     * Process pipes
     *
     * @var array
     */
    public $procPipes = array();
    /**
     * Tests that the passed files and sizes are the same
     *
     * @param mixed  $size_a The size of the first file
     * @param mixed  $size_b The size of the second file
     * @param string $file_a The name of the first file
     * @param string $file_b The name of the second file
     *
     * @return bool
     */
    private static function _filesAreEqual(
        $size_a,
        $size_b,
        $file_a,
        $file_b
    ) {
        if ($size_a != $size_b) {
            return false;
        }
        return true;
    }
    /**
     * Initializes the FOGService class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $logpath = trim(trim(self::getSetting('SERVICE_LOG_PATH'), '/'));
        if (!$logpath) {
            $logpath = 'opt/fog/log';
        }
        self::$logpath = sprintf(
            '/%s/',
            $logpath
        );
    }
    /**
     * Checks if the node runnning this is indeed the master
     *
     * @return array
     */
    protected function checkIfNodeMaster()
    {
        self::getIPAddress();
        $Nodes = self::getClass('StorageNodeManager')
            ->find(
                array(
                    'isMaster' => 1,
                    'isEnabled' => 1,
                )
            );
        $StorageNodes = array();
        foreach ((array)$Nodes as &$StorageNode) {
            if (!$StorageNode->isValid()) {
                continue;
            }
            $ip = self::$FOGCore->resolveHostname(
                $StorageNode->get('ip')
            );
            if (!in_array($ip, self::$ips)) {
                continue;
            }
            $StorageNodes[] = $StorageNode;
            $MasterIDs[] = $StorageNode->get('id');
        }
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTERS',
            array(
                'StorageNodes' => &$StorageNodes,
                'FOGServiceClass' => &$this,
                'MasterIDs' => &$MasterIDs
            )
        );
        if (count($StorageNodes) > 0) {
            return $StorageNodes;
        }
        throw new Exception(
            _(' | This is not the master node')
        );
    }
    /**
     * Wait to ensure the network interface is ready
     *
     * @return void
     */
    public function waitInterfaceReady()
    {
        self::getIPAddress();
        if (!count(self::$ips)) {
            self::outall(
                _('Interface not ready, waiting.')
            );
            sleep(10);
            $this->waitInterfaceReady();
        }
        foreach (self::$ips as &$ip) {
            self::outall(
                _("Interface Ready with IP Address: $ip")
            );
            unset($ip);
        }
    }
    /**
     * Wait to ensure the DB is ready
     *
     * @return void
     */
    public function waitDbReady()
    {
        if (self::$DB->getLink()) {
            return;
        }
        self::outall(
            sprintf(
                'FOGService: %s - %s',
                get_class($this),
                _('Waiting for mysql to be available')
            )
        );
        sleep(10);
        $this->waitDbReady();
    }
    /**
     * Displays the banner for fog services
     *
     * @return void
     */
    public function getBanner()
    {
        ob_start();
        echo "\n";
        echo "     ___           ___           ___     \n";
        echo "    /\  \         /\  \         /\  \    \n";
        echo "   /::\  \       /::\  \       /::\  \   \n";
        echo "  /:/\:\  \     /:/\:\  \     /:/\:\  \  \n";
        echo " /::\-\:\  \   /:/  \:\  \   /:/  \:\  \ \n";
        echo '/:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\\';
        echo "\n";
        echo "\/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/\n";
        echo "     \:\__\    \:\  /:/  /   \:\ \:\__\  \n";
        echo "      \/__/     \:\/:/  /     \:\/:/  /  \n";
        echo "                 \::/  /       \::/  /   \n";
        echo "                  \/__/         \/__/    \n";
        echo "\n";
        echo "#########################################\n";
        echo "#     Free Computer Imaging Solution    #\n";
        echo "#     Credits:                          #\n";
        echo "#     http://fogproject.org/credits     #\n";
        echo "#     GNU GPL Version 3                 #\n";
        echo "#########################################\n";
        self::outall(ob_get_clean());
    }
    /**
     * Outputs the string passed
     *
     * @param string $string the string to output
     *
     * @return void
     */
    public function outall($string)
    {
        self::out("$string\n", static::$dev);
        self::wlog("$string\n", static::$log);
        return;
    }
    /**
     * Outputs the string to the tty/device
     *
     * @param string $string the string to output
     * @param string $device the place to output
     *
     * @return void
     */
    protected static function out($string, $device)
    {
        if (!$fh = fopen($device, 'wb')) {
            return;
        }
        if (fwrite($fh, "$string\n") === false) {
            return;
        }
        fclose($fh);
    }
    /**
     * Get's the current datetime
     *
     * @return string
     */
    protected static function getDateTime()
    {
        return self::niceDate()->format('m-d-y g:i:s a');
    }
    /**
     * Outputs the passed string to the log
     *
     * @param string $string the string to write to log
     * @param string $path   the log path to write to
     *
     * @return void
     */
    protected static function wlog($string, $path)
    {
        if (file_exists($path)) {
            $filesize = (double)self::getFilesize($path);
            $max_size = (double)self::getSetting('SERVICE_LOG_SIZE');
            if (!$max_size) {
                $max_size = 500000;
            }
            if ($filesize >= $max_size) {
                unlink($path);
            }
        }
        if (!$fh = fopen($path, 'ab')) {
            self::out(
                "\n * Error: Unable to open file: $path\n",
                static::$dev
            );
        }
        $test = fwrite(
            $fh,
            sprintf(
                '[%s] %s',
                self::getDateTime(),
                $string
            )
        );
        if (false === $test) {
            self::out(
                "\n * Error: Unable to write to file: $path\n",
                static::$dev
            );
        }
        fclose($fh);
    }
    /**
     * Attempts to start the service
     *
     * @return void
     */
    public function serviceStart()
    {
        self::outall(
            sprintf(
                ' * Starting %s Service',
                get_class($this)
            )
        );
        self::outall(
            sprintf(
                ' * Checking for new items every %s seconds',
                static::$zzz
            )
        );
        self::outall(' * Starting service loop');
        return;
    }
    /**
     * Runs the service
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->waitDbReady();
        $tmpTime = self::getSetting(static::$sleeptime);
        if (static::$zzz != $tmpTime) {
            static::$zzz = $tmpTime;
            self::outall(
                sprintf(
                    " | Sleep time has changed to %s seconds",
                    static::$zzz
                )
            );
        }
        self::out(
            '',
            static::$dev
        );
        self::out(
            '+---------------------------------------------------------',
            static::$dev
        );
    }
    /**
     * Replicates data without having to keep repeating
     *
     * @param int    $myStorageGroupID this servers groupid
     * @param int    $myStorageNodeID  this servers nodeid
     * @param object $Obj              that is trying to send data
     * @param bool   $master           master->master or master->nodes
     *
     * @return void
     */
    protected function replicateItems(
        $myStorageGroupID,
        $myStorageNodeID,
        $Obj,
        $master = false
    ) {
        unset(
            $username,
            $password,
            $ip,
            $remItem,
            $myItem,
            $limitmain,
            $limitsend,
            $limit,
            $includeFile
        );
        $itemType = $master ? 'group' : 'node';
        $groupID = $myStorageGroupID;
        if ($master) {
            $groupID = $Obj->get('storagegroups');
        }
        $findWhere = array(
            'isEnabled' => 1,
            'storagegroupID' => $groupID,
        );
        if ($master) {
            $findWhere['isMaster'] = 1;
        }
        $StorageNode = self::getClass('StorageNode', $myStorageNodeID);
        if (!$StorageNode->isValid() || !$StorageNode->get('isMaster')) {
            throw new Exception(
                sprintf(
                    ' * %s',
                    _('I am not the master for this group')
                )
            );
        }
        $objType = get_class($Obj);
        $groupOrNodeCount = self::getClass('StorageNodeManager')
            ->count($findWhere);
        $counttest = 2;
        if (!$master) {
            $groupOrNodeCount--;
            $counttest = 1;
        }
        if ($groupOrNodeCount < $counttest) {
            self::outall(
                sprintf(
                    ' * %s %s %s %s',
                    _('Not syncing'),
                    $objType,
                    _('between'),
                    _("{$itemType}s")
                )
            );
            self::outall(
                sprintf(
                    ' | %s %s: %s',
                    $objType,
                    _('Name'),
                    $Obj->get('name')
                )
            );
            self::outall(
                sprintf(
                    ' | %s.',
                    _('There are no other members to sync to')
                )
            );
        } else {
            self::outall(
                sprintf(
                    ' * %s %s %s %s %s',
                    _('Found'),
                    _($objType),
                    _('to transfer to'),
                    $groupOrNodeCount,
                    (
                        $groupOrNodeCount != 1 ?
                        _("{$itemType}s") :
                        _($itemType)
                    )
                )
            );
            self::outall(
                sprintf(
                    ' | %s %s: %s',
                    _($objType),
                    _('Name'),
                    $Obj->get('name')
                )
            );
            $getPathOfItemField = 'ftppath';
            $getFileOfItemField = 'path';
            if ($objType == 'Snapin') {
                $getPathOfItemField = 'snapinpath';
                $getFileOfItemField = 'file';
            }
            $PotentialStorageNodes = array_diff(
                (array)self::getSubObjectIDs(
                    'StorageNode',
                    $findWhere,
                    'id'
                ),
                (array)$myStorageNodeID
            );
            $myDir = sprintf(
                '/%s/',
                trim($StorageNode->get($getPathOfItemField), '/')
            );
            $myFile = basename($Obj->get($getFileOfItemField));
            $myAdd = "$myDir$myFile";
            $myAddItem = false;
            $PotentialNodes = self::getClass('StorageNodeManager')
                ->find(
                    array(
                        'id' => $PotentialStorageNodes
                    )
                );
            foreach ((array)$PotentialNodes as $i => &$PotentialStorageNode) {
                usleep(50000);
                if (!$PotentialStorageNode->isValid()) {
                    continue;
                }
                $groupID = $PotentialStorageNode->get('storagegroupID');
                if ($master
                    && $groupID == $myStorageGroupID
                ) {
                    continue;
                }
                if (isset($this->procRef[$itemType])
                    && isset($this->procRef[$itemType][$Obj->get('name')])
                    && isset($this->procRef[$itemType][$Obj->get('name')][$i])
                ) {
                    $isRunning = $this->isRunning(
                        $this->procRef[$itemType][$Obj->get('name')][$i]
                    );
                    if ($isRunning) {
                        self::outall(
                            sprintf(
                                '| %s: %d',
                                _('Replication already running with PID'),
                                $this->getPID(
                                    $this->procRef[$itemType][$Obj->get('name')][$i]
                                )
                            )
                        );
                        continue;
                    }
                }
                if (!file_exists($myAdd)
                    || !is_readable($myAdd)
                ) {
                    self::outall(
                        sprintf(
                            ' * %s %s %s %s',
                            _('Not syncing'),
                            $objType,
                            _('between'),
                            _("{$itemType}s")
                        )
                    );
                    self::outall(
                        sprintf(
                            ' | %s %s: %s',
                            _($objType),
                            _('Name'),
                            $Obj->get('name')
                        )
                    );
                    self::outall(
                        sprintf(
                            ' | %s.',
                            _('File or path cannot be reached')
                        )
                    );
                    continue;
                }
                self::$FOGFTP
                    ->set(
                        'username',
                        $PotentialStorageNode->get('user')
                    )->set(
                        'password',
                        $PotentialStorageNode->get('pass')
                    )->set(
                        'host',
                        $PotentialStorageNode->get('ip')
                    );
                if (!self::$FOGFTP->connect()) {
                    self::outall(
                        sprintf(
                            ' * %s %s',
                            _('Cannot connect to'),
                            $PotentialStorageNode->get('name')
                        )
                    );
                    continue;
                }
                $nodename = $PotentialStorageNode->get('name');
                $username = self::$FOGFTP->get('username');
                $password = self::$FOGFTP->get('password');
                $encpassword = urlencode($password);
                $ip = self::$FOGFTP->get('host');
                $removeDir = sprintf(
                    '/%s/',
                    trim(
                        $PotentialStorageNode->get($getPathOfItemField),
                        '/'
                    )
                );
                $removeFile = $myFile;
                $limitmain = $this->byteconvert(
                    $StorageNode->get('bandwidth')
                );
                $limitsend = $this->byteconvert(
                    $PotentialStorageNode->get('bandwidth')
                );
                if ($limitmain > 0) {
                    $limitset = "set net:limit-total-rate 0:$limitmain;";
                }
                if ($limitsend > 0) {
                    $limitset .= "set net:limit-rate 0:$limitsend;";
                }
                unset($limit);
                $limit = $limitset;
                unset($limitset);
                unset($remItem);
                unset($includeFile);
                $ftpstart = "ftp://$username:$encpassword@$ip";
                if (is_file($myAdd)) {
                    $remItem = dirname("$removeDir$removeFile");
                    $opts = '-R -i';
                    $includeFile = $myFile;
                    if (!$myAddItem) {
                        $myAddItem = dirname($myAdd);
                    }
                    $localfilescheck[0] = $myAdd;
                    $remotefilescheck[0] = sprintf(
                        '%s/%s',
                        $remItem,
                        $removeFile
                    );
                } elseif (is_dir($myAdd)) {
                    $remItem = "$removeDir$removeFile";
                    $localfilescheck = glob("$myAdd/*");
                    $remotefilescheck = self::$FOGFTP->nlist($remItem);
                    $opts = '-R';
                    $includeFile = '';
                    if (!$myAddItem) {
                        $myAddItem = $myAdd;
                    }
                }
                sort($localfilescheck);
                sort($remotefilescheck);
                $test = -1;
                foreach ((array)$localfilescheck as $j => &$localfile) {
                    usleep(50000);
                    $index = $this->arrayFind(
                        basename($localfile),
                        $remotefilescheck
                    );
                    if (false === $index) {
                        continue;
                    }
                    $filesize_main = self::getFilesize($localfile);
                    $filesize_rem = self::$FOGFTP->size(
                        $remotefilescheck[$index]
                    );
                    $filesEqual = self::_filesAreEqual(
                        $filesize_main,
                        $filesize_rem,
                        $localfile,
                        $ftpstart.$remotefilescheck[$index]
                    );
                    if (!$filesEqual) {
                        self::outall(
                            sprintf(
                                ' | %s.',
                                _('Files do not match')
                            )
                        );
                        self::outall(
                            sprintf(
                                '* %s: %s',
                                _('Deleting remote file'),
                                $remotefilescheck[$index]
                            )
                        );
                        self::$FOGFTP->delete($remotefilescheck[$index]);
                        $test = false;
                    } else {
                        self::outall(
                            sprintf(
                                ' | %s: %s %s %s %s',
                                $Obj->get('name'),
                                _('No need to sync'),
                                basename($localfile),
                                _('file to'),
                                $nodename
                            )
                        );
                        if ($test !== false) {
                            $test = true;
                        }
                    }
                    unset($localfile);
                }
                self::$FOGFTP->close();
                if ($test === true) {
                    continue;
                }
                $logname = sprintf(
                    '%s.transfer.%s.log',
                    static::$log,
                    $nodename
                );
                if (!$i) {
                    self::outall(
                        sprintf(
                            ' * %s',
                            _('Starting Sync Actions')
                        )
                    );
                }
                $this->killTasking(
                    $i,
                    $itemType,
                    $Obj->get('name')
                );
                $myAddItem = escapeshellarg($myAddItem);
                $remItem = escapeshellarg($remItem);
                $cmd = "lftp -e 'set ftp:list-options -a;set net:max-retries ";
                $cmd .= "10;set net:timeout 30; $limit mirror -c ";
                $cmd .= "$opts ";
                if (!empty($includeFile)) {
                    $includeFile = escapeshellarg($includeFile);
                    $cmd .= "$includeFile ";
                }
                $cmd .= "--ignore-time -vvv --exclude \"dev/\" --exclude \"ssl/\" ";
                $cmd .= "--exclude \"CA\" --delete-first $myAddItem ";
                $cmd .= "$remItem; ";
                $cmd .= "exit' -u $username,$password $ip";
                $cmd2 = "lftp -e 'set ftp:list-options -a;set net:max-retries ";
                $cmd2 .= "10;set net:timeout 30; $limit mirror -c ";
                $cmd2 .= "$opts ";
                if (!empty($includeFile)) {
                    $cmd2 .= "$includeFile ";
                }
                $cmd2 .= "--ignore-time -vvv --exclude \"dev/\" --exclude \"ssl/\" ";
                $cmd2 .= "--exclude \"CA\" --delete-first $myAddItem ";
                $cmd2 .= "\\'$remItem\\'; ";
                $cmd2 .= "exit' -u $username,[Protected] $ip";
                self::outall(" | CMD:\n\t\t\t$cmd2");
                unset($includeFile, $remItem, $myAddItem);
                $this->startTasking(
                    $cmd,
                    $logname,
                    $i,
                    $itemType,
                    $Obj->get('name')
                );
                self::outall(
                    sprintf(
                        ' * %s %s %s',
                        _('Started sync for'),
                        $objType,
                        $Obj->get('name')
                    )
                );
                unset($PotentialStorageNode);
            }
        }
    }
    /**
     * Starts taskings
     *
     * @param string $cmd      The command to start
     * @param string $logname  The name of the log to write to
     * @param int    $index    The index to store tasking reference
     * @param mixed  $itemType The type of the item
     * @param mixed  $filename Filename extra
     *
     * @return void
     */
    public function startTasking(
        $cmd,
        $logname,
        $index = 0,
        $itemType = false,
        $filename = false
    ) {
        if (isset($this->altLog)) {
            $log = $this->altLog;
        } else {
            $log = static::$log;
        }
        $descriptor = array(
            0 => array('pipe', 'r'),
            1 => array('file', $logname, 'a'),
            2 => array('file', $log, 'a')
        );
        if ($itemType === false) {
            $this->procRef[$index] = proc_open(
                $cmd,
                $descriptor,
                $pipes
            );
            $this->procPipes[$index] = $pipes;
        } else {
            $this->procRef[$itemType][$filename][$index] = proc_open(
                $cmd,
                $descriptor,
                $pipes
            );
            $this->procPipes[$itemType][$filename][$index] = $pipes;
        }
    }
    /**
     * Kills all child processes
     *
     * @param int   $pid the pid to scan
     * @param mixed $sig the signal to kill with
     *
     * @return void
     */
    public function killAll($pid, $sig)
    {
        exec("ps -ef|awk '\$3 == '$pid' {print \$2}'", $output, $ret);
        if ($ret) {
            return false;
        }
        while (list(, $t) = each($output)) {
            if ($t != $pid) {
                $this->killAll($t, $sig);
            }
        }
        posix_kill($pid, $sig);
    }
    /**
     * Kills the tasking
     *
     * @param int    $index    the index for the item to look into
     * @param mixed  $itemType the type of the item
     * @param string $filename the filename to close out
     *
     * @return bool
     */
    public function killTasking(
        $index = 0,
        $itemType = false,
        $filename = false
    ) {
        if ($itemType === false) {
            foreach ((array)$this->procPipes[$index] as $i => &$close) {
                fclose($close);
                unset($close);
            }
            unset($this->procPipes[$index]);
            if ($this->isRunning($this->procRef[$index])) {
                $pid = $this->getPID($this->procRef[$index]);
                if ($pid) {
                    $this->killAll($pid, SIGTERM);
                }
                proc_terminate($this->procRef[$index], SIGTERM);
                proc_close($this->procRef[$index]);
                return (bool)$this->isRunning($this->procRef[$index]);
            } elseif ($this->isRunning($this->procRef)) {
                $pid = $this->getPID($this->procRef);
                if ($pid) {
                    $this->killAll($pid, SIGTERM);
                }
                proc_terminate($this->procRef, SIGTERM);
                proc_close($this->procRef);
                return (bool)$this->isRunning($this->procRef);
            }
        } else {
            $procRef = $this->procRef[$itemType][$filename][$index];
            $pipes = $this->procPipes[$itemType][$filename][$index];
            $isRunning = $this->isRunning(
                $procRef
            );
            if ($isRunning) {
                $pid = $this->getPID(
                    $procRef
                );
                if ($pid) {
                    $this->killAll($pid, SIGTERM);
                }
                proc_terminate($procRef, SIGTERM);
            } else {
                return true;
            }
            proc_close($procRef);
            foreach ((array)$pipes as $i => &$close) {
                fclose($close);
                unset($close);
            }
            unset($pipes);
            return (bool)$this->isRunning(
                $procRef
            );
        }
    }
    /**
     * Gets the pid of the running reference
     *
     * @param resouce $procRef the reference to check
     *
     * @return int
     */
    public function getPID($procRef)
    {
        if (!$procRef) {
            return false;
        }
        $ar = proc_get_status($procRef);
        return $ar['pid'];
    }
    /**
     * Checks if the passed reference is still running
     *
     * @param resource $procRef the reference to check
     *
     * @return bool
     */
    public function isRunning($procRef)
    {
        if (!$procRef) {
            return false;
        }
        $ar = proc_get_status($procRef);
        return $ar['running'];
    }
}
