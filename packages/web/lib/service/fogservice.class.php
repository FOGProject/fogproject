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
    public $procRef = [];
    /**
     * Process pipes
     *
     * @var array
     */
    public $procPipes = [];
    /**
     * Tests that the passed files and sizes are the same
     *
     * @param mixed  $size_a The size of the first file
     * @param mixed  $size_b The size of the second file
     * @param string $file_a The name of the first file
     * @param string $file_b The name of the second file
     * @param bool   $avail  Is url available.
     *
     * @return bool
     */
    private static function _filesAreEqual(
        $size_a,
        $size_b,
        $file_a,
        $file_b,
        $avail
    ) {
        if ($size_a != $size_b) {
            return false;
        }
        if (false === $avail) {
            if ($size_a < 1047685760) {
                $remhash = md5_file($file_b);
                $lochash = md5_file($file_a);
                return ($remhash == $lochash);
            }
            return file_exists($file_b) && file_exists($file_a);
        }
        $hashLoc = self::getHash($file_a);
        $hashRem = $file_b;
        $hashCom = ($hashLoc == $hashRem);
        return $hashCom;
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
        Route::listem(
            'storagenode',
            [
                'isMaster' => 1,
                'isEnabled' => 1
            ]
        );
        $StorageNodes = [];
        $StorageNodesFound = json_decode(
            Route::getData()
        );
        foreach ($StorageNodesFound->data as &$StorageNode) {
            if (!$StorageNode->online) {
                continue;
            }
            $ip = self::resolveHostname(
                $StorageNode->ip
            );
            if (!in_array($ip, self::$ips)) {
                continue;
            }
            $StorageNodes[] = $StorageNode;
            $MasterIDs[] = $StorageNode->id;
            unset($StorageNode);
        }
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTERS',
            [
                'StorageNodes' => &$StorageNodes,
                'FOGServiceClass' => &$this,
                'MasterIDs' => &$MasterIDs
            ]
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
        if (DatabaseManager::getLink()) {
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
        echo "==================================\n";
        echo "===        ====    =====      ====\n";
        echo "===  =========  ==  ===   ==   ===\n";
        echo "===  ========  ====  ==  ====  ===\n";
        echo "===  ========  ====  ==  =========\n";
        echo "===      ====  ====  ==  =========\n";
        echo "===  ========  ====  ==  ===   ===\n";
        echo "===  ========  ====  ==  ====  ===\n";
        echo "===  =========  ==  ===   ==   ===\n";
        echo "===  ==========    =====      ====\n";
        echo "==================================\n";
        echo "===== Free Opensource Ghost ======\n";
        echo "==================================\n";
        echo "============ Credits =============\n";
        echo "= https://fogproject.org/Credits =\n";
        echo "==================================\n";
        echo "== Released under GPL Version 3 ==\n";
        echo "==================================\n";
        echo "\n";
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
        self::wlog("$string\n", static::$log);
        return;
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
        $fh = fopen($path, 'ab');
        fwrite(
            $fh,
            sprintf(
                '[%s] %s',
                self::getDateTime(),
                $string
            )
        );
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
    }
    /**
     * Replicates data without having to keep repeating
     *
     * @param int    $myStorageGroupID this servers groupid
     * @param int    $myStorageNodeID  this servers nodeid
     * @param object $Obj              that is trying to send data
     * @param bool   $master           master->master or master->nodes
     * @param mixed  $fileOverride     file override.
     *
     * @return void
     */
    protected function replicateItems(
        $myStorageGroupID,
        $myStorageNodeID,
        $Obj,
        $master = false,
        $fileOverride = false
    ) {
        $itemType = $master ? 'group' : 'node';
        $groupID = $myStorageGroupID;
        if ($master) {
            $groupID = $Obj->get('storagegroups');
        }
        $find = [
            'isEnabled' => 1,
            'storagegroupID' => $groupID
        ];
        if ($master) {
            $find['isMaster'] = [1];
        }
        Route::indiv(
            'storagenode',
            $myStorageNodeID
        );
        $myStorageNode = json_decode(
            Route::getData()
        );
        if (!$myStorageNode->isMaster) {
            throw new Exception(
                _('This is not the master for this group')
            );
        }
        if (!$myStorageNode->online) {
            throw new Exception(
                _('This node does not appear to be online')
            );
        }
        Route::listem(
            'storagenode',
            $find
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        $objType = get_class($Obj);
        $groupOrNodeCount = count($StorageNodes->data ?: []);
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
                    $fileOverride ? _('File') : _($objType),
                    _('Name'),
                    $fileOverride ? $fileOverride : $Obj->get('name')
                )
            );
            $getPathOfItemField = 'ftppath';
            $getFileOfItemField = 'path';
            if ($objType == 'Snapin') {
                $getPathOfItemField = 'snapinpath';
                $getFileOfItemField = 'file';
            }
            $myDir = sprintf(
                '/%s/',
                trim($myStorageNode->{$getPathOfItemField}, '/')
            );
            if (false === $fileOverride) {
                $myFile = basename($Obj->get($getFileOfItemField));
            } else {
                $myFile = $fileOverride;
            }
            $myAdd = "$myDir$myFile";
            $myAddItem = false;
            foreach ($StorageNodes->data as &$StorageNode) {
                if ($StorageNode->id == $myStorageNodeID) {
                    continue;
                }
                if (!$StorageNode->online) {
                    self::outall(
                        sprintf(
                            ' | %s server does not appear to be online.',
                            $StorageNode->name
                        )
                    );
                    continue;
                }
                $groupID = $StorageNode->storagegroupID;
                if ($master
                    && $groupID == $myStorageGroupID
                ) {
                    continue;
                }
                if ($fileOverride) {
                    $name = $fileOverride;
                    $randind = "abcdef$i";
                } else {
                    $name = $Obj->get('name');
                    $randind = $i;
                }
                if (isset($this->procRef[$itemType])
                    && isset($this->procRef[$itemType][$name])
                    && isset($this->procRef[$itemType][$name][$randind])
                ) {
                    $isRunning = $this->isRunning(
                        $this->procRef[$itemType][$name][$randind]
                    );
                    if ($isRunning) {
                        self::outall(
                            sprintf(
                                '| %s: %d',
                                _('Replication already running with PID'),
                                $this->getPID(
                                    $this->procRef[$itemType][$name][$randind]
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
                            $fileOverride ? _('File') : _($objType),
                            _('Name'),
                            $name
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
                $testip = $StorageNode->ip;
                $url = sprintf(
                    '%s://%s/fog/status/gethash.php',
                    self::$httpproto,
                    $StorageNode->ip
                );
                self::$FOGFTP->username = $StorageNode->user;
                self::$FOGFTP->password = $StorageNode->pass;
                self::$FOGFTP->host = $StorageNode->ip;
                if (!self::$FOGFTP->connect()) {
                    self::outall(
                        sprintf(
                            ' * %s %s',
                            _('Cannot connect to'),
                            $StorageNode->name
                        )
                    );
                    continue;
                }
                $nodename = $StorageNode->name;
                $username = self::$FOGFTP->username;
                $password = self::$FOGFTP->password;
                $ip = self::$FOGFTP->host;
                $encpassword = urlencode($password);
                $removeDir = sprintf(
                    '/%s/',
                    trim(
                        $StorageNode->{$getPathOfItemField},
                        '/'
                    )
                );
                $removeFile = $myFile;
                $limitmain = self::byteconvert(
                    $myStorageNode->bandwidth
                );
                $limitsend = self::byteconvert(
                    $StorageNode->bandwidth
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
                    $removeFile = basename($removeFile);
                    $opts = '-R -i';
                    $includeFile = basename($myFile);
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
                    $path = realpath($myAdd);
                    $localfilescheck = self::globrecursive(
                        "$path/**{,.}*[!.,!..]",
                        GLOB_BRACE
                    );
                    $remotefilescheck = self::$FOGFTP->listrecursive($remItem);
                    $opts = '-R';
                    $includeFile = '';
                    if (!$myAddItem) {
                        $myAddItem = $myAdd;
                    }
                }
                sort($localfilescheck);
                sort($remotefilescheck);
                $localfilescheck = array_values(
                    array_filter(
                        array_unique($localfilescheck)
                    )
                );
                $remotefilescheck = array_values(
                    array_filter(
                        array_unique($remotefilescheck)
                    )
                );
                $testavail = -1;
                foreach ((array)$localfilescheck as $j => &$localfile) {
                    $allsynced = true;
                    $avail = true;
                    $index = self::arrayFind(
                        basename($localfile),
                        (array)$remotefilescheck
                    );
                    $testavail = array_filter(
                        self::$FOGURLRequests->isAvailable($testip, 1, 80, 'tcp')
                    );
                    $testavail = array_shift($testavail);
                    if (!$testavail) {
                        $avail = false;
                    }
                    $filesize_main = self::getFilesize($localfile);
                    $file = $remotefilescheck[$index];
                    if ($avail) {
                        $sizeurl = str_replace('gethash', 'getsize', $url);
                        $remsize = self::$FOGURLRequests->process(
                            $sizeurl,
                            'POST',
                            ['file' => base64_encode($file)]
                        );
                        $filesize_rem = array_shift($remsize);
                        $res = self::$FOGURLRequests->process(
                            $url,
                            'POST',
                            ['file' => base64_encode($file)]
                        );
                        $res = array_shift($res);
                    } elseif (!$avail) {
                        if ($file) {
                            $filesize_rem = self::$FOGFTP->size(
                                $file
                            );
                        }
                        $res = sprintf(
                            '%s%s',
                            $ftpstart,
                            $file
                        );
                    }
                    $filesEqual = self::_filesAreEqual(
                        $filesize_main,
                        $filesize_rem,
                        $localfile,
                        $res,
                        $avail
                    );
                    if (!$filesEqual) {
                        $allsynced = false;
                        self::outall(
                            ' | '
                            . _('Files do not match on server:')
                            . ' '
                            . $StorageNode->name
                        );
                        if (!$remotefilescheck[$index]) {
                            self::outall(
                                ' | '
                                . basename($localfile)
                                . ' '
                                . _('File does not exist.')
                                . ' '
                                . $StorageNode->name
                            );
                        } else {
                            self::outall(
                                sprintf(
                                    ' | %s: %s',
                                    _('Deleting remote file'),
                                    $remotefilescheck[$index]
                                )
                            );
                            self::$FOGFTP->delete($remotefilescheck[$index]);
                        }
                        $test = false;
                    } else {
                        self::outall(
                            sprintf(
                                ' | %s: %s %s %s %s',
                                $name,
                                _('No need to sync'),
                                basename($localfile),
                                _('file to'),
                                $nodename
                            )
                        );
                        self::$FOGFTP->close();
                        continue;
                    }
                    unset($localfile);
                }
                self::$FOGFTP->close();
                if ($allsynced) {
                    self::outall(_(' * All files synced for this item.'));
                    continue;
                }
                $logname = sprintf(
                    '%s.%s.transfer.%s.log',
                    rtrim(
                        substr(
                            static::$log,
                            0,
                            -4
                        ),
                        '.'
                    ),
                    $Obj->get('name'),
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
                    $randind,
                    $itemType,
                    $name
                );
                $myAddItem = escapeshellarg($myAddItem);
                $remItem = escapeshellarg($remItem);
                $logname = escapeshellarg($logname);
                $myAddItem = trim($myAddItem, "'");
                $myAddItem = sprintf(
                    '"%s"',
                    $myAddItem
                );
                $remItem = trim($remItem, "'");
                $remItem = sprintf(
                    '"%s"',
                    $remItem
                );
                $logname = trim($logname, "'");
                $logname = sprintf(
                    '"%s"',
                    $logname
                );
                $cmd = "lftp -e 'set xfer:log 1; set xfer:log-file $logname;";
                $cmd .= "set ftp:list-options -a;set net:max-retries ";
                $cmd .= "10;set net:timeout 30; $limit mirror -c --parallel=20 ";
                $cmd .= "$opts ";
                if (!empty($includeFile)) {
                    $includeFile = escapeshellarg($includeFile);
                    $includeFile = trim($includeFile, "'");
                    $includeFile = sprintf(
                        '"%s"',
                        $includeFile
                    );
                    $cmd .= "$includeFile ";
                }
                $cmd .= "--ignore-time -vvv --exclude \".srvprivate\" ";
                $cmd .= "$myAddItem $remItem;";
                $cmd2 = sprintf(
                    "%s exit' -u $username,[Protected] $ip",
                    $cmd
                );
                $cmd .= "exit' -u $username,$password $ip";
                self::outall(" | CMD:\n\t\t\t$cmd2");
                unset($includeFile, $remItem, $myAddItem);
                $this->startTasking(
                    $cmd,
                    $logname,
                    $randind,
                    $itemType,
                    $name
                );
                self::outall(
                    sprintf(
                        ' * %s %s %s',
                        _('Started sync for'),
                        $objType,
                        $name
                    )
                );
                unset($StorageNode);
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
        self::wlog(_('Task started'), $logname);
        $descriptor = [
            0 => ['pipe', 'r'],
            2 => ['file', $log, 'a']
        ];
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
    /**
     * Local file glob recursive getter.
     *
     * @param string $pattern a Pattern for globbing onto.
     * @param mixed  $flags   any required flags.
     *
     * @return array
     */
    public static function globrecursive(
        $pattern,
        $flags = 0
    ) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as &$dir) {
            $files = array_merge(
                (array)$files,
                self::globrecursive(
                    $dir . '/' . basename($pattern),
                    $flags
                )
            );
            unset($file);
        }
        return $files;
    }
}
