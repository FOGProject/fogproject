<?php
/**
 * Handles the fog linux services
 *
 * PHP version 7.4+
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
     * Node IPs we have in the database to check in service startup
     *
     * @var array
     */
    public static $knownips = array();
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
        Route::listem(
            'storagenode',
            'name',
            false,
            [ 'isEnabled' => [1] ]
        );
        $StorageNodes = json_decode(
            Route::getData()
        )->storagenodes;
        foreach ((array)$StorageNodes as &$StorageNode) {
            self::$knownips[] = $StorageNode->ip;
        }
    }
    /**
     * Checks if the node running this is indeed the master
     *
     * @return array
     */
    protected function checkIfNodeMaster()
    {
        self::getIPAddress();
        $find = [
            'isMaster' => [1],
            'isEnabled' => [1]
        ];
        Route::listem(
            'storagenode',
            'name',
            false,
            $find
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        $StorageNodes = $StorageNodes->storagenodes;
        // Initialised up front because a server that masters no node never
        // enters the loop, and the find() below then read an undefined
        // variable. That is every non-master node running any of the
        // services -- the ordinary case for a storage node.
        $MasterIDs = [];
        foreach ((array)$StorageNodes as &$StorageNode) {
            $ip = self::resolveHostname(
                $StorageNode->ip
            );
            if (!in_array($ip, self::$ips)) {
                continue;
            }
            $MasterIDs[] = $StorageNode->id;
        }
        $StorageNodes = self::getClass('StorageNodeManager')->find(
            ['id' => $MasterIDs]
        );
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTERS',
            array(
                'StorageNodes' => $StorageNodes,
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
        self::getIPAddress(true);
        if (!count(self::$ips) || !array_intersect(self::$knownips, self::$ips)) {
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
                sprintf(_('Interface Ready with IP Address: %s'), $ip)
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
        // GH-497: this was twenty lines of ASCII art per start. Now that the
        // log survives a restart it is repeated noise rather than a one-off
        // header, and it pushed real output out of view in a `tail`. One line
        // keeps what the banner was actually useful for -- a visible marker of
        // where a restart begins, and which build is running.
        //
        // Still emitted from the daemon entry points under packages/service/,
        // so the signature stays as it is.
        self::outall(
            sprintf(
                '===== FOG %s -- %s starting =====',
                FOG_VERSION,
                get_class($this)
            )
        );
    }
    /**
     * Outputs the string passed
     *
     * @param string $string the string to output
     *
     * @return void
     */
    public static function outall($string)
    {
        self::wlog("$string\n", static::$log);
        return;
    }
    /**
     * Gets the current datetime
     *
     * @return string
     */
    public static function getDateTime()
    {
        return self::niceDate()->format('m-d-y g:i:s a');
    }
    /**
     * Rotates a service log that has reached its size limit.
     *
     * GH-497: this used to unlink the log outright, which threw away the
     * run you most likely wanted to read -- the one that had just filled
     * the file. Shift the numbered generations instead and drop only the
     * oldest, so there is always history behind the live file.
     *
     * Kept at a fixed five generations rather than a new setting. Disk cost
     * is SERVICE_LOG_SIZE * (KEEP + 1) and admins already control the first
     * term, so a second knob buys nothing and would cost a schema step.
     *
     * Two writers can race here: the supervisor and its forked child share
     * a log, and both could see an oversize file and rotate. The loss is a
     * duplicated shift, not corruption, and wlog() holds its handle only
     * for the length of one write -- not worth a lock file.
     *
     * @param string $path the log path to rotate
     *
     * @return void
     */
    protected static function rotateLog($path)
    {
        $keep = 5;
        if (file_exists("{$path}.{$keep}")) {
            unlink("{$path}.{$keep}");
        }
        // Descending. Going the other way would overwrite .2 with .1 before
        // .2 itself had been moved up to .3.
        for ($i = $keep - 1; $i >= 1; $i--) {
            if (file_exists("{$path}.{$i}")) {
                rename("{$path}.{$i}", sprintf('%s.%d', $path, $i + 1));
            }
        }
        rename($path, "{$path}.1");
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
                self::rotateLog($path);
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
            'name',
            false,
            $find
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        $StorageNodes = $StorageNodes->storagenodes;
        $objType = get_class($Obj);
        $groupOrNodeCount = count($StorageNodes ?: []);
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
                    _($itemType) . 's'
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
                        _($itemType) . 's'  :
                        _($itemType)
                    )
                )
            );
            self::outall(
                sprintf(
                    ' | %s %s: %s',
                    $fileOverride ? _('File') : _($objType),
                    _('Name'),
                    $fileOverride ?: $Obj->get('name')
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
                $myFile = $Obj->get($getFileOfItemField);
            } else {
                $myFile = $fileOverride;
            }
            $myAdd = "$myDir$myFile";
            $myAddItem = false;
            foreach ($StorageNodes as $i => &$StorageNode) {
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
                            _($itemType) . 's'
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
                            ' | %s: %s',
                            _('File or path cannot be reached'),
                            $myAdd
                        )
                    );
                    continue;
                }
                $testip = $StorageNode->ip;
                $sizeurl = sprintf('%s://%s/fog/status/getsize.php', self::$httpproto, $testip);
                $hashurl = sprintf('%s://%s/fog/status/gethash.php', self::$httpproto, $testip);
                self::$FOGFTP
                    ->set('username', $StorageNode->user)
                    ->set('password', $StorageNode->pass)
                    ->set('host', $StorageNode->ip);
                try {
                    self::$FOGFTP->connect();
                } catch (Exception $e) {
                    // A refused login used to be reported as "Cannot connect",
                    // which points at the network when the cause is nearly
                    // always a stale password for this node. Each master keeps
                    // its own copy of every peer's credential and nothing syncs
                    // them, so say which of the two actually failed.
                    if (self::$FOGFTP->lastFailure() === 'login') {
                        self::outall(
                            sprintf(
                                ' * Error: %s %s %s %s',
                                _('FTP login rejected by'),
                                $StorageNode->name,
                                _('for user'),
                                $StorageNode->user
                            )
                        );
                        self::outall(
                            sprintf(
                                ' | %s',
                                _('Check the password stored for this node')
                            )
                        );
                    } else {
                        self::outall(
                            sprintf(
                                ' * Error: %s %s',
                                _('Cannot connect to'),
                                $StorageNode->name
                            )
                        );
                    }
                    continue;
                }
                $nodename = $StorageNode->name;
                $username = self::$FOGFTP->get('username');
                $password = self::$FOGFTP->get('password');
                $ip = self::$FOGFTP->get('host');
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
                $limitset = "";
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
                $remotefilescheck = array();
                if (is_file($myAdd)) {
                    $remItem = dirname("$removeDir$removeFile");
                    $path = $remItem;
                    $removeFile = basename($removeFile);
                    $opts = '-R -i';
                    $includeFile = basename($myFile);
                    if (!$myAddItem) {
                        $myAddItem = dirname($myAdd);
                    }
                    $localfilescheck[0] = $myAdd;
                    if (file_exists($ftpstart.$remItem."/".$removeFile)) {
                        $remotefilescheck[0] = sprintf(
                            '%s/%s',
                            $remItem,
                            $removeFile
                        );
                    }
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
                $localfilescheck = array_values(
                    array_filter(
                        array_unique($localfilescheck)
                    )
                );
                foreach ($localfilescheck as &$lfn) {
                    $lfn = str_replace("$path/", "", $lfn);
                    unset($lfn);
                }
                $remotefilescheck = array_values(
                    array_filter(
                        array_unique($remotefilescheck)
                    )
                );
                foreach ($remotefilescheck as &$rfn) {
                    $rfn = str_replace("$remItem/", "", $rfn);
                    unset($rfn);
                }
                $filescheck = array_unique(array_merge((array)$localfilescheck, (array)$remotefilescheck));
                $testavail = -1;
                $allsynced = true;

                $resp = self::$FOGURLRequests->isAvailable($testip, 1, 80);
                $avail = true;
                $testavail = array_filter($resp);
                $testavail = array_shift($testavail);
                if (!$testavail) {
                    $avail = false;
                }

                foreach ($filescheck as $j => &$filename) {
                    $filesequal = false;
                    $lindex = array_search($filename, $localfilescheck);
                    $rindex = array_search($filename, $remotefilescheck);
                    if (!is_int($rindex)) {
                        $allsynced = false;
                        self::outall(sprintf(
                            '  # %s: %s %s (%s)',
                            $name,
                            _('File does not exist'),
                            $filename,
                            $nodename
                        ));
                    } elseif (!is_int($lindex)) {
                        self::outall(sprintf(
                            '  # %s: %s %s %s %s %s',
                            $name,
                            _('File does not exist'),
                            'on master node, deleting',
                            $filename,
                            'on',
                            $nodename
                        ));
                        $remotefilename = sprintf('%s%s%s', $remItem, "/", $remotefilescheck[$rindex]);
                        self::$FOGFTP->delete($remotefilename);
                    } else {
                        $localfilename = sprintf('%s%s%s', $path, "/", $localfilescheck[$lindex]);
                        $remotefilename = sprintf('%s%s%s', $remItem, "/", $remotefilescheck[$rindex]);
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
                                $sizeurl = sprintf('%s://%s/fog/status/getsize.php', 'https', $testip);
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
                                    $hashurl = sprintf('%s://%s/fog/status/gethash.php', 'https', $testip);
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
                                self::outall(sprintf(
                                    '  # %s: %s - %s: %s != %s',
                                    $name,
                                    _('File hash mismatch'),
                                    $filename,
                                    $localhash,
                                    $remotehash
                                ));
                            }
                        } else {
                            self::outall(sprintf(
                                '  # %s: %s - %s: %s != %s',
                                $name,
                                _('File size mismatch'),
                                $filename,
                                $localsize,
                                $remotesize
                            ));
                        }
                        if ($filesequal != true) {
                            $allsynced = false;
                            self::outall(sprintf('  # %s: %s %s', $name, _('Deleting remote file'), $filename));
                            self::$FOGFTP->delete($remotefilename);
                        } else {
                            self::outall(sprintf(
                                '  # %s: %s %s (%s)',
                                $name,
                                _('No need to sync'),
                                $filename,
                                $nodename
                            ));
                            continue;
                        }
                    }
                    unset($filename);
                }
                self::$FOGFTP->close();
                if ($allsynced) {
                    self::outall(' * ' . _('All files synced for this item.'));
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
                /**
                 * GHSA-2hqx-5ffg-w4c3: this used to build one shell string
                 * and hand it to proc_open(), which runs it through
                 * `/bin/sh -c`. The storage node's ftpUser, ftpPass and ip
                 * went in raw -- `-u $username,'$password' $ip` -- so a
                 * single quote in the password, or anything at all in the
                 * username or ip, escaped into the shell and executed as
                 * root, the uid this daemon runs under.
                 *
                 * The argv is built as an ARRAY instead, so proc_open()
                 * execs lftp directly and no shell ever parses these values.
                 * That removes the class of bug rather than escaping this
                 * one instance of it.
                 *
                 * The paths still need quoting, but for lftp's own script
                 * parser -- see _lftpQuote(). The previous code called
                 * escapeshellarg(), stripped the single quotes it had just
                 * added and re-wrapped the result in double quotes, which
                 * threw the escaping away and left `$`, backtick and
                 * backslash live again.
                 */
                $script = "set xfer:log 1; set xfer:log-file "
                    . self::_lftpQuote($logname) . ";";
                $script .= "set ftp:list-options -a;set net:max-retries ";
                $script .= "10;set net:timeout 30; $limit mirror -c --parallel=20 ";
                $script .= "$opts ";
                if (!empty($includeFile)) {
                    $script .= self::_lftpQuote($includeFile) . ' ';
                }
                $script .= "--ignore-time -vvv --exclude \".srvprivate\" ";
                $script .= self::_lftpQuote($myAddItem)
                    . ' '
                    . self::_lftpQuote($remItem)
                    . ';';
                $script .= 'exit';
                $cmd = array(
                    'lftp',
                    '-e',
                    $script,
                    '-u',
                    "$username,$password",
                    $ip
                );
                self::outall(
                    sprintf(
                        " | CMD: lftp -e '%s' -u %s,[Protected] %s",
                        $script,
                        $username,
                        $ip
                    )
                );
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
                        ' | %s %s %s - %s',
                        _('Started sync for'),
                        $objType,
                        $name,
                        print_r($this->procRef[$itemType][$name][$randind], true)
                    )
                );
                unset($StorageNode);
            }
        }
    }
    /**
     * Quotes a value for lftp's own script parser.
     *
     * The replication script passed to `lftp -e` is parsed by lftp, not by
     * a shell, so shell escaping is the wrong tool for it (and was actively
     * harmful -- see replicate_items). lftp treats a double-quoted string as
     * one word and honours backslash escapes inside it, so escaping the
     * backslash and the double quote is sufficient to keep a path with
     * spaces, quotes or metacharacters from splitting into extra lftp
     * commands. Introduced with GHSA-2hqx-5ffg-w4c3.
     *
     * @param string $value the value to quote
     *
     * @return string
     */
    protected static function _lftpQuote($value)
    {
        return sprintf(
            '"%s"',
            str_replace(
                array('\\', '"'),
                array('\\\\', '\\"'),
                (string)$value
            )
        );
    }
    /**
     * Starts taskings
     *
     * @param string|array $cmd The command to start. An array is exec'd
     *                          directly by proc_open() with no shell,
     *                          which is how any command built from
     *                          user-controlled values must be passed.
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
        self::wlog(_('Task started')."\n", $logname);
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
        foreach ($output as $t) {
            if ($t != $pid) {
                $this->killAll($t, $sig);
            }
        }
        posix_kill($pid, $sig);
    }
    /**
     * Is a pid still present, and still running what we started?
     *
     * /proc is read rather than posix_kill($pid, 0) because a pid can be
     * recycled between the kill and the check. Matching the cmdline means a
     * reused pid running something else answers "gone", which is the honest
     * answer -- and a zombie, whose cmdline is empty, answers "gone" too,
     * correctly: it holds no port and no file.
     *
     * @param int    $pid   The pid to test.
     * @param string $match Substring the cmdline must contain, if any.
     *
     * @return bool
     */
    public function isPidAlive($pid, $match = '')
    {
        $pid = (int)$pid;
        if ($pid < 1) {
            return false;
        }
        $cmdline = @file_get_contents(
            sprintf('/proc/%d/cmdline', $pid)
        );
        if (empty($cmdline)) {
            return false;
        }
        if ('' === $match) {
            return true;
        }
        return false !== strpos(
            str_replace("\0", ' ', $cmdline),
            $match
        );
    }
    /**
     * Waits, bounded, for a process to exit.
     *
     * @param resource $procRef The proc_open() handle.
     * @param int      $tenths  How many tenths of a second to wait.
     *
     * @return bool True if it exited within the window.
     */
    private function _waitForExit($procRef, $tenths = 30)
    {
        for ($i = 0; $i < $tenths; $i++) {
            if (!$this->isRunning($procRef)) {
                return true;
            }
            usleep(100000);
        }
        return !$this->isRunning($procRef);
    }
    /**
     * Terminates a process tree and reports whether it is actually gone.
     *
     * SIGTERM the tree, wait, then SIGKILL what is left. Both halves matter:
     *
     * - proc_close() BLOCKS until the child is reaped, so a process that
     *   ignored SIGTERM used to hang the daemon inside the kill itself.
     * - the answer is only honest once the process has had a chance to go,
     *   and callers act on it: MulticastTask only releases its ownership
     *   row when the sender is really gone.
     *
     * The handle is deliberately NOT closed when the process survives
     * everything -- proc_close() would block forever waiting to reap it.
     * Leaking one handle beats wedging the daemon, and the caller is told.
     *
     * @param resource $procRef The proc_open() handle.
     *
     * @return bool True when nothing is left running.
     */
    private function _terminateProc($procRef)
    {
        if (!is_resource($procRef)) {
            return true;
        }
        if (!$this->isRunning($procRef)) {
            // Already exited; proc_close() only reaps it.
            proc_close($procRef);
            return true;
        }
        $pid = (int)$this->getPID($procRef);
        if ($pid > 0) {
            $this->killAll($pid, SIGTERM);
        }
        proc_terminate($procRef, SIGTERM);
        if (!$this->_waitForExit($procRef)) {
            if ($pid > 0) {
                $this->killAll($pid, SIGKILL);
            }
            proc_terminate($procRef, SIGKILL);
            $this->_waitForExit($procRef);
        }
        if ($this->isRunning($procRef)) {
            return false;
        }
        proc_close($procRef);
        return true;
    }
    /**
     * Closes out a set of proc_open() pipes.
     *
     * @param mixed $pipes The stored pipe set, if any.
     *
     * @return void
     */
    private function _closePipes($pipes)
    {
        foreach ((array)$pipes as &$close) {
            if (is_resource($close)) {
                fclose($close);
            }
            unset($close);
        }
    }
    /**
     * Kills the tasking.
     *
     * Returns whether the process is GONE by the time the call finishes:
     * true when nothing is left running, false when it survived SIGTERM
     * and SIGKILL. That is the only question a caller can act on, and it
     * was previously unanswerable -- the old code returned false after a
     * SUCCESSFUL kill, returned !$isRunning ("was it already dead") from
     * the itemType branch, and fell off the end returning null when the
     * process had already exited. MulticastTask::killTask() discarded it
     * and hardcoded true, which left the "could not be killed" arms in
     * MulticastManager unreachable.
     *
     * @param int    $index    the index for the item to look into
     * @param mixed  $itemType the type of the item
     * @param string $filename the filename to close out
     *
     * @return bool True when the process is gone.
     */
    public function killTasking(
        $index = 0,
        $itemType = false,
        $filename = false
    ) {
        if ($itemType === false) {
            // isset guard: killTask() is also called on a task whose start
            // FAILED, where no pipes were ever stored -- MulticastManager
            // does exactly that. Unguarded this warned on every failed
            // multicast start under PHP 8.
            if (isset($this->procPipes[$index])) {
                $this->_closePipes($this->procPipes[$index]);
                unset($this->procPipes[$index]);
            }
            // procRef may be an array keyed by $index or a single resource
            // (the multicast path collapses it to one resource via
            // array_shift). Resolve to a single reference so we never index
            // into a resource, which emits a warning under PHP 8.
            $procRef = is_array($this->procRef)
                ? ($this->procRef[$index] ?? null)
                : $this->procRef;
            return $this->_terminateProc($procRef);
        }
        if (!isset($this->procRef[$itemType][$filename][$index])) {
            return true;
        }
        $procRef = $this->procRef[$itemType][$filename][$index];
        if (isset($this->procPipes[$itemType][$filename][$index])) {
            $this->_closePipes(
                $this->procPipes[$itemType][$filename][$index]
            );
            unset($this->procPipes[$itemType][$filename][$index]);
        }
        // Both sides of the pair are dropped, not just the pipes: the
        // handle is closed below, and cleanupProcList() walks procRef
        // expecting a live resource with matching pipes still beside it.
        unset($this->procRef[$itemType][$filename][$index]);
        return $this->_terminateProc($procRef);
    }
    /**
     * Gets the pid of the running reference
     *
     * @param resource $procRef the reference to check
     *
     * @return int
     */
    public function getPID($procRef)
    {
        // is_resource(), not a truthy test: a proc resource that has already
        // been proc_close()'d is still truthy, so !$procRef let it through to
        // proc_get_status(), which throws on PHP 8 ("supplied resource is not
        // a valid process resource"). is_resource() is false for a closed
        // resource, so the guard catches it. Refs #945.
        if (!is_resource($procRef)) {
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
        // See getPID(): a closed resource is truthy but no longer valid, so
        // is_resource() is the correct guard against proc_get_status()
        // throwing on PHP 8. Refs #945.
        if (!is_resource($procRef)) {
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
            unset($dir);
        }
        return $files;
    }
    /**
     * Local file glob recursive getter.
     *
     * @return array
     */
    public function cleanupProcList()
    {
        // Iterated over key SNAPSHOTS, with every read and write going to
        // $this->procRef / $this->procPipes by full path.
        //
        // The obvious form -- foreach ((array)$this->procRef as &$x) -- does
        // not work: casting produces a TEMPORARY, so &$x binds into the copy
        // and every unset() through it is silently discarded. A finished
        // transfer then stays in the list forever, and since housekeeping
        // runs every 100ms the daemon re-reports it about ten times a
        // second for the life of the process, filling the log while
        // proc_close() is called again and again on the same handle. The
        // casts were added for PHP 8 null safety and are still wanted;
        // array_keys() gives that plus a stable iteration order that is
        // safe to unset from. (The procPipes unsets below already wrote
        // through by path and were never affected; procRef was.)
        //
        // count() on a missing key is a TypeError under PHP 8, not a
        // warning -- an uncaught one, in a daemon, so the replicator would
        // die outright and the unit would simply stop syncing. empty() is
        // null-safe and means the same thing at every site here: no entry
        // and an entry holding nothing both want the key dropped. The two
        // structures are kept in step by startTasking() and killTasking(),
        // but nothing enforces that, and this is not the place to find out.
        foreach (array_keys((array)$this->procRef) as $item) {
            foreach (array_keys((array)$this->procRef[$item]) as $image) {
                foreach (array_keys((array)$this->procRef[$item][$image]) as $i) {
                    $proc = $this->procRef[$item][$image][$i];
                    if ($this->isRunning($proc)) {
                        continue;
                    }
                    self::outall(" | Sync finished - " . print_r($proc, true));
                    $pipes = (array)($this->procPipes[$item][$image][$i] ?? []);
                    foreach (array_keys($pipes) as $j) {
                        if (is_resource($pipes[$j])) {
                            fclose($pipes[$j]);
                        }
                    }
                    unset($this->procPipes[$item][$image][$i]);
                    if (is_resource($proc)) {
                        proc_close($proc);
                    }
                    unset($this->procRef[$item][$image][$i]);
                }
                if (empty($this->procRef[$item][$image])) {
                    unset($this->procRef[$item][$image]);
                }
                if (empty($this->procPipes[$item][$image])) {
                    unset($this->procPipes[$item][$image]);
                }
            }
            if (empty($this->procRef[$item])) {
                unset($this->procRef[$item]);
            }
            if (empty($this->procPipes[$item])) {
                unset($this->procPipes[$item]);
            }
        }
    }
}
