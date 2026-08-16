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
        // FOG_LOG_DIR, not the SERVICE_LOG_PATH globalSetting.
        //
        // The two were independent and nothing kept them in step. FOG_LOG_DIR
        // is derived from the installer's base path, and so are $servicelogs
        // and the /var/log/fog symlink the log viewer reads through -- but the
        // setting stayed at whatever the schema seeded. Relocating the install
        // (GH-850) therefore left the daemons writing to /opt/fog/log while
        // everything else had moved, with nothing reporting it; editing the
        // setting in the UI produced the same split the other way round.
        //
        // The installer now records the setting from $servicelogs
        // (recordGitUpdateSettings), so it shows where logs go and stays true;
        // reading it back here would just reintroduce the second source of
        // truth it exists to document.
        self::$logpath = rtrim(FOG_LOG_DIR, DS) . DS;
        self::$knownips = Route::getIds(
            'storagenode',
            ['isEnabled' => 1],
            'ip'
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
        $StorageNodesFound = Route::getList(
            'storagenode',
            [
                'isMaster' => 1,
                'isEnabled' => 1
            ]
        );
        $StorageNodes = [];
        // Initialised up front because it is handed to the hook below by
        // reference. A server that masters no node never entered the loop,
        // so $MasterIDs auto-vivified to null, and the Location plugin's
        // alterMasters() then called fastmerge() with it -- `array + null`,
        // a PHP 8 TypeError. Being an Error rather than an Exception it
        // walked past the service loop's catch and killed the child, which
        // was re-forked straight back into it. That is every non-master node
        // running the multicast daemon with the Location plugin on (#815).
        $MasterIDs = [];
        foreach ($StorageNodesFound as &$StorageNode) {
            // getItem(), not indiv(): a node that vanished between the list
            // and the fetch used to exit the daemon child here. Refs #907.
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode || !$StorageNode->online) {
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
        throw new \Exception(
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
                self::shortName($this),
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
        // Still emitted from the eight daemon entry points under
        // packages/service/, so the signature stays as it is.
        self::outall(
            sprintf(
                '===== FOG %s -- %s starting =====',
                FOG_VERSION,
                self::shortName($this)
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
     * Get's the current datetime
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
     * term, so a second knob buys nothing and would cost a schema step on
     * both branches.
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
                self::shortName($this)
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
        // getItem(), not indiv(): the two throws below are what this method
        // already uses to report "cannot sync from here", and a node that has
        // been deleted belongs with them rather than exiting. Refs #907.
        $myStorageNode = Route::getItem('storagenode', $myStorageNodeID);
        if (!$myStorageNode) {
            throw new \Exception(_('This node could not be found'));
        }
        if (!$myStorageNode->isMaster) {
            throw new \Exception(_('This is not the master for this group'));
        }
        if (!$myStorageNode->online) {
            throw new \Exception(_('This node does not appear to be online'));
        }
        $StorageNodes = Route::getList('storagenode', $find);
        // Short name: compared to 'Snapin' below to pick between the
        // snapin path fields and the image path fields.
        $objType = self::shortName($Obj);
        $groupOrNodeCount = count($StorageNodes);
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
        foreach ($StorageNodes as $i => &$StorageNode) {
            if ($StorageNode->id == $myStorageNodeID) {
                continue;
            }
            // getItem(), not indiv(): a node that vanished between the list
            // and the fetch used to exit the daemon child here. Refs #907.
            $StorageNode = Route::getItem('storagenode', $StorageNode->id);
            if (!$StorageNode) {
                continue;
            }
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
                if (file_exists($ftpstart.$remItem.'/'.$removeFile)) {
                    $remotefilescheck[0] = $remItem . '/' . $removeFile;
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
                $remotefilescheck[$rin] = str_replace("$remItem/", "", $rfn);
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
                $localfilename = sprintf('%s/%s', $path, $filename);
                $remotefilename = sprintf('%s/%s', $remItem, $filename);
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
                            $ip
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
                        if (is_string($rhash) && strlen($rhash) == 64) {
                            $remotehash = $rhash;
                        } else {
                            // we should re-try HTTPS because we don't know about the storage node setup
                            // and letting curl follow the redirect doesn't work for POST requests
                            $hashurl = sprintf(
                                '%s://%s/fog/status/gethash.php',
                                'https',
                                $ip
                            );
                            $rhash = self::$FOGURLRequests->process(
                                $hashurl,
                                'POST',
                                ['file' => base64_encode($remotefilename)]
                            );
                            $rhash = array_shift($rhash);
                            if (is_string($rhash) && strlen($rhash) == 64) {
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
            /**
             * GHSA-2hqx-5ffg-w4c3: this used to build one shell string and
             * hand it to proc_open(), which runs it through `/bin/sh -c`.
             * The storage node's ftpUser, ftpPass and ip went in raw --
             * `-u {$username},'{$password}' $ip` -- so a single quote in the
             * password, or anything at all in the username or ip, escaped
             * into the shell and executed as root, the uid this daemon runs
             * under. RBAC gates who may edit a storage node on this branch,
             * but that only raises the bar to reach the primitive; it does
             * not remove it.
             *
             * The argv is built as an ARRAY instead, so proc_open() execs
             * lftp directly and no shell ever parses these values. That
             * removes the class of bug rather than escaping this instance.
             *
             * The paths still need quoting, but for lftp's own script
             * parser -- see _lftpQuote(). The previous code called
             * escapeshellarg(), stripped the single quotes it had just added
             * and re-wrapped the result in double quotes, which threw the
             * escaping away and left `$`, backtick and backslash live again.
             * $logname keeps its bare form now, which also stops wlog()
             * creating transfer logs with literal double quotes in the name.
             */
            $script = 'set xfer:log 1; set xfer:log-file '
                . self::_lftpQuote($logname) . ';';
            $script .= "set ftp:list-options -a;set net:max-retries ";
            $script .= "10;set net:timeout 30;$limit mirror -c --parallel=20 ";
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
            $cmd = [
                'lftp',
                '-e',
                $script,
                '-u',
                "{$username},{$password}",
                $ip
            ];
            self::outall(
                " | CMD: lftp -e '$script' -u $username,[redacted] $ip"
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
        return '"'
            . str_replace(
                ['\\', '"'],
                ['\\\\', '\\"'],
                (string)$value
            )
            . '"';
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
        foreach ((array)$output as $t) {
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
            // procRef may be an array keyed by $index or a single resource
            // (the multicast path collapses it to one resource via
            // array_shift). Resolve to a single reference so we never index
            // into a resource, which emits a warning under PHP 8.
            $procRef = is_array($this->procRef)
                ? ($this->procRef[$index] ?? null)
                : $this->procRef;
            if ($this->isRunning($procRef)) {
                $pid = $this->getPID($procRef);
                if ($pid) {
                    $this->killAll($pid, SIGTERM);
                }
                proc_terminate($procRef, SIGTERM);
                proc_close($procRef);
                return false;
            }
            // Process already exited — release the resource.
            if (is_resource($procRef)) {
                proc_close($procRef);
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
            $isRunning = $this->isRunning($procRef);
            if ($isRunning) {
                $pid = $this->getPID($procRef);
                if ($pid) {
                    $this->killAll($pid, SIGTERM);
                }
                proc_terminate($procRef, SIGTERM);
            }
            // Always close pipes and release the process resource,
            // whether it was still running or had already exited.
            foreach ((array)$pipes as $i => &$close) {
                fclose($close);
                unset($close);
            }
            unset($pipes);
            proc_close($procRef);
            return !$isRunning;
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
        if (!is_resource($procRef)) {
            return false;
        }
        $ar = proc_get_status($procRef);
        return $ar['running'];
    }
    /**
     * Returns the full process status (running flag and exit code).
     *
     * Unlike isRunning()/getPID(), this exposes the exit code. PHP's
     * proc_get_status only reports a real exitcode on the FIRST call
     * after the process terminates (subsequent calls return -1), so a
     * caller that needs the exit code must read it through this method
     * INSTEAD of calling isRunning() first -- otherwise isRunning()
     * consumes it.
     *
     * @param resource $procRef the reference to check
     *
     * @return array|bool the proc_get_status array, or false if no ref
     */
    public function getProcStatus($procRef)
    {
        if (!is_resource($procRef)) {
            return false;
        }
        return proc_get_status($procRef);
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
            unset($dir);
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
                        foreach (
                            (array)$this->procPipes[$item][$image][$i]
                            as &$pipe
                        ) {
                            fclose($pipe);
                            unset($pipe);
                        }
                        unset($this->procPipes[$item][$image][$i]);
                        proc_close($images[$i]);
                        unset($images[$i]);
                    }
                    unset($ref);
                }
                if (!count($itemTypes[$image])) {
                    unset($itemTypes[$image]);
                }
                if (!count($this->procPipes[$item][$image])) {
                    unset($this->procPipes[$item][$image]);
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
