<?php
/**
 * Handles the session in db.
 *
 * PHP version 5
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
     * @var array
     */
    protected $databaseFields = [
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
        'shutdown' => 'msShutdown',
        'maxwait' => 'msMaxwait',
        'senderpid' => 'msSenderPID',
        'sendernode' => 'msSenderNode',
        'senderstart' => 'msSenderStart',
        'anon5' => 'msAnon5'
    ];
    /**
     * Additional Fields
     *
     * @var array
     */
    protected $additionalFields = [
        'imagename',
        'state'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Image' => [
            'id',
            'image',
            'imagename'
        ],
        'TaskState' => [
            'id',
            'stateID',
            'state'
        ]
    ];
    /**
     * Get's the session's associated image object.
     *
     * @return object
     */
    public function getImage()
    {
        return new Image($this->get('image'));
    }
    /**
     * Get's the session's task state.
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
        return (int)Route::getCount(
            'multicastsession',
            ['stateID' => self::activeStates()]
        );
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
            (array)Route::getIds(
                'multicastsession',
                ['stateID' => self::activeStates()],
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
        $ports = [];
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
     * Picks a base port for a new session.
     *
     * With a pool configured, the first port no active session is holding
     * is taken -- which is what makes the pool a concurrency limit rather
     * than one shared port every session collided on. Without a pool, the
     * historical rotating start port is kept.
     *
     * @throws Exception
     * @return int
     */
    public static function allocatePort()
    {
        $pool = self::portPool();
        if (count($pool)) {
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
        $port = (int)self::getSetting('FOG_UDPCAST_STARTINGPORT');
        $next = mt_rand(24576, 32766) * 2;
        while ($next === $port) {
            $next = mt_rand(24576, 32766) * 2;
        }
        // A setting that udp-sender would reject outright is replaced now
        // rather than handed on to fail at spawn time.
        if ($port < 1024 || $port > 65534 || $port % 2 !== 0) {
            $port = $next;
        }
        self::setSetting('FOG_UDPCAST_STARTINGPORT', $next);
        return $port;
    }
    /**
     * Returns the task ids associated with this session.
     *
     * @return array
     */
    public function getAssociatedTaskIDs()
    {
        return (array)Route::getIds(
            'multicastsessionassociation',
            ['msID' => $this->get('id')],
            'taskID'
        );
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
        return self::niceDate()->getTimestamp()
            < (strtotime($start) + (int)$this->get('maxwait'));
    }
    /**
     * Cancels this particular session.
     *
     * @return void
     */
    public function cancel()
    {
        $find = ['msID' => $this->get('id')];
        $taskIDs = Route::getIds(
            'multicastsessionassociation',
            $find,
            'taskID'
        );
        self::getClass('TaskManager')->update(
            ['id' => $taskIDs],
            '',
            [
                'stateID' => self::getCancelledState(),
                'stateChangedTime' => self::niceDate()->format('Y-m-d H:i:s')
            ]
        );
        Route::deletemass(
            'multicastsessionassociation',
            ['msID' => $this->get('id')]
        );
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
        $now = self::niceDate()->format('Y-m-d H:i:s');
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
            $neverStarted = (array)Route::getIds(
                'task',
                [
                    'id' => $taskIDs,
                    'stateID' => array_values(
                        array_diff(
                            self::getQueuedStates(),
                            [self::getCheckedInState()]
                        )
                    )
                ]
            );
            $received = array_values(
                array_diff($taskIDs, $neverStarted)
            );
            if (count($received)) {
                self::getClass('TaskManager')->update(
                    ['id' => $received],
                    '',
                    [
                        'stateID' => self::getCompleteState(),
                        'stateChangedTime' => $now
                    ]
                );
            }
            if (count($neverStarted)) {
                self::getClass('TaskManager')->update(
                    ['id' => $neverStarted],
                    '',
                    [
                        'stateID' => self::getCancelledState(),
                        'stateChangedTime' => $now
                    ]
                );
            }
        }
        Route::deletemass(
            'multicastsessionassociation',
            ['msID' => $this->get('id')]
        );
        return $this
            ->set('stateID', self::getCompleteState())
            ->set('name', '')
            ->set('clients', 0)
            ->set('completetime', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
    }
}
