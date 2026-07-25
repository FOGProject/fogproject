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
