<?php
/**
 * Multicast task generator/finder
 *
 * PHP version 7.4+
 *
 * @category MulticastTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Multicast task generator/finder
 *
 * @category MulticastTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastTask extends FOGService
{
    /**
     * Gets all the multicast tasks
     *
     * @param string $root            root to look for items
     * @param int    $myStorageNodeID this services storage id
     * @param string $queuedStates    the queued states.
     *
     * @return array
     */
    public function getAllMulticastTasks(
        $root,
        $myStorageNodeID,
        $queuedStates
    ) {
        Route::indiv(
            'storagenode',
            $myStorageNodeID
        );
        $StorageNode = json_decode(
            Route::getData()
        );
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTER',
            array(
                'StorageNode' => &$StorageNode,
                'FOGServiceClass' => __CLASS__
            )
        );
        if (!$StorageNode->isMaster) {
            return;
        }
        // The interface udp-sender binds. Two sources can answer this and
        // they disagree in practice, so the precedence is deliberate.
        //
        // The routing table wins. It answers "which device owns the address
        // receivers reach this node at", which is definitionally the one to
        // send from. The node's own Interface field (ngmInterface) is an
        // unvalidated string that, until now, nothing ever read -- it is
        // written onto every session as msInterface and then dropped -- so
        // it has been free to drift since install time with no feedback.
        // A field nothing reads is a field nobody maintains, and honouring
        // it outright would break installs that work today. So it is
        // demoted to a fallback for the case the routing lookup genuinely
        // cannot answer -- a VIP, a bond, an address with no matching
        // kernel route -- where the sender previously launched with no
        // --interface at all and silently chose its own. Mismatches are
        // logged rather than acted on, so a stale value becomes visible
        // instead of staying invisible. Refs #908.
        $Interface = self::getMasterInterface(
            self::resolveHostname(
                $StorageNode->ip
            )
        );
        // Said once per node, not every tick. This is re-derived on every
        // pass, so logging unconditionally writes a line every sleep
        // interval for as long as the field stays stale -- some 8,600 a day
        // on the reference server. Keyed on the pair so a genuine change
        // still reports.
        static $mismatchNoted = [];
        $configuredInterface = trim((string)($StorageNode->interface ?? ''));
        if ($Interface
            && $configuredInterface
            && $configuredInterface !== $Interface
        ) {
            $noted = $configuredInterface . '|' . $Interface;
            if (($mismatchNoted[$myStorageNodeID] ?? null) !== $noted) {
                $mismatchNoted[$myStorageNodeID] = $noted;
                self::outall(
                    sprintf(
                        ' | ' . _('Ignoring configured interface %s; %s routes over %s'),
                        $configuredInterface,
                        $StorageNode->ip,
                        $Interface
                    )
                );
            }
        }
        if (!$Interface) {
            $Interface = $configuredInterface;
        }
        $myStorageGroupID = $StorageNode->storagegroupID;
        unset($StorageNode);
        // Scope sessions to the group this node actually serves. This used
        // to be an unscoped Route::active('multicastsession'), so every
        // master evaluated every session and the only thing standing between
        // two groups and two udp-senders on one session was whether both
        // happened to hold the image file -- which replication makes likely.
        Route::listem(
            'multicastsession',
            'name',
            false,
            array(
                'stateID' => $queuedStates,
                'storagegroupID' => $myStorageGroupID
            )
        );
        $Tasks = json_decode(
            Route::getData()
        );
        $NewTasks = [];
        foreach ($Tasks->multicastsessions as &$Task) {
            $find = ['msID' => $Task->id];
            Route::ids(
                'multicastsessionassociation',
                $find,
                'taskID'
            );
            $taskIDs = json_decode(Route::getData(), true);
            // udp-sender waits for this many receivers before transmitting,
            // so it must be the number expected to join, not merely the
            // number that happen to have joined by the time the daemon first
            // sees the session. Using the joined count alone made a named
            // session start as soon as its early arrivals connected, which
            // is exactly the straggler that sessclients exists to wait for.
            $count = max(
                count($taskIDs ?: []),
                (int)$Task->sessclients
            );
            if ($count < 1) {
                self::getClass('MulticastSessionManager')->update(
                    ['id' => $Task->id],
                    '',
                    [
                        'stateID' => self::getCancelledState(),
                        'name' => ''
                    ]
                );
                self::outall(
                    _('Task not created as there are no associated tasks')
                );
                self::outall(
                    _('Or there was no number defined for joining session')
                );
                continue;
            }
            // Route::indiv() answers a missing row with sendResponse(404),
            // which ends in HTTPResponseCodes::breakHead()'s exit. That is
            // correct for a web request and fatal here: it terminates the
            // forked daemon child outright, with nothing written to
            // multicast.log and no exception for the service loop to catch,
            // so multicast stops dead until someone restarts the unit. All
            // it takes is deleting an image while a session for it is still
            // queued. Check the row exists first and skip the session
            // instead -- the REST API's exit is load-bearing everywhere
            // else, so it is left alone.
            //
            // isValid(), not an existence check: indiv() gates on isValid()
            // rather than on the row being present, so an image that exists
            // but fails validation -- osID of 0, a blank name or path --
            // takes the daemon down exactly as a deleted one does. Testing
            // for existence alone would leave that half of the bug open.
            //
            // imageID is the session's raw msImage. The expanded `image`
            // object below cannot answer this: it keeps whatever id it was
            // constructed with, so it reports a deleted image as present.
            // Refs #907.
            $imageID = (int)($Task->imageID ?? 0);
            if (!self::getClass('Image', $imageID)->isValid()) {
                self::outall(
                    sprintf(
                        ' | ' . _('Image %s for session %s is missing or invalid; skipping'),
                        $imageID,
                        $Task->name
                    )
                );
                continue;
            }
            Route::indiv(
                'image',
                $Task->image->id
            );
            $Image = json_decode(
                Route::getData()
            );
            $fullPath = sprintf('%s/%s', $root, $Task->logpath);
            if (!file_exists($fullPath)) {
                self::outall(_(' | Unable to find image path'));
                continue;
            }
            $NewTasks[] = new self(
                $Task->id,
                $Task->name,
                $Task->port,
                $fullPath,
                $Interface,
                $count,
                $Task->isDD,
                $Image->osID,
                ($Task->clients == -2 ? 1 : 0),
                $taskIDs,
                $myStorageNodeID
            );
            unset($Task);
        }
        return array_filter($NewTasks);
    }
    /**
     * Session ID
     *
     * @var int
     */
    private $_intID;
    /**
     * The session name
     *
     * @var string
     */
    private $_strName;
    /**
     * The session port
     *
     * @var int
     */
    private $_intPort;
    /**
     * The session image
     *
     * @var string
     */
    private $_strImage;
    /**
     * The session interface to use
     *
     * @var string
     */
    private $_strEth;
    /**
     * The number of clients
     *
     * @var int
     */
    private $_intClients;
    /**
     * The sessions task ids
     *
     * @var array
     */
    private $_taskIDs;
    /**
     * The sessions image type
     *
     * @var int
     */
    private $_intImageType;
    /**
     * The sessions osid
     *
     * @var int
     */
    private $_intOSID;
    /**
     * Is this session a joined session
     *
     * @var bool
     */
    private $_isNameSess;
    /**
     * The multicast session class
     *
     * @var object
     */
    private $_MultiSess;
    /**
     * The storage node this task's sender belongs to
     *
     * @var int
     */
    private $_intNodeID = 0;
    /**
     * This tasks process reference
     *
     * @var resource
     */
    public $procRef;
    /**
     * This tasks process piped info
     *
     * @var resource
     */
    public $procPipes;
    /**
     * Initializes the task so multicast man can process
     *
     * @param int    $id        the id
     * @param string $name      the name
     * @param int    $port      the port
     * @param string $image     the image
     * @param string $eth       the interface
     * @param int    $clients   the number of clients
     * @param int    $imagetype the image type
     * @param int    $osid      the os id
     * @param bool   $nameSess  the named session
     * @param array  $taskIDs   the task ids
     * @param int    $nodeID    the storage node owning this sender
     *
     * @return void
     */
    public function __construct(
        $id = '',
        $name = '',
        $port = '',
        $image = '',
        $eth = '',
        $clients = '',
        $imagetype = '',
        $osid = '',
        $nameSess = '',
        $taskIDs = '',
        $nodeID = 0
    ) {
        parent::__construct();
        $this->_intID = $id;
        $this->_strName = $name;
        // The port stored on the session is authoritative. This used to be
        // overridden with FOG_MULTICAST_PORT_OVERRIDE, which forced every
        // concurrent session onto one portbase; that setting is now a pool
        // allocated from at session creation, so re-reading it here would
        // both undo the allocation and fail outright on a list.
        $this->_intPort = $port;
        $this->_strImage = $image;
        $this->_strEth = $eth;
        $this->_intClients = $clients;
        $this->_intImageType = $imagetype;
        $this->_intOSID = $osid;
        $this->_isNameSess = $nameSess;
        $this->_taskIDs = $taskIDs;
        $this->_intNodeID = (int)$nodeID;
        $this->_MultiSess = new MulticastSession($this->getID());
    }
    /**
     * Is this a named session
     *
     * @return bool
     */
    public function isNamedSessionFinished()
    {
        if ($this->_isNameSess
                && $this->_MultiSess->get('clients') == 0
                && !$this->isRunning($this->procRef)) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * Returns the task ids
     *
     * @return array
     */
    public function getTaskIDs()
    {
        return $this->_taskIDs;
    }
    /**
     * Returns the id
     *
     * @return int
     */
    public function getID()
    {
        return $this->_intID;
    }
    /**
     * Returns the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_strName;
    }
    /**
     * Returns the image path
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->_strImage;
    }
    /**
     * Returns the image type
     *
     * @return int
     */
    public function getImageType()
    {
        return $this->_intImageType;
    }
    /**
     * Returns the image's storage format
     *
     * Formats 2, 4 and 6 are the "Split 200MiB" options -- the same three
     * FOS tests for when it decides to restore a partition with a
     * "<name>*" glob rather than an exact filename. Mirroring the
     * client's own condition is deliberate: this bug class comes from the
     * two ends disagreeing about which files make up a partition.
     *
     * @return int
     */
    public function getImageFormat()
    {
        return (int)self::getClass(
            'Image',
            $this->_MultiSess->get('image')
        )->get('format');
    }
    /**
     * Returns the client count
     *
     * @return int
     */
    public function getClientCount()
    {
        return $this->_intClients;
    }
    /**
     * Returns the port
     *
     * @return int
     */
    public function getPortBase()
    {
        return $this->_intPort;
    }
    /**
     * Returns the interface
     *
     * @return string
     */
    public function getInterface()
    {
        return $this->_strEth;
    }
    /**
     * Returns the os id
     *
     * @return int
     */
    public function getOSID()
    {
        return $this->_intOSID;
    }
    /**
     * Returns the udpcast log file
     *
     * @return string
     */
    public function getUDPCastLogFile()
    {
        // By name, not by position -- see the note in MulticastManager's
        // constructor. A missing SERVICE_LOG_PATH row used to leave $logpath
        // undefined and put the log filename in its place, so the udpcast log
        // was written to a path built out of the wrong setting.
        $filenam = self::getSetting('MULTICASTLOGFILENAME');
        $logpath = self::getSetting('SERVICE_LOG_PATH');
        return $this->altLog = sprintf(
            '/%s/%s.udpcast.%s',
            trim($logpath, '/'),
            $filenam,
            $this->getID()
        );
    }
    /**
     * Returns the bitrate max
     *
     * @return string
     */
    public function getBitrate()
    {
        return self::getClass(
            'Image',
            $this->_MultiSess->get('image')
        )->getStorageGroup()
        ->getMasterStorageNode()
        ->get('bitrate');
    }
    /**
     * Returns the rexmit hello interval
     *
     * @return string
     */
    public function getHelloInterval()
    {
        return self::getClass(
            'Image',
            $this->_MultiSess->get('image')
        )->getStorageGroup()
        ->getMasterStorageNode()
        ->get('helloInterval');
    }


    /**
     * Returns the partition id to be cloned, 0 for all
     *
     * @return int
     */
    public function getPartitions()
    {
        return (int)self::getClass(
            'Image',
            $this->_MultiSess->get('image')
        )->getPartitionType();
    }
    /**
     * Returns the session class
     *
     * @return object
     */
    public function getSess()
    {
        return $this->_MultiSess;
    }
    /**
     * Records a partition image file, if that is what it is.
     *
     * A partition captured in a "Split 200MiB" format is on disk as
     * <name>.000, <name>.001 ... and never as a bare <name>. The extension
     * test here used to be an exact 'img', and sscanf('d1p1.img.000',
     * 'd1p%d.%s') yields 'img.000', so every chunk of every split
     * partition was silently dropped and the partition was never
     * transmitted at all. The list holds one entry per partition -- the
     * base name -- and the send loop expands it back into the chunks, the
     * way FOS does with its "<name>*" glob. Refs #898.
     *
     * @param array  $filelist the list being built
     * @param string $filename the on-disk filename
     * @param string $ext      the extension sscanf pulled off it
     * @param bool   $split    whether the image is in a split format
     *
     * @return void
     */
    private function _addPartFile(&$filelist, $filename, $ext, $split)
    {
        if ($ext === 'img') {
            $filelist[] = $filename;
            return;
        }
        if (!$split || !preg_match('/^img\.[0-9]+$/', (string)$ext)) {
            return;
        }
        $base = preg_replace('/\.[0-9]+$/', '', $filename);
        if (!in_array($base, (array)$filelist, true)) {
            $filelist[] = $base;
        }
    }
    /**
     * Sets/Gets the command needed to start the tasking
     *
     * @return string
     */
    public function getCMD()
    {
        unset(
            $filelist,
            $buildcmd,
            $cmd
        );
        // By name, not by position -- see the note in MulticastManager's
        // constructor. This one built the udp-sender command line, so a
        // single missing row slid the duplex setting into the multicast
        // address, the rendezvous address into duplex, and left maxwait
        // undefined.
        $address = self::getSetting('FOG_MULTICAST_ADDRESS');
        $duplex = self::getSetting('FOG_MULTICAST_DUPLEX');
        $multicastrdv = self::getSetting('FOG_MULTICAST_RENDEZVOUS');
        $maxwait = self::getSetting('FOG_UDPCAST_MAXWAIT');
        $maxwait = (int)$maxwait;
        if (!$maxwait || $maxwait <= 0) {
            $maxwait = 10;
        }
        if ($address) {
            // Each concurrent session needs its own data address. With a
            // port pool configured the port's position in that pool gives
            // one directly, which is what guarantees two sessions cannot
            // land on the same address. Without a pool this falls back to
            // deriving an offset from the portbase, wrapped by the session
            // cap -- the historical behaviour, and the reason
            // FOG_MULTICAST_MAX_SESSIONS ended up doubling as a modulus.
            // max(1, ...) because an unset cap made this a modulo by zero.
            $pool = MulticastSession::portPool();
            $index = array_search((int)$this->getPortBase(), $pool, true);
            if (false !== $index) {
                $offset = $index + 1;
            } else {
                $offset = (
                    $this->getPortBase() / 2 + 1
                ) % max(1, (int)self::getSetting('FOG_MULTICAST_MAX_SESSIONS'));
            }
            $address = long2ip(ip2long($address) + $offset);
        }
        /*
         * Everything below is interpolated into a single string that
         * startTask() hands to proc_open(), which runs it through
         * /bin/sh as root -- so ';', '$()' and backticks in any token
         * are live commands. Several of these tokens are attacker
         * reachable: the node bitrate and hello interval, the duplex
         * and rendezvous settings, the image path and the on-disk
         * filenames below. Write-side validation cannot be the fix,
         * because Route::edit() copies a JSON body straight into a
         * model's databaseFields with no validation at all, and
         * because filenames are never "written" through FOG in the
         * first place. So each value is validated and/or escaped here
         * at the sink, which also covers sessions already queued with
         * a historical msLogPath.
         * Reported by Aisle Research (065 / 3.27.1).
         */
        $bitrate = trim((string)$this->getBitrate());
        if ('' !== $bitrate
            && !preg_match('/^[0-9]+[kmg]?$/i', $bitrate)
        ) {
            // Not (int) cast: '100m' is a legitimate value and casting
            // would silently turn it into 100 bits/s.
            self::outall(
                sprintf(
                    ' | %s: %s',
                    _('Ignoring invalid multicast bitrate'),
                    $bitrate
                )
            );
            $bitrate = '';
        }
        $helloInterval = trim((string)$this->getHelloInterval());
        if ('' !== $helloInterval
            && !preg_match('/^[0-9]+$/', $helloInterval)
        ) {
            self::outall(
                sprintf(
                    ' | %s: %s',
                    _('Ignoring invalid multicast hello interval'),
                    $helloInterval
                )
            );
            $helloInterval = '';
        }
        if ($multicastrdv
            && !filter_var($multicastrdv, FILTER_VALIDATE_IP)
        ) {
            self::outall(
                sprintf(
                    ' | %s: %s',
                    _('Ignoring invalid multicast rendezvous address'),
                    $multicastrdv
                )
            );
            $multicastrdv = '';
        }
        if ($duplex
            && !in_array($duplex, array('--half-duplex', '--full-duplex'), true)
        ) {
            // Allowlist, not a sanitizer: the valid values start with
            // dashes, so anything that rejects or escapes leading
            // dashes would break multicast outright. Dropping an
            // unrecognized value leaves udp-sender on its own default,
            // matching the existing "empty -> no flag".
            self::outall(
                sprintf(
                    ' | %s: %s',
                    _('Ignoring invalid multicast duplex setting'),
                    $duplex
                )
            );
            $duplex = '';
        }
        $buildcmd = array(
            UDPSENDERPATH,
            (
                $helloInterval ?
                sprintf(
                    ' --rexmit-hello-interval %s',
                    escapeshellarg($helloInterval)
                ) :
                null
            ),
            (
                $bitrate ?
                sprintf(' --max-bitrate %s', escapeshellarg($bitrate)) :
                null
            ),
            (
                $this->getInterface() ?
                // Normally derived locally from `ip route`, but #908 added
                // the node's stored Interface field as a fallback, so this
                // can now genuinely come from the database. The escaping
                // was already here for exactly that eventuality.
                sprintf(' --interface %s', escapeshellarg($this->getInterface())) :
                null
            ),
            sprintf(
                // The whole-inventory fallback is gone: getAllMulticastTasks()
                // cancels any session whose count is below one before it ever
                // constructs the task, and that is the only construction site,
                // so the fallback could never fire -- but if it had, it would
                // have held the sender for every host FOG knows about.
                ' --min-receivers %d',
                $this->getClientCount()
            ),
            // {MAXWAIT} rather than '%d': the whole command used to be
            // run back through sprintf() below, and escapeshellarg()
            // does not neutralize '%'. Once the values are escaped, a
            // '%' anywhere in them (a bitrate of '100%', a filename
            // containing '%s') would turn an injection into a PHP 8
            // ArgumentCountError and kill the daemon instead. Dropping
            // the format string removes that class entirely.
            ' --max-wait {MAXWAIT}',
            (
                $address ?
                sprintf(' --mcast-data-address %s', $address) :
                null
            ),
            (
                $multicastrdv ?
                sprintf(' --mcast-rdv-address %s', $multicastrdv) :
                null
            ),
            sprintf(' --portbase %d', $this->getPortBase()),
            sprintf(' %s', $duplex),
            ' --ttl 32',
            ' --nokbd',
            ' --nopointopoint',
        );
        $buildcmd = array_values(array_filter($buildcmd));
        $filelist = array();
        // Every entry collected below is a bare filename that the send
        // loop joins onto this directory. It is the image path itself
        // in all but the single-file case, which reassigns it.
        $imagedir = rtrim($this->getImagePath(), DS);
        // Read once: the collection below and the send loop both need it.
        $split = in_array($this->getImageFormat(), array(2, 4, 6), true);
        switch ($this->getImageType()) {
            case 1:
                switch ($this->getOSID()) {
                    case 1:
                    case 2:
                        if (is_file($this->getImagePath())) {
                            // Here the image path names the image file
                            // itself, not a directory holding one. The
                            // send loop still assembles
                            // "<directory>/<filename>", so it gets the
                            // containing directory and the basename;
                            // pushing the whole path made every --file
                            // argument "<imagepath>/<imagepath>", which
                            // cannot exist -- is_file() just proved the
                            // first half is a file, so it holds
                            // nothing. Multicast of a single-file image
                            // has never worked on this branch.
                            $imagedir = rtrim(
                                dirname($this->getImagePath()),
                                DS
                            );
                            $filelist[] = basename($this->getImagePath());
                            break;
                        }
                        // no break
                    case 5:
                    case 6:
                    case 7:
                        $files = scandir($this->getImagePath());
                        $sys = preg_grep('#(sys\.img\..*$)#i', $files);
                        $rec = preg_grep('#(rec\.img\..*$)#i', $files);
                        if (count($sys) || count($rec)) {
                            if (count($sys)) {
                                $filelist[] = 'sys.img.*';
                            }
                            if (count($rec)) {
                                $filelist[] = 'rec.img.*';
                            }
                        } else {
                            $filename = 'd1p%d.%s';
                            $iterator = new DirectoryIterator(
                                $this->getImagePath()
                            );
                            foreach ($iterator as $fileInfo) {
                                if ($fileInfo->isDot()) {
                                    continue;
                                }
                                sscanf(
                                    $fileInfo->getFilename(),
                                    $filename,
                                    $part,
                                    $ext
                                );
                                $this->_addPartFile(
                                    $filelist,
                                    $fileInfo->getFilename(),
                                    $ext,
                                    $split
                                );
                                unset($part, $ext);
                            }
                            unset($iterator);
                        }
                        unset($files, $sys, $rec);
                        break;
                    default:
                        $filename = 'd1p%d.%s';
                        $iterator = new DirectoryIterator(
                            $this->getImagePath()
                        );
                        foreach ($iterator as $fileInfo) {
                            if ($fileInfo->isDot()) {
                                continue;
                            }
                            sscanf(
                                $fileInfo->getFilename(),
                                $filename,
                                $part,
                                $ext
                            );
                            $this->_addPartFile(
                                $filelist,
                                $fileInfo->getFilename(),
                                $ext,
                                $split
                            );
                            unset($part, $ext);
                        }
                        unset($iterator);
                        break;
                }
                break;
            case 2:
                $filename = 'd1p%d.%s';
                $iterator = new DirectoryIterator(
                    $this->getImagePath()
                );
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    sscanf(
                        $fileInfo->getFilename(),
                        $filename,
                        $part,
                        $ext
                    );
                    $this->_addPartFile(
                        $filelist,
                        $fileInfo->getFilename(),
                        $ext,
                        $split
                    );
                    unset($part, $ext);
                }
                unset($iterator);
                break;
            case 3:
                $filename = 'd%dp%d.%s';
                $iterator = new DirectoryIterator(
                    $this->getImagePath()
                );
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    sscanf(
                        $fileInfo->getFilename(),
                        $filename,
                        $device,
                        $part,
                        $ext
                    );
                    $this->_addPartFile(
                        $filelist,
                        $fileInfo->getFilename(),
                        $ext,
                        $split
                    );
                    unset($device, $part, $ext);
                }
                unset($iterator);
                break;
            case 4:
                $iterator = new DirectoryIterator(
                    $this->getImagePath()
                );
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    $filelist[] = $fileInfo->getFilename();
                }
                unset($iterator);
                break;
        }
        natcasesort($filelist);
        // $this->, not self::: getPartitions() is an instance accessor like
        // every other one on this class, and self:: binds statically to
        // MulticastTask, so any subclass override is silently ignored. No
        // subclass ships today, but it makes the method untestable in
        // isolation -- an override is skipped, the real one runs
        // Route::indiv() on whatever session is loaded, and a miss exits the
        // process outright (the #907 path), which reads as the harness dying
        // for no reason. Behaviour in the daemon is unchanged.
        $partid = $this->getPartitions();
        if ($partid < 1) {
            $filelist = array_values((array)$filelist);
        } else {
            $filelist = array_values(
                preg_grep("/^d[0-9]p$partid\.img$/", (array)$filelist)
            );
        }
        /*
         * One entry per udp-sender invocation, each holding the list of
         * files whose concatenation is that invocation's stream.
         *
         * FOS opens one udp-receiver per *partition*, not per file
         * (funcs.sh writeImage() with mc=yes), and on the unicast path
         * feeds that partition with "cat <path>/<name>*". So a partition
         * split across several chunks has to arrive as a single stream;
         * sending a sender per chunk misassigns every later partition.
         * 'sys.img.*' is one such partition, while 'rec.img.NNN' is one
         * partition each -- the asymmetry is FOS's, mirrored here rather
         * than guessed at. Refs #897.
         *
         * 'sys.img.*' and 'rec.img.*' are pushed as literal wildcards
         * above and were expanded by /bin/sh, which the escaping below
         * would turn into a single literal filename that does not
         * exist. Expand them here instead. glob()'s default sort is
         * the same collating order the shell used, so the on-the-wire
         * order -- the only thing keeping the receivers in sync -- does
         * not change. Part of the 065 sink fix.
         */
        $streams = array();
        $claimed = array();
        foreach ($filelist as $file) {
            if (isset($claimed[$file])) {
                continue;
            }
            if (false !== strpos($file, '*')) {
                $matches = glob($imagedir . DS . $file);
                if (empty($matches)) {
                    self::outall(
                        sprintf(
                            ' | %s: %s',
                            _('No files matched multicast image pattern'),
                            $file
                        )
                    );
                    continue;
                }
                $matches = array_map('basename', $matches);
                if ('sys.img.*' === $file) {
                    $streams[] = $matches;
                    continue;
                }
                foreach ($matches as $match) {
                    $streams[] = array($match);
                }
                continue;
            }
            if (!$split) {
                $streams[] = array($file);
                continue;
            }
            /*
             * Split formats store a partition as <name>.000, <name>.001
             * ... and never as a bare <name>. FOS restores it with
             * exactly this glob and cats the result into one receiver,
             * so send it as one stream. $claimed keeps a chunk that also
             * reached the list on its own from being sent twice.
             * Refs #898.
             */
            $chunks = glob($imagedir . DS . $file . '*');
            if (empty($chunks)) {
                self::outall(
                    sprintf(
                        ' | %s: %s',
                        _('No files matched multicast image pattern'),
                        $file
                    )
                );
                continue;
            }
            $chunks = array_map('basename', $chunks);
            foreach ($chunks as $chunk) {
                $claimed[$chunk] = true;
            }
            $streams[] = $chunks;
        }
        ob_start();
        // $i is gone with the sprintf: both arms of the max-wait
        // ternary it fed were already identical ($maxwait * 60).
        foreach ($streams as $stream) {
            $cmd = str_replace(
                '{MAXWAIT}',
                (string)($maxwait * 60),
                implode($buildcmd)
            );
            $paths = array();
            foreach ($stream as $file) {
                $paths[] = escapeshellarg($imagedir . DS . $file);
            }
            if (count($paths) > 1) {
                // udp-sender accepts a single --file and discards the
                // rest ("Extra argument ... ignored"), which is why a
                // multi-chunk partition used to go out truncated. With no
                // --file it reads stdin, so the chunks are concatenated
                // onto it in the same order cat would use unicast.
                printf(
                    'cat %s | %s;',
                    implode(' ', $paths),
                    $cmd
                );
                continue;
            }
            printf('%s --file %s;', $cmd, $paths[0]);
        }
        unset($filelist, $streams, $buildcmd);
        return ob_get_clean();
    }
    /**
     * Starts our tasking as needed
     *
     * @return bool
     */
    public function startTask()
    {
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }
        $this->startTasking($this->getCMD(), $this->getUDPCastLogFile());
        $this->procRef = array_shift($this->procRef);
        $running = $this->isRunning($this->procRef);
        // Persist who owns this sender. procRef alone is in-process memory,
        // so without this a daemon restart cannot tell an orphaned sender
        // from a session that never started. Saving the whole object is safe
        // here, unlike in updateStats(): _MultiSess was loaded when this
        // task was constructed a moment ago, so it is not yet stale.
        $this->_MultiSess
            ->set('stateID', self::getQueuedState())
            ->set('senderpid', $running ? (int)$this->getPID($this->procRef) : 0)
            ->set('sendernode', $running ? $this->_intNodeID : 0);
        if ($running) {
            // Only set on a successful start. Leaving it untouched otherwise
            // avoids writing NULL to the DATETIME through the ORM -- PDODB
            // does not throw on query error, so that would fail silently.
            $this->_MultiSess->set(
                'senderstart',
                self::niceDate()->format('Y-m-d H:i:s')
            );
        }
        $this->_MultiSess->save();
        return $running;
    }
    /**
     * Kills the tasking as needed
     *
     * @return bool
     */
    public function killTask()
    {
        $gone = $this->killTasking();
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }
        // clearSenderRef() self-guards on liveness, so this stays
        // unconditional: on a successful kill it releases the row, and on a
        // sender that survived it deliberately does nothing, leaving the
        // reference for _reconcileOrphanedSenders() to find.
        $this->clearSenderRef();
        return $gone;
    }
    /**
     * Clears the persisted sender ownership for this session.
     *
     * Called once the sender is gone (killed, completed or cancelled) so
     * startup reconciliation does not later mistake a stale row for a live
     * orphan. senderstart is deliberately left alone: nothing reads it while
     * senderpid is 0, and keeping it records when the sender last ran.
     *
     * This writes the two columns directly rather than saving _MultiSess.
     * That object was loaded when the task was constructed and is stale by
     * the time a sender is killed -- FOGController::save() writes every
     * field it holds, so saving it here would put the pre-cancel state,
     * name and client count back and hand the session straight back to the
     * daemon to start again.
     *
     * @return bool True when the reference was released.
     */
    public function clearSenderRef()
    {
        // Refuse while the sender is still alive. Zeroing senderpid is
        // precisely what makes a session invisible to
        // _reconcileOrphanedSenders(), so doing it to a sender that
        // survived killTask() strands a udp-sender holding its portbase
        // with nothing left that would ever clean it up. The pid tested is
        // the pre-kill one still held on the in-memory session object,
        // which is the whole reason the manager clears AFTER cancel() and
        // complete() rather than inside them.
        $pid = (int)$this->_MultiSess->get('senderpid');
        if ($pid > 0
            && $this->isPidAlive($pid, basename(UDPSENDERPATH))
        ) {
            return false;
        }
        self::getClass('MulticastSessionManager')->update(
            array('id' => $this->_intID),
            '',
            array(
                'senderpid' => 0,
                'sendernode' => 0
            )
        );
        return true;
    }
    /**
     * Updates the stats of the tasking
     *
     * @return void
     */
    public function updateStats()
    {
        Route::listem(
            'multicastsessionassociation',
            'msID',
            false,
            ['msID' => $this->_intID]
        );
        $MSAssocs = json_decode(
            Route::getData()
        )->multicastsessionassociations;
        $TaskPercent = [0];
        foreach ($MSAssocs as &$Task) {
            $TaskPercent[] = self::getClass('Task', $Task->taskID)->get('percent');
            unset($Task);
        }
        $TaskPercent = array_unique((array)$TaskPercent);
        // Write the one column this owns. updateStats() runs against the
        // task held in $KnownTasks, whose _MultiSess was loaded when the
        // session was first seen, and FOGController::save() writes every
        // field it holds -- so saving the whole object here put that
        // first-seen snapshot back every tick. That silently undid the
        // clients counter TaskQueue::checkIn() increments as hosts arrive,
        // which is why a session's client count never climbed.
        self::getClass('MulticastSessionManager')->update(
            ['id' => $this->_intID],
            '',
            ['percent' => max($TaskPercent)]
        );
    }
    /**
     * Updates task ID list in case of MC session joins via PXE menu
     *
     * @return void
     */
    public function setTaskIDs($newTaskIDs)
    {
        $this->_taskIDs = $newTaskIDs;
    }
}
