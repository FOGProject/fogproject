<?php
/**
 * Multicast task generator/finder
 *
 * PHP version 5
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
     * @param string $queuedStates    The queued states.
     *
     * @return array
     */
    public static function getAllMulticastTasks(
        $root,
        $myStorageNodeID,
        $queuedStates
    ) {
        // getItem(), not indiv(): a miss answers with null here rather than
        // exiting the forked daemon child outright. Refs #907.
        $StorageNode = Route::getItem(
            'storagenode',
            $myStorageNodeID
        );
        if (!$StorageNode) {
            return;
        }
        self::$HookManager->processEvent(
            'CHECK_NODE_MASTER',
            [
                'StorageNode' => &$StorageNode,
                'FOGServiceClass' => __CLASS__
            ]
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
        // The reference server has 'enp0s31f6' recorded for a node whose
        // address lives on 'eno2'; honouring it outright would have broken
        // multicast on installs that work today. A field nothing reads is a
        // field nobody maintains, so it is demoted to a fallback for the
        // case the routing lookup genuinely cannot answer -- a VIP, a bond,
        // an address with no matching kernel route -- where the sender
        // previously launched with no --interface at all and silently chose
        // its own. Mismatches are logged rather than acted on, so a stale
        // value becomes visible instead of staying invisible. Refs #908.
        $Interface = self::getMasterInterface(
            self::resolveHostname(
                $StorageNode->ip
            )
        );
        // Said once per node, not every tick. This is re-derived on every
        // pass, so logging unconditionally writes a line every sleep
        // interval for as long as the field stays stale -- some 8,600 a day
        // on the reference server. Keyed on the pair so a genuine change
        // still reports. Same shape as the claim notice in 886966fa7.
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
        $Tasks = Route::getList(
            'multicastsession',
            [
                'stateID' => $queuedStates,
                'storagegroupID' => $myStorageGroupID
            ]
        );
        $NewTasks = [];
        foreach ($Tasks as $Task) {
            $find = ['msID' => $Task->id];
            $taskIDs = Route::getIds(
                'multicastsessionassociation',
                $find,
                'taskID'
            );
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
                    _('Task not created as there are no associated Tasks')
                );
                self::outall(
                    _('Or there was no number defined for joining session')
                );
                continue;
            }
            // This guard stays, and it is narrower than it was. getItem()
            // below ends the half of #907 that killed the daemon: a deleted
            // image now answers null instead of reaching indiv()'s
            // sendResponse(404) and breakHead()'s exit.
            //
            // It does not end the other half. getItem() establishes
            // *existence*, while indiv() gates on isValid(), so an image that
            // is present but does not validate -- osID of 0, a blank name or
            // path -- still reaches indiv() and still fails. It raises rather
            // than exiting now, so the daemon survives, but the exception
            // unwinds the whole collection pass and takes every other queued
            // session with it. Testing validity here keeps the failure scoped
            // to the one session it belongs to. Refs #907, ADR 0011.
            if (!self::getClass('Image', $Task->image)->isValid()) {
                self::outall(
                    sprintf(
                        ' | ' . _('Image %s for session %s is missing or invalid; skipping'),
                        $Task->image,
                        $Task->name
                    )
                );
                continue;
            }
            $Image = Route::getItem(
                'image',
                $Task->image
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
     * Get session clients
     *
     * @return bool
     */
    public function getSessClients()
    {
        return $this->_MultiSess->get('clients') == 0;
    }
    /**
     * Is this a named session
     *
     * @return bool
     */
    public function isNamedSession()
    {
        return (bool)$this->_isNameSess;
    }
    /**
     * Is the named session finished?
     *
     * @return bool
     */
    public function isNamedSessionFinished()
    {
        return (
            $this->isNamedSession() &&
            $this->getSessClients() &&
            !$this->isRunning($this->procRef)
        );
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
     * Formats 2, 4 and 6 are the "Split 200MiB" options
     * (imagemanagement.page.php:155-206) -- the same three FOS tests for
     * when it decides to restore a partition with a "<name>*" glob rather
     * than an exact filename. Mirroring the client's own condition is
     * deliberate: this bug class comes from the two ends disagreeing about
     * which files make up a partition.
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
        // FOG_LOG_DIR rather than the SERVICE_LOG_PATH globalSetting, for the
        // reason spelled out in FOGService::__construct(): the setting is a
        // record of where the installer put the logs, not a second control
        // over it. This file has to land beside the other service logs or the
        // log viewer cannot reach it.
        $filenam = self::getSetting('MULTICASTLOGFILENAME');
        return $this->altLog = sprintf(
            '%s%s.udpcast.%s',
            rtrim(FOG_LOG_DIR, DS) . DS,
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
     * Returns the rexmit-hello-interval
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
        // getItem(), not indiv(): a session whose image was deleted while it
        // was running used to exit the daemon child here. 0 is the same
        // answer a whole-disk image gives -- send every file -- which is the
        // conservative one when the partition cannot be determined. Refs #907.
        $Image = Route::getItem(
            'image',
            $this->_MultiSess->get('image')
        );
        if (!$Image) {
            return 0;
        }
        return (int)$Image->imagepartitiontype->type;
    }
    /**
     * Returns the max timeout setting
     *
     * @return int
     */
    public function getMaxwait()
    {
        return (int)$this->getSess()->get('maxwait');
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
     * Returns the storage node this task would send from.
     *
     * Exposed so the manager can compare it against the session's recorded
     * sender owner before starting a second sender for it.
     *
     * @return int
     */
    public function getNodeID()
    {
        return $this->_intNodeID;
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
     * Returns the LV image filenames a dNpM.lvm sidecar names, in sidecar
     * line order. udpcast synchronizes by file order alone, so this must
     * skip exactly the lines the FOS client skips (swap LVs and volumes
     * with no image file); any divergence misassigns every later stream.
     * Returns false for a sidecar this server cannot parse (a newer
     * LVMFORMAT) — the client refuses those before opening a receiver.
     *
     * @param string $sidecar full path to the dNpM.lvm sidecar
     *
     * @return array|false
     */
    private function _getLVMImageList($sidecar)
    {
        $lines = @file(
            $sidecar,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        if (!$lines) {
            return false;
        }
        switch (trim(array_shift($lines))) {
            case 'LVMFORMAT 1':
                $fstypefield = 4;
                $imagefield = 5;
                break;
            case 'LVMFORMAT 2':
                $fstypefield = 5;
                $imagefield = 6;
                break;
            default:
                return false;
        }
        $lvimages = [];
        foreach ($lines as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if ($fields[0] !== 'LV') {
                continue;
            }
            if (isset($fields[$fstypefield])
                && $fields[$fstypefield] == 'swap'
            ) {
                continue;
            }
            $image = (
                isset($fields[$imagefield]) ?
                $fields[$imagefield] :
                ''
            );
            if ($image == '' || $image == '-') {
                continue;
            }
            $lvimages[] = $image;
        }
        return $lvimages;
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
        $keys = [
            'FOG_MULTICAST_ADDRESS',
            'FOG_MULTICAST_DUPLEX',
            'FOG_MULTICAST_RENDEZVOUS'
        ];
        list(
            $address,
            $duplex,
            $multicastrdv
        ) = self::getSetting($keys);
        if ($address) {
            // Each concurrent session needs its own data address. With a
            // port pool configured the port's position in that pool gives
            // one directly, which is what guarantees two sessions cannot
            // land on the same address. Without a pool this falls back to
            // deriving an offset from the portbase, wrapped by the session
            // cap -- the historical behaviour, and the reason
            // FOG_MULTICAST_MAX_SESSIONS ended up doubling as a modulus.
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
        $maxwait = $this->getMaxwait();
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
         * because filenames and .lvm sidecar contents are never
         * "written" through FOG in the first place. So each value is
         * validated and/or escaped here at the sink, which also covers
         * sessions already queued with a historical msLogPath.
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
            && !in_array($duplex, ['--half-duplex', '--full-duplex'], true)
        ) {
            // Allowlist, not a sanitizer: the valid values start with
            // dashes, so anything that rejects or escapes leading
            // dashes would break multicast outright. These two are
            // exactly what the configuration page's select offers.
            // Dropping an unrecognized value leaves udp-sender on its
            // own default, matching the existing "empty -> no flag".
            self::outall(
                sprintf(
                    ' | %s: %s',
                    _('Ignoring invalid multicast duplex setting'),
                    $duplex
                )
            );
            $duplex = '';
        }
        $buildcmd = [
            UDPSENDERPATH,
            (
                $bitrate ?
                sprintf(' --max-bitrate %s', escapeshellarg($bitrate)) :
                null
            ),
            (
                $helloInterval ?
                sprintf(
                    ' --rexmit-hello-interval %s',
                    escapeshellarg($helloInterval)
                ) :
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
        ];
        $buildcmd = array_values(array_filter($buildcmd));
        // Initialised up front because the LVM scan below reads it before
        // adding its placeholder. An image directory where nothing matched
        // -- every partition split, or a stray directory -- reached that
        // array_diff() with $filelist never assigned, which is an
        // undefined-variable warning now and a TypeError later.
        $filelist = [];
        $lvfiles = [];
        $lvmscan = false;
        // Every entry collected below is a bare filename that the send
        // loop joins onto this directory. It is the image path itself
        // in all but the single-file case, which reassigns it.
        $imagedir = rtrim($this->getImagePath(), DS);
        // Read once: the collection below and the send loop both need it.
        $split = in_array($this->getImageFormat(), [2, 4, 6], true);
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
                            $lvmscan = true;
                            $filename = 'd1p%d.%s';
                            $iterator = new \DirectoryIterator(
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
                        $lvmscan = true;
                        $filename = 'd1p%d.%s';
                        $iterator = new \DirectoryIterator(
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
                break;
            case 2:
                $lvmscan = true;
                $filename = 'd1p%d.%s';
                $iterator = new \DirectoryIterator(
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
                $lvmscan = true;
                $filename = 'd%dp%d.%s';
                $iterator = new \DirectoryIterator(
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
                $iterator = new \DirectoryIterator(
                    $this->getImagePath()
                );
                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    $filelist[] = $fileInfo->getFilename();
                }
                unset($iterator);
        }
        if ($lvmscan) {
            /*
             * Per-LV LVM images (dNpM.lvm sidecars) have no dNpM.img for
             * that partition; a placeholder of that name keeps the sort
             * and single-partition filter behaving as if it existed, and
             * the send loop below expands it into the sidecar's LV image
             * files in line order — the order the FOS client restores in.
             */
            $iterator = new \DirectoryIterator(
                $this->getImagePath()
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                if (!preg_match(
                    '/^(d\d+p\d+)\.lvm$/',
                    $fileInfo->getFilename(),
                    $match
                )
                ) {
                    continue;
                }
                if ($this->getImageType() != 3
                    && strpos($match[1], 'd1p') !== 0
                ) {
                    continue;
                }
                $lvimages = $this->_getLVMImageList(
                    $this->getImagePath() . DS . $fileInfo->getFilename()
                );
                if ($lvimages === false) {
                    continue;
                }
                $placeholder = $match[1] . '.img';
                $filelist = array_diff((array)$filelist, [$placeholder]);
                $filelist[] = $placeholder;
                $lvfiles[$placeholder] = $lvimages;
            }
            unset($iterator);
        }
        @natcasesort($filelist);
        // $this->, not self::: getPartitions() is an instance accessor like
        // every other one on this class, and self:: binds statically to
        // MulticastTask, so any subclass override is silently ignored. No
        // subclass ships today, but it makes the method untestable in
        // isolation -- an override is skipped and the real one queries
        // whatever session is loaded. Behaviour in the daemon is unchanged.
        $partid = $this->getPartitions();
        if ($partid < 1) {
            $filelist = array_values((array)$filelist);
        } else {
            $filelist = array_values(
                preg_grep("/^d[0-9]p$partid\.img$/", (array)$filelist)
            );
        }
        $sendfiles = [];
        foreach ($filelist as $file) {
            if (!isset($lvfiles[$file])) {
                $sendfiles[] = $file;
                continue;
            }
            foreach ($lvfiles[$file] as $lvfile) {
                $sendfiles[] = $lvfile;
            }
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
        $streams = [];
        $claimed = [];
        foreach ($sendfiles as $file) {
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
                    $streams[] = [$match];
                }
                continue;
            }
            if (!$split) {
                $streams[] = [$file];
                continue;
            }
            /*
             * Split formats store a partition (or an LV named by a .lvm
             * sidecar) as <name>.000, <name>.001 ... and never as a bare
             * <name>. FOS restores it with exactly this glob and cats the
             * result into one receiver, so send it as one stream. $claimed
             * keeps a chunk that also reached the list on its own from
             * being sent a second time. Refs #898.
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
        foreach ($streams as $i => $stream) {
            $cmd = str_replace(
                '{MAXWAIT}',
                (string)(
                    $i == 0 ?
                    $maxwait :
                    600
                ),
                implode($buildcmd)
            );
            $paths = [];
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
        unset($filelist, $sendfiles, $streams, $lvfiles, $buildcmd);
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
        // from a session that never started.
        $this->_MultiSess
            ->set('stateID', self::getQueuedState())
            ->set('senderpid', $running ? (int)$this->getPID($this->procRef) : 0)
            ->set('sendernode', $running ? $this->_intNodeID : 0);
        if ($running) {
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
        $this->killTasking();
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }
        $this->clearSenderRef();
        return true;
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
     * @return void
     */
    public function clearSenderRef()
    {
        self::getClass('MulticastSessionManager')->update(
            ['id' => $this->getID()],
            '',
            [
                'senderpid' => 0,
                'sendernode' => 0
            ]
        );
    }
    /**
     * Updates the stats of the tasking
     *
     * @return void
     */
    public function updateStats()
    {
        $MSAssocs = Route::getIds(
            'multicastsessionassociation',
            ['msID' => $this->_intID],
            'taskID'
        );
        $TaskPercent = [];
        foreach ($MSAssocs as $TaskID) {
            $TaskPercent[] = self::getClass('Task', $TaskID)->get('percent');
        }
        $TaskPercent = array_unique($TaskPercent);
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
            ['percent' => self::maxId($TaskPercent)]
        );
    }
    /**
     * Updates task ID list in case of MC session joins via PXE menu
     *
     * @param array $newTaskIDs The array of task ids to set.
     *
     * @return void
     */
    public function setTaskIDs($newTaskIDs = [])
    {
        $this->_taskIDs = $newTaskIDs;
    }
}
