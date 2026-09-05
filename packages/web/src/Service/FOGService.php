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

namespace FOG\Service;

use FOG\Base\FOGBase;
use FOG\Db\DatabaseManager;
use FOG\Router\Route;

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
     * Longest a daemon sleeps in one go while waiting for its next pass.
     *
     * Bounds how stale a changed $sleeptime can be -- see waitUntilDue(). Five
     * seconds is well inside the 300 s settings cache the daemons already read
     * through, so it costs nothing in freshness and takes the idle wake rate
     * from 10Hz to 0.2Hz.
     *
     * Keep it between 1 and roughly 60 if you change it. Below 1 the wait loop
     * is back to spinning; far above the settings cache the bound stops
     * meaning anything, because the value it is protecting the freshness of is
     * itself stale by then.
     *
     * @var int
     */
    const IDLE_TICK_CAP = 5;
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
     * Fallback sleep, in seconds, when $sleeptime's globalSetting is unset.
     *
     * Each daemon entry point used to carry its own literal here -- 60 for
     * FileDeleter, 3600 for ImageSize, and so on -- inside a copy of the
     * service loop that lived outside the analyzed tree. The value is a
     * property of the service, not of the script that starts it, so it sits
     * beside $sleeptime, which already names that service's globalSetting.
     *
     * @var int
     */
    public static $sleepdefault = 600;
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
     * Refreshed by waitInterfaceReady() on every pass, not filled once at
     * construction -- read loadKnownIps() before treating this as a value
     * that can be cached.
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
    }
    /**
     * The addresses of every enabled storage node, read fresh.
     *
     * Deliberately a method rather than a value cached at construction.
     * waitInterfaceReady() calls it on every pass so an edit made in the UI
     * while a daemon is waiting is actually seen -- see the comment there for
     * why that is the difference between a ten-second wait and a permanent
     * one.
     *
     * @return array
     */
    public static function loadKnownIps()
    {
        return Route::getIds(
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
        // Initialized up front because it is handed to the hook below by
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
        for (;;) {
            self::getIPAddress(true);
            // Re-read on every pass, because this comparison has two sides
            // and only one of them used to be live.
            //
            // getIPAddress(true) forces a fresh read of what the machine
            // currently holds, so the loop already intends to re-poll. The
            // node addresses it is compared against, though, were read once
            // in the constructor and never again -- so if they did not match
            // when the daemon started, they could not begin to match, and the
            // loop below had no exit. The daemon then sat on sleep(10)
            // forever while systemd reported the unit active, which is the
            // failure mode that is hardest to notice.
            //
            // That is not hypothetical: it is what a re-IP looks like. The
            // server's address changes, every daemon comes up unable to match
            // itself against a storagenode row that still holds the old
            // address, and the admin corrects the row in the UI -- which is
            // exactly the fix, and which used to require restarting all ten
            // daemons before it took. Now the next pass sees it.
            self::$knownips = self::loadKnownIps();
            if (count(self::$ips)
                && array_intersect(self::$knownips, self::$ips)
                && in_array(self::getSetting('FOG_WEB_HOST'), self::$ips)
            ) {
                break;
            }
            self::outall(
                sprintf(
                    '%s: %s',
                    _('Interface not ready, waiting for it to come up'),
                    self::getSetting('FOG_WEB_HOST')
                )
            );
            sleep(10);
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
        // A loop, not recursion, for the same reason waitInterfaceReady() is
        // one: every ten-second wait used to add a stack frame that was never
        // unwound, so the cost of waiting grew without bound. PHP has no tail
        // calls, so a database that stays down does not just block the daemon
        // -- it slowly consumes it.
        //
        // The test stays in its original positive form deliberately. Written
        // as `while (!getLink())` the analyzer calls the negation always-false,
        // because PDODB::link() is documented `@return object` while it in
        // fact returns false on a failed connect and null after a disconnect
        // -- the class guards on `!self::$_link` in a dozen places, so the
        // docblock is simply wrong. Correcting it is right but is not this
        // change: the accurate type immediately reaches
        // FOGManagerController::sqlexec(), which takes `@param resource $db`
        // and calls $db->prepare() with no guard, and deciding what that
        // should do when the link is false is a DataTables error-handling
        // question, not a daemon one.
        for (;;) {
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
        }
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
        // Called from run() now rather than from each entry point, so it is
        // emitted once per daemon start exactly as before. The signature is
        // unchanged: MulticastManager overrides run() and still calls it.
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
        $tmpTime = (int) self::getSetting(static::$sleeptime);
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
     * The whole life of a daemon: bring it up, then run it until killed.
     *
     * This is the loop that used to be copy-pasted into each of the ten
     * entry points under packages/service/. Those files are named for their
     * systemd unit and so carry no extension, which put them outside every
     * *.php filter in the tree -- PHPStan's paths, bin/import-core-classes.php
     * and tests/no-bare-core-references.test.php alike. The PSR-4 sweep
     * therefore skipped all ten, each kept a bare `FOGCore::niceDate()` that
     * no longer resolved, and every daemon died one iteration in while systemd
     * reported the unit active (GH-1498).
     *
     * Living here instead of there is the actual fix for that class of bug:
     * this file is analyzed, swept and deployed by the same paths as the rest
     * of core, and what remains in packages/service/ is a five-line stub with
     * no logic left to drift. ExecStart deliberately still points at that
     * stub rather than into the webroot -- eight of the ten units run as root
     * with Restart=always, and the webroot is writable by the web user.
     *
     * @return void
     */
    public function run()
    {
        $this->getBanner();
        $this->waitInterfaceReady();
        $this->waitDbReady();
        $this->serviceStart();
        $nextrun = null;
        // for(;;) rather than while(true), matching Service_persist()'s own
        // supervisor loop in packages/service/lib/service_lib.php.
        for (;;) {
            if (!static::$zzz) {
                static::$zzz = static::$sleepdefault;
            }
            if (null === $nextrun) {
                $this->serviceRun();
                $nextrun = $this->scheduleNextRun();
            }
            if (self::niceDate() < $nextrun) {
                $this->waitUntilDue($nextrun);
                continue;
            }
            $nextrun = $this->scheduleNextRun();
            $this->serviceRun();
        }
    }
    /**
     * Idle until the next pass is due, reaping transfers while any are live.
     *
     * The loop used to wake ten times a second unconditionally -- usleep(100000)
     * plus a doHousekeeping() -- whatever the service was waiting for. Measured
     * on an idle server that is 43 s of CPU per day per daemon, 432 s across the
     * ten, none of it doing anything: ImageSize spends an hour between passes and
     * woke 36,000 times to get there.
     *
     * The 100ms tick is not arbitrary, though, and this is why the rate is
     * conditional rather than simply longer. cleanupProcList() is what notices a
     * finished replication transfer, closes its pipes and proc_close()s it; at a
     * slower rate a completed transfer sits unreaped, and its "Sync finished"
     * line arrives late. So: tick at the old rate exactly while there is
     * something in $procRef to reap, and idle properly when there is not.
     *
     * $procRef is populated only by startTasking(), i.e. only by the two
     * replicators and MulticastManager, and only while a transfer is actually
     * running. Every other daemon, and those three between transfers, take the
     * cheap path -- which is the case that was costing everything.
     *
     * The cap keeps a long interval from becoming a long blind spot:
     * serviceRun() re-reads $sleeptime each pass, so an admin shortening a
     * 3600 s interval would otherwise not be noticed until the pass after next.
     * Shutdown does not depend on it -- the child clears its signal handlers in
     * Service_persist(), so SIGTERM ends it mid-sleep.
     *
     * @param \DateTime $nextrun when the next pass is due
     *
     * @return void
     */
    protected function waitUntilDue($nextrun)
    {
        if (!empty($this->procRef)) {
            usleep(100000);
            $this->doHousekeeping();
            return;
        }
        $nap = self::idleNap($nextrun->getTimestamp() - time());
        if ($nap < 1) {
            usleep(100000);
            return;
        }
        sleep($nap);
    }
    /**
     * Whole seconds to sleep, given the seconds left before the next pass.
     *
     * Split out from waitUntilDue() so the arithmetic can be tested without a
     * database, a service instance or a five-second wait -- the surrounding
     * method is all side effects, and a test of it could only ever assert on
     * elapsed wall-clock, which is exactly the kind of assertion that goes
     * flaky and then gets deleted.
     *
     * 0 means "do not sleep": the pass is due now, or its deadline has
     * already gone by, and the caller should tick straight through.
     *
     * @param int $remaining seconds until the next pass is due
     *
     * @return int
     */
    public static function idleNap($remaining)
    {
        if ($remaining < 1) {
            return 0;
        }
        return (int)min($remaining, self::IDLE_TICK_CAP);
    }
    /**
     * When the next pass is due: now, plus this service's sleep time.
     *
     * The pluralization this replaces was inverted -- `$zzz != 1 ? '' : 's'`
     * appended the "s" only when the interval was exactly one second, giving
     * "+1 seconds" and "+600 second". DateTime::modify() accepts either form,
     * so nothing misbehaved; dropping the ternary removes the trap rather
     * than inverting it.
     *
     * @return \DateTime
     */
    protected function scheduleNextRun()
    {
        return self::niceDate()->modify(
            sprintf('+%d seconds', (int)static::$zzz)
        );
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
        if ($master) {
            // Resolve the groups BEFORE $find is built. Between 2018 and
            // this fix the assignment sat inside the second `if ($master)`
            // below, after $find had already copied $groupID by value -- so
            // it was a dead write and master->master replication searched
            // the replicator's OWN group. A group has one master, the loop
            // below then skips it as self, and the count check reported
            // "There are no members to sync to" on every cycle of every
            // install. Group->Nodes was never affected: it wants exactly
            // this group and does not enter the branch.
            $groupID = $Obj->get('storagegroups');
        }
        $find = [
            'isEnabled' => [1],
            'storagegroupID' => $groupID
        ];
        if ($master) {
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
                . _('between') . ' ' . _($itemType) . 's'
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
                _($itemType) . 's'
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
            // Take the FTP credential off the LIST row, before the re-fetch
            // below replaces the object. It is the row this loop actually
            // selected on, and reading it here means the credential does not
            // depend on what a second trip through the router hands back.
            //
            // It used to be load-bearing rather than tidy: getItem() went
            // through getter(), which stripped storagenode pass and key at
            // the source, so the re-fetched object had no password on it and
            // every replication login was refused -- reported as a stale
            // password for the node when the stored one was correct all
            // along. That asymmetry is gone; getItem() and getList() now both
            // hand internal callers the whole object and the API emitter is
            // the only thing that removes secrets.
            $nodeUser = $StorageNode->user;
            $nodePass = $StorageNode->pass;
            // getItem(), not indiv(): a node that vanished between the list
            // and the fetch used to exit the daemon child here. Refs #907.
            // Still the right call -- `online` is computed by getter() and is
            // not carried on a list row, so this is where it comes from.
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
                    . ' ' . _('between') . ' ' . _($itemType) . 's'
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
            $username = self::$FOGFTP->username = $nodeUser;
            $password = self::$FOGFTP->password = $nodePass;
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
                // A refused login used to be reported as "Cannot connect",
                // which points at the network when the cause is nearly always
                // a stale password for this node. Each master keeps its own
                // copy of every peer's credential and nothing syncs them, so
                // say which of the two actually failed.
                if (self::$FOGFTP->lastFailure() === 'login') {
                    self::outall(
                        ' * ' . _('FTP login rejected by') . ' ' . $nodename
                        . ' ' . _('for user') . ' ' . $username
                    );
                    self::outall(
                        ' | ' . _('Check the password stored for this node')
                    );
                } else {
                    self::outall(
                        ' * ' . _('Cannot connect to') . ' ' . $nodename
                    );
                }
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
            // Both lists are reset per NODE, not per item. Neither branch
            // below assigns unconditionally -- the remote one only fires
            // when the file is already there, and the local one writes
            // index 0 -- so without this they carry the previous node's
            // listing into the next node's comparison, and on the very
            // first replication of a file $remotefilescheck is never
            // assigned at all. natcasesort() then gets null, which under
            // PHP 8 stops the transfer dead before a single byte moves:
            // the daemon's last log line is the item name and nothing
            // says why. dev-branch resets $remotefilescheck here for the
            // same reason; the 2018 rework of this method (49c1c87a9)
            // dropped it on this line.
            $localfilescheck = [];
            $remotefilescheck = [];
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
     * one word and honors backslash escapes inside it, so escaping the
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
        // safe to unset from.
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
                    self::outall(
                        ' | '
                        . _('Sync finished - ')
                        . print_r($proc, true)
                    );
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
