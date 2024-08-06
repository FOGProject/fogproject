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
     * Node IPs we have in the database to check in service startup
     *
     * @var array
     */
    public static $knownips = [];
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
        Route::ids(
            'storagenode',
            ['isEnabled' => 1],
            'ip'
        );
        self::$knownips = json_decode(Route::getData(), true);
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
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
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
        self::getIPAddress(true);
        if (!count(self::$ips)
            || !array_intersect(self::$knownips, self::$ips)
            || !in_array(self::getSetting('FOG_WEB_HOST'), self::$ips)
        ) {
            self::outall(
                sprintf(
                    '%s: %s',
                    _('Interface not ready, waiting for it to come up'),
                    self::getSetting('FOG_WEB_HOST')
                )
            );
            sleep(10);
            $this->waitInterfaceReady();
            return;
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
    public static function getDateTime()
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
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        $fh = fopen($path, 'ab');
        if (!$fh) {
            return;
        }
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
        $unique = function ($item) {
            return array_keys(array_flip(array_filter($item)));
        };
        $itemType = $master ? 'group' : 'node';
        $groupID = $myStorageGroupID;
        $find = [
            'isEnabled' => [1],
            'storagegroupID' => $groupID
        ];
        if ($master) {
            $groupID = $Obj->get('storagegroups');
            $find['isMaster'] = [1];
        }
        Route::indiv('storagenode', $myStorageNodeID);
        $myStorageNode = json_decode(Route::getData());
        if (!$myStorageNode->isMaster) {
            throw new Exception(_('This is not the master for this group'));
        }
        if (!$myStorageNode->online) {
            throw new Exception(_('This node does not appear to be online'));
        }
        Route::listem('storagenode', $find);
        $StorageNodes = json_decode(Route::getData());
        $objType = get_class($Obj);
        $groupOrNodeCount = count($StorageNodes->data ?: []);
        $counttest = 2;
        if (!$master) {
            $groupOrNodeCount--;
            $counttest--;
        }
        if ($groupOrNodeCount < $counttest) {
            self::outall(
                ' * ' . _('Not syncing') . ' ' . $objType . ' '
                . _('between') . ' ' . _("{$itemType}s")
            );
            self::outall(
                ' | ' . $objType . ' ' . _('Name') . ': ' . $Obj->get('name')
            );
            self::outall(
                ' | ' . _('There are no members to sync to')
            );
            return;
        }
        self::outall(
            ' * ' . _('Found') . ' ' . _($objType) . ' ' . _('to transfer to')
            . ' ' . $groupOrNodeCount . ' '
            . (
                $groupOrNodeCount == 1 ?
                _($itemType) :
                _("{$itemType}s")
            )
        );
        $nfilename = ($fileOverride ?: $Obj->get('name'));
        self::outall(
            ' | ' . ($fileOverride ? _('File') : _($objType))
            . ' ' . _('Name') . ': ' . $nfilename
        );
        $pathField = 'ftppath';
        $fileField = 'path';
        if ($objType == 'Snapin') {
            $pathField = 'snapinpath';
            $fileField = 'file';
        }
        $myDir = '/' . trim($myStorageNode->{$pathField}, '/') . '/';
        $myFile = ($fileOverride ?: $Obj->get($fileField));
        $myAdd = "{$myDir}{$myFile}";
        $myAddItem = false;
        foreach ($StorageNodes->data as $i => &$StorageNode) {
            if ($StorageNode->id == $myStorageNodeID) {
                continue;
            }
            Route::indiv('storagenode', $StorageNode->id);
            $StorageNode = json_decode(Route::getData());
            if ($master && $StorageNode->storagegroupID == $myStorageGroupID) {
                continue;
            }
            if (!$StorageNode->online) {
                self::outall(
                    ' | ' . $StorageNode->name . ' '
                    . _('server does not appear to be online')
                );
                continue;
            }
            $name = $Obj->get('name');
            $randind = $i;
            if ($fileOverride) {
                $name = $fileOverride;
                $randind = "abcdef$i";
            }
            if (isset($this->procRef[$itemType])
                && isset($this->procRef[$itemType][$name])
                && isset($this->procRef[$itemType][$randind])
            ) {
                $isRunning = $this->isRunning(
                    $this->procRef[$itemType][$name][$randind]
                );
                if ($isRunning) {
                    $pid = $this->getPID(
                        $this->procRef[$itemType][$name][$randind]
                    );
                    self::outall(
                        ' | ' . _('Replication already running with PID')
                        . ': ' . $pid
                    );
                    continue;
                }
            }
            if (!file_exists($myAdd) || !is_readable($myAdd)) {
                self::outall(
                    ' * ' . _('Not syncing') . ' ' . $objType
                    . ' ' . _('between') . ' ' . _("{$itemType}s")
                );
                self::outall(
                    ' | ' . ($fileOverride ? _('File') : _($objType))
                    . ' ' . _('Name') . ': ' . $name
                );
                self::outall(
                    ' | ' . _('File or path cannot be reached')
                );
                continue;
            }
            $username = self::$FOGFTP->username = $StorageNode->user;
            $password = self::$FOGFTP->password = $StorageNode->pass;
            $ip = self::$FOGFTP->host = $StorageNode->ip;
            $sizeurl = sprintf(
                '%s://%s/fog/status/getsize.php',
                'http',
                $ip
            );
            $hashurl = sprintf(
                '%s://%s/fog/status/gethash.php',
                'http',
                $ip
            );
            $nodename = $StorageNode->name;
            if (!self::$FOGFTP->connect()) {
                self::outall(
                    ' * ' . _('Cannot connect to') . ' ' . $nodename
                );
                continue;
            }
            $encpassword = urlencode($password);
            $removeDir = '/' . trim($StorageNode->{$pathField}, '/') . '/';
            $removeFile = $myFile;
            $limitmain = self::byteconvert($myStorageNode->bandwidth);
            $limitsend = self::byteconvert($StorageNode->bandwidth);
            $limitset = "";
            if ($limitmain > 0) {
                $limitset = "set net:limit-total-rate 0:$limitmain;";
            }
            if ($limitsend > 0) {
                $limitset .= "set net:limit-total-rate 0:$limitsend;";
            }
            unset($limit);
            $limit = $limitset;
            unset($limitset, $remItem, $includeFile);
            $ftpstart = "ftp://{$username}:{$encpassword}@{$ip}";
            if (is_file($myAdd)) {
                $remItem = dirname("{$removeDir}{$removeFile}");
                $path = $remItem;
                $removeFile = basename($removeFile);
                $opts = '-R -i';
                $includeFile = basename($myFile);
                if (!$myAddItem) {
                    $myAddItem = dirname($myAdd);
                }
                $localfilescheck[0] = $myAdd;
                if (file_exists($ftpstart.$remItem.'/'.$removefile)) {
                    $remotefilescheck[0] = $remItem . '/' . $removefile;
                }
            } elseif (is_dir($myAdd)) {
                $remItem = "{$removeDir}{$removeFile}";
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
            foreach ($localfilescheck as $lin => &$lfn) {
                $localfilescheck[$lin] = str_replace("$path/", "", $lfn);
                unset($lfn, $lin);
            }
            @natcasesort($localfilescheck);
            foreach ($remotefilescheck as $rin => &$rfn) {
                $removefilescheck[$rin] = str_replace("$remItem/", "", $rfn);
                unset($rfn, $rin);
            }
            @natcasesort($remotefilescheck);
            $filescheck = $unique(self::fastmerge($localfilescheck, $remotefilescheck));
            $testavail = -1;
            $testavail = array_filter(
                self::$FOGURLRequests->isAvailable($ip, 1, 80, 'tcp')
            );
            $avail = true;
            if (!$testavail) {
                $avail = false;
            }
            $testavail = array_shift($testavail);
            $allsynced = true;
            foreach ($filescheck as $j => &$filename) {
                $filesequal = false;
                $lindex = array_search($filename, $localfilescheck);
                $rindex = array_search($filename, $remotefilescheck);
                $localfilename = sprintf('%s/%s', $path, $localfilescheck);
                $remotefilename = sprintf('%s/%s', $remItem, $remotefilescheck);
                if (!is_int($rindex)) {
                    $allsynced = false;
                    self::outall(
                        '  # '
                        . $name
                        . ': '
                        . _('File does not exist')
                        . ' '
                        . $filename
                        . '(' . $nodename . ')'
                    );
                    continue;
                } elseif (!is_int($lindex)) {
                    self::outall(
                        '  # '
                        . $name
                        . ': '
                        . _('File does not exist on master node, deleting')
                        . ' '
                        . $filename
                        . ' on '
                        . $nodename
                    );
                    self::$FOGFTP->delete($remotefilename);
                }
                $localsize = self::getFilesize($localfilename);
                $remotesize = null;
                if ($avail) {
                    $rsize = self::$FOGURLRequests->process(
                        $sizeurl,
                        'POST',
                        ['file' => base64_encode($remotefilename)]
                    );
                    $rsize = array_shift($rsize);
                    if (is_int($rsize)) {
                        $remotesize = $rsize;
                    } else {
                        // we should re-try HTTPS because we don't know about the storage node setup
                        // and letting curl follow the redirect doesn't work for POST requests
                        $sizeurl = sprintf(
                            '%s://%s/fog/status/getsize.php',
                            'https',
                            $testip
                        );
                        $rsize = self::$FOGURLRequests->process(
                            $sizeurl,
                            'POST',
                            ['file' => base64_encode($remotefilename)]
                        );
                        $rsize = array_shift($rsize);
                        if (is_int($rsize)) {
                            $remotesize = $rsize;
                        }
                    }
                }
                if (is_null($remotesize)) {
                    $remotesize = self::$FOGFTP->size($remotefilename);
                }
                if ($localsize == $remotesize) {
                    $localhash = self::getHash($localfilename);
                    $remotehash = null;
                    if ($avail) {
                        $rhash = self::$FOGURLRequests->process(
                            $hashurl,
                            'POST',
                            ['file' => base64_encode($remotefilename)]
                        );
                        $rhash = array_shift($rhash);
                        if (strlen($rhash) == 64) {
                            $remotehash = $rhash;
                        } else {
                            // we should re-try HTTPS because we don't know about the storage node setup
                            // and letting curl follow the redirect doesn't work for POST requests
                            $hashurl = sprintf(
                                '%s://%s/fog/status/gethash.php',
                                'https',
                                $testip
                            );
                            $rhash = self::$FOGURLRequests->process(
                                $hashurl,
                                'POST',
                                ['file' => base64_encode($remotefilename)]
                            );
                            $rhash = array_shift($rhash);
                            if (strlen($rhash) == 64) {
                                $remotehash = $rhash;
                            }
                        }
                    }
                    if (is_null($remotehash)) {
                        if ($localsize < 10485760) {
                            $remotehash = hash_file('sha256', $ftpstart.$remotefilename);
                        } else {
                            $filesequal = true;
                        }
                    }
                    if ($localhash == $remotehash) {
                        $filesequal = true;
                    } else {
                        $errorMsg = '  # '
                            . $name
                            . ':'
                            . ' '
                            . _('File hash mismatch')
                            . ' - '
                            . $filename
                            . ': '
                            . $localhash
                            . ' != '
                            . $remotehash;
                    }
                } else {
                    $errorMsg = '  # '
                        . $name
                        . ':'
                        . ' '
                        . _('File size mismatch')
                        . ' - '
                        . $filename
                        . ': '
                        . $localsize
                        . ' != '
                        . $remotesize;
                }
                if ($filesequal) {
                    self::outall(
                        ' | ' . $name . ': ' . _('No need to sync')
                        . ' ' . $filename . ' ' . _('file to')
                        . ' ' . $nodename
                    );
                    continue;
                }
                self::outall($errorMsg);
                $allsynced = false;
                self::outall(
                    ' | ' . _('Deleting remote file') . ': '
                    . $filename
                );
                self::$FOGFTP->delete($remotefilename);
                unset($filename);
            }
            if ($allsynced) {
                self::outall(
                    ' * ' . _('All files synced for this item')
                );
                continue;
            }
            $logname = rtrim(substr(static::$log, 0, -4), '.')
                . '.' . basename($nfilename) . '.transfer.' . $nodename . '.log';
            if (!$i) {
                self::outall(
                    ' * ' . _('Starting Sync Actions')
                );
            }
            $this->killTasking(
                $randind,
                $itemType,
                $name
            );
            $myAddItem = escapeshellarg($myAddItem);
            $myAddItem = trim($myAddItem, "'");
            $myAddItem = '"' . $myAddItem . '"';
            $remItem = escapeshellarg($remItem);
            $remItem = trim($remItem, "'");
            $remItem = '"' . $remItem . '"';
            $logname = escapeshellarg($logname);
            $logname = trim($logname, "'");
            $logname = '"' . $logname . '"';
            $cmd = "lftp -e 'set xfer:log 1; set xfer:log-file $logname;";
            $cmd .= "set ftp:list-options -a;set net:max-retries ";
            $cmd .= "10;set net:timeout 30;$limit mirror -c --parallel=20 ";
            $cmd .= "$opts ";
            if (!empty($includeFile)) {
                $includeFile = escapeshellarg($includeFile);
                $includeFile = trim($includeFile, "'");
                $includeFile = '"' . $includeFile . '"';
                $cmd .= $includeFile . ' ';
                ;
            }
            $cmd .= "--ignore-time -vvv --exclude \".srvprivate\" ";
            $cmd .= "$myAddItem $remItem;";
            $cmd2 = $cmd . "exit' -u $username,[redacted] $ip";
            $cmd .= "exit' -u {$username},'{$password}' $ip";
            self::outall(" | CMD: $cmd2");
            unset($includeFile, $remItem, $myAddItem);
            $this->startTasking(
                $cmd,
                $logname,
                $randind,
                $itemType,
                $name
            );
            self::outall(
                ' * '
                . _('Started sync for')
                . ' '
                . $objType
                . ' '
                . $name
                . ' - '
                . print_r($this->procRef[$itemType][$name][$randind], true)
            );
            unset($StorageNode);
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
            if (isset($this->procRef[$itemType])
                && isset($this->procRef[$itemType][$filename])
                && isset($this->procRef[$itemType][$filename][$index])
            ) {
                $procRef = $this->procRef[$itemType][$filename][$index];
            } else {
                return true;
            }
            if (isset($this->procPipes[$itemType])
                && isset($this->procPipes[$itemType][$filename])
                && isset($this->procPipes[$itemType][$filename][$index])
            ) {
                $pipes = $this->procPipes[$itemType][$filename][$index];
            } else {
                return true;
            }
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
            $files = self::fastmerge(
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
    /**
     * Do some housekeeping jobs in between the replication.
     *
     * @return void
     */
    public function doHousekeeping()
    {
        $this->cleanupProcList();
    }
    /**
     * Cleans up our process lists.
     *
     * @return array
     */
    public function cleanupProcList()
    {
        foreach ($this->procRef as $item => &$itemTypes) {
            foreach ($itemTypes as $image => &$images) {
                foreach ($images as $i => &$ref) {
                    if (!$this->isRunning($images[$i])) {
                        self::outall(
                            ' | '
                            . _('Sync finished - ')
                            . print_r($images[$i], true)
                        );
                        fclose($this->procPipes[$item][$image][$i]);
                        unset($this->procPipes[$item][$image][$i]);
                        fclose($images[$i]);
                        unset($images[$i]);
                    }
                    unset($ref);
                }
                if (!count($itemTypes[$image])) {
                    unset($itemTypes[$image]);
                }
                if (!count($this->procPipes[$item[$image]])) {
                    unset($this->procPipes[$item[$image]]);
                }
                unset($images);
            }
            if (!count($this->procRef[$item])) {
                unset($this->procRef[$item]);
            }
            if (!count($this->procPipes[$item])) {
                unset($this->procPipes[$item]);
            }
            unset($itemTypes);
        }
    }
}
