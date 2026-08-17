<?php
/**
 * Handles the session in db.
 *
 * PHP version 7.4+
 *
 * @category MulticastSession
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles the session in db.
 *
 * @category MulticastSession
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSession extends FOGController
{
    /**
     * The multicast sessions table.
     *
     * @var string
     */
    protected $databaseTable = 'multicastSessions';
    /**
     * The multicast sessions common and column names.
     *
     * @var string
     */
    protected $databaseFields = array(
        'id' => 'msID',
        'name' => 'msName',
        'port' => 'msBasePort',
        'logpath' => 'msLogPath',
        'image' => 'msImage',
        'clients' => 'msClients',
        'sessclients' => 'msSessClients',
        'interface' => 'msInterface',
        'starttime' => 'msStartDateTime',
        'percent' => 'msPercent',
        'stateID' => 'msState',
        'completetime' => 'msCompleteDateTime',
        'isDD' => 'msIsDD',
        'storagegroupID' => 'msNFSGroupID',
        'senderpid' => 'msSenderPID',
        'sendernode' => 'msSenderNode',
        'senderstart' => 'msSenderStart',
        'anon3' => 'msAnon3',
        'anon4' => 'msAnon4',
        'anon5' => 'msAnon5',
    );
    /**
     * Additional Fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'imagename',
        'state'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Image' => array(
            'id',
            'image',
            'imagename'
        ),
        'TaskState' => array(
            'id',
            'stateID',
            'state'
        )
    );
    /**
     * Gets the session's associated image object.
     *
     * @return object
     */
    public function getImage()
    {
        return new Image($this->get('image'));
    }
    /**
     * Gets the session's task state.
     *
     * @return object
     */
    public function getTaskState()
    {
        return new TaskState($this->get('stateID'));
    }
    /**
     * The states in which a session is considered to be occupying capacity.
     *
     * @return array
     */
    public static function activeStates()
    {
        return self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
    }
    /**
     * How many sessions are currently active, server wide.
     *
     * @return int
     */
    public static function activeCount()
    {
        return (int)self::getClass('MulticastSessionManager')
            ->count(array('stateID' => self::activeStates()));
    }
    /**
     * The base ports currently held by active sessions.
     *
     * @return array
     */
    public static function activePorts()
    {
        return array_map(
            'intval',
            (array)self::getSubObjectIDs(
                'MulticastSession',
                array('stateID' => self::activeStates()),
                'port'
            )
        );
    }
    /**
     * The configured pool of multicast base ports.
     *
     * FOG_MULTICAST_PORT_OVERRIDE accepts a comma separated list, one entry
     * per concurrently runnable session. Entries that udp-sender could not
     * use are dropped rather than allowed to fail at spawn time: it takes
     * the base port and base+1, so the value has to be even and leave room
     * above it. An empty or entirely unusable setting yields an empty pool,
     * which callers treat as "not configured".
     *
     * @return array
     */
    public static function portPool()
    {
        $raw = (string)self::getSetting('FOG_MULTICAST_PORT_OVERRIDE');
        $ports = array();
        foreach (preg_split('#[\s,]+#', $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            if (!ctype_digit($p)) {
                continue;
            }
            $p = (int)$p;
            if ($p < 1024 || $p > 65534 || $p % 2 !== 0) {
                continue;
            }
            $ports[$p] = $p;
        }
        return array_values($ports);
    }
    /**
     * Throws when the server is already running its configured maximum.
     *
     * FOG_MULTICAST_MAX_SESSIONS was only ever checked when a session was
     * created from Image Management, so sessions created from a host, a
     * group or a booting machine walked straight past it and the setting
     * never meant what it said.
     *
     * @throws Exception
     * @return void
     */
    public static function assertCapacity()
    {
        $max = (int)self::getSetting('FOG_MULTICAST_MAX_SESSIONS');
        if ($max > 0 && self::activeCount() >= $max) {
            throw new Exception(
                sprintf(
                    _('Server is only configured to run %d multicast tasks'),
                    $max
                )
            );
        }
    }
    /**
     * The implicit pool used when FOG_MULTICAST_PORT_OVERRIDE is not set.
     *
     * FOG_UDPCAST_STARTINGPORT is the base and each concurrent session takes
     * two ports (udp-sender uses base and base+1), so the window is
     * base .. base + 2 * FOG_MULTICAST_MAX_SESSIONS. That is exactly the
     * range the installer opens in the firewall, and the two are meant to be
     * read together -- see _firewallPortList() in lib/common/functions.sh.
     *
     * @return array
     */
    public static function defaultPortPool()
    {
        $base = (int)self::getSetting('FOG_UDPCAST_STARTINGPORT');
        // Falls back to the installer's UDPCAST_STARTINGPORT default rather
        // than to something arbitrary, so an unusable setting still lands
        // inside the firewalled window.
        if ($base < 1024 || $base > 65534 || $base % 2 !== 0) {
            $base = 63100;
        }
        $sessions = (int)self::getSetting('FOG_MULTICAST_MAX_SESSIONS');
        // 0 means "no cap" to assertCapacity(), but a pool has to be finite.
        // 64 matches the installer default and the window it firewalls.
        if ($sessions < 1) {
            $sessions = 64;
        }
        $ports = [];
        for ($p = $base; $p <= $base + (2 * $sessions) && $p <= 65534; $p += 2) {
            $ports[] = $p;
        }
        return $ports;
    }
    /**
     * Picks a base port for a new session.
     *
     * The first port no active session is holding is taken, which is what
     * makes the pool a concurrency limit rather than one shared port every
     * session collided on. Without an explicit pool the same rule is applied
     * to the implicit window above, so both paths behave identically.
     *
     * This used to rotate FOG_UDPCAST_STARTINGPORT through
     * mt_rand(24576, 32766) * 2 after every allocation, which was wrong three
     * times over:
     *
     *   - it spanned the whole upper port space, so only ~0.8% of sessions
     *     landed in the range the installer firewalls. A firewalled server
     *     multicast once and then failed silently -- the task starts and no
     *     client ever receives data.
     *   - 49152-60999 overlaps Linux's default ephemeral range, so udp-sender
     *     could be handed a port the kernel had already issued to something.
     *   - FOG_UDPCAST_STARTINGPORT is an admin-editable setting. Overwriting
     *     it after every session meant a value set in the UI never survived
     *     to be used twice.
     *
     * Nothing rotates now. The setting means what its own description says:
     * the starting port. Deliberately kept as lowest-free rather than random
     * -- a bounded window plus random selection collides constantly, which is
     * the bug the rotation was papering over in the first place.
     *
     * @throws Exception
     * @return int
     */
    public static function allocatePort()
    {
        $pool = self::portPool();
        if (!count($pool)) {
            $pool = self::defaultPortPool();
        }
        $inUse = self::activePorts();
        foreach ($pool as $port) {
            if (!in_array($port, $inUse, true)) {
                return $port;
            }
        }
        throw new Exception(
            _('Every configured multicast port is already in use')
        );
    }
    /**
     * Returns the task ids associated with this session.
     *
     * @return array
     */
    public function getAssociatedTaskIDs()
    {
        Route::ids(
            'multicastsessionassociation',
            array('msID' => $this->get('id')),
            'taskID'
        );
        return (array)json_decode(Route::getData(), true);
    }
    /**
     * Can a client still join this session?
     *
     * A row in multicastSessionsAssoc is not a seat on the wire. udp-sender
     * transmits once --min-receivers have connected or --max-wait elapses,
     * whichever comes first, and anything joining after that receives a
     * partial image while still being reported as part of the session. The
     * window is therefore open only until the sender actually begins
     * sending: before it is spawned at all, or while it is still holding
     * for stragglers that have not arrived.
     *
     * @return bool
     */
    public function isJoinable()
    {
        if (!$this->isValid()) {
            return false;
        }
        // No sender yet, so nothing has been transmitted.
        if ((int)$this->get('senderpid') < 1) {
            return true;
        }
        // An unsized session was started with --min-receivers set to
        // whatever had already joined, so it can begin transmitting at any
        // moment and there is no window left to reason about.
        $expected = (int)$this->get('sessclients');
        if ($expected < 1) {
            return false;
        }
        $start = $this->get('senderstart');
        if (!$start) {
            return false;
        }
        // Everyone expected has arrived, so the hold has been satisfied and
        // transmission has begun.
        if (count($this->getAssociatedTaskIDs()) >= $expected) {
            return false;
        }
        // Unlike 1.6 there is no per-session maxwait column on this branch;
        // the hold is the global setting, in minutes, and MulticastTask
        // applies the same 10 minute fallback when it is unset or bad.
        $maxwait = (int)self::getSetting('FOG_UDPCAST_MAXWAIT');
        if ($maxwait < 1) {
            $maxwait = 10;
        }
        return self::niceDate()->getTimestamp()
            < (strtotime($start) + ($maxwait * 60));
    }
    /**
     * Cancels this particular session.
     *
     * @return void
     */
    public function cancel()
    {
        $find = ['msID' => $this->get('id')];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'taskID'
        );
        $taskIDs = json_decode(Route::getData(), true);
        self::getClass('TaskManager')->update(
            ['id' => $taskIDs],
            '',
            ['stateID' => self::getCancelledState()]
        );
        self::getClass('MulticastSessionAssociationManager')
            ->destroy(['msID' => $this->get('id')]);
        return $this
            ->set('stateID', self::getCancelledState())
            ->set('name', '')
            ->set('clients', 0)
            ->set('completetime', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
    }
    /**
     * Completes this particular session.
     *
     * @return void
     */
    public function complete()
    {
        $taskIDs = $this->getAssociatedTaskIDs();
        // An association row is not proof the host ever received anything.
        // A task that never even checked in was never on the wire, so
        // completing it reports a deployment that did not happen.
        //
        // The predicate is deliberately narrow. Multicast tasks never reach
        // the progress state -- nothing advances them past checked-in -- and
        // checked-in (2) is itself inside getQueuedStates() (0..2), so
        // testing against that whole set would cancel every task in every
        // session. Only the states below checked-in mean "never arrived".
        // Percent is avoided for the same reason: a task reporting 99 at the
        // final flush is a success and must never be turned into a failure.
        if (count($taskIDs)) {
            Route::ids(
                'task',
                array(
                    'id' => $taskIDs,
                    'stateID' => array_values(
                        array_diff(
                            self::getQueuedStates(),
                            array(self::getCheckedInState())
                        )
                    )
                )
            );
            $neverStarted = (array)json_decode(Route::getData(), true);
            $received = array_values(
                array_diff($taskIDs, $neverStarted)
            );
            if (count($received)) {
                self::getClass('TaskManager')->update(
                    ['id' => $received],
                    '',
                    ['stateID' => self::getCompleteState()]
                );
            }
            if (count($neverStarted)) {
                self::getClass('TaskManager')->update(
                    ['id' => $neverStarted],
                    '',
                    ['stateID' => self::getCancelledState()]
                );
            }
        }
        self::getClass('MulticastSessionAssociationManager')
            ->destroy(['msID' => $this->get('id')]);
        return $this
            ->set('stateID', self::getCompleteState())
            ->set('name', '')
            ->set('clients', 0)
            ->set('completetime', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
    }
}
