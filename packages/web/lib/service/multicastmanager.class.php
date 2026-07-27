<?php
/**
 * The multicast manager service
 *
 * PHP version 5
 *
 * @category MulticastManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The multicast manager service
 *
 * @category MulticastManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastManager extends FOGService
{
    /**
     * Is the host lookup/ping enabled
     *
     * @var int
     */
    private static $_mcOn = 0;
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'MULTICASTSLEEPTIME';
    /**
     * Alternate log -- the multicast running udpcast
     *
     * @var string
     */
    protected $altLog;
    /**
     * Initializes the MulticastManager class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        /*
         * Read each setting by name rather than by position.
         *
         * This was a list() over getSubObjectIDs(), which returns only the
         * rows that actually exist, ordered by name. One missing Service row
         * therefore shifted every later value a place to the left and left
         * the last variable undefined -- the "Undefined array key 0/1" in
         * issue #728. The daemon then took its log filename from the device
         * setting and its console device from the log filename, so multicast
         * appeared to do nothing at all.
         *
         * getSetting() returns '' for a key with no row, which the defaults
         * below already handle, and it cannot mix values up between keys.
         */
        $dev = self::getSetting('MULTICASTDEVICEOUTPUT');
        $log = self::getSetting('MULTICASTLOGFILENAME');
        $zzz = self::getSetting(self::$sleeptime);
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                '/opt/fog/log/'
            ),
            (
                $log ?
                $log :
                'multicast.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty2'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            10
        );
    }
    /**
     * Tests if the multicast task is new
     *
     * @param array $KnownTasks the known tasks
     * @param int   $id         test if the id is new
     *
     * @return bool
     */
    private static function _isMCTaskInList(
        $Tasks,
        $id
    ) {
        if (count($Tasks) < 1) {
            return false;
        }
        foreach ((array)$Tasks as &$Task) {
            if ($Task->getID() == $id) {
                return true;
            }
            unset($Task);
        }
        return false;
    }
    /**
     * Gets the multicast task
     *
     * @param array $KnownTasks the known tasks
     * @param int   $id         the id to get
     *
     * @return object
     */
    private static function _getMCExistingTask(
        $KnownTasks,
        $curTask
    ) {
        foreach ((array)$KnownTasks as &$Known) {
            if ($Known->getID() == $curTask->getID()) {
                // This is very important for MC session joins via PXE menu
                $curTaskTaskIDs = $curTask->getTaskIDs();
                if (count($curTaskTaskIDs) > count($Known->getTaskIDs())) {
                    $Known->setTaskIDs($curTaskTaskIDs);
                }
                return $Known;
            }
            unset($Known);
        }
        return false;
    }
    /**
     * Removes task from the known list
     *
     * @param array $KnownTasks the known tasks
     * @param int   $id         the id to removes
     *
     * @return array
     */
    private static function _removeFromKnownList(
        $KnownTasks,
        $id
    ) {
        $new = array();
        foreach ((array)$KnownTasks as &$Known) {
            if ($Known->getID() != $id) {
                $new[] = $Known;
            }
            unset($Known);
        }
        unset($Known);
        return array_filter($new);
    }
    /**
     * Is the given pid still a live udp-sender?
     *
     * Checks the command line as well as existence so a recycled pid
     * belonging to an unrelated process is never mistaken for our sender.
     *
     * @param int $pid the pid to check
     *
     * @return bool
     */
    private function _isSenderAlive($pid)
    {
        $pid = (int)$pid;
        if ($pid < 1) {
            return false;
        }
        $cmdline = @file_get_contents(
            sprintf('/proc/%d/cmdline', $pid)
        );
        if (!$cmdline) {
            return false;
        }
        return strpos(
            str_replace("\0", ' ', $cmdline),
            basename(UDPSENDERPATH)
        ) !== false;
    }
    /**
     * Reconciles udp-senders this node owns but no longer tracks.
     *
     * procRef only ever lived in process memory, so a daemon restart lost
     * every handle to a running sender. The re-forked daemon then saw an
     * empty known-task list and spawned a second sender on the same
     * portbase. There is no way to re-adopt a proc_open resource across a
     * restart, so an orphan that is still alive is terminated: the session
     * is still active and will be picked up and started cleanly, under a
     * handle this daemon can actually monitor and kill later.
     *
     * @return void
     */
    private function _reconcileOrphanedSenders()
    {
        foreach ($this->checkIfNodeMaster() as &$StorageNode) {
            Route::listem(
                'multicastsession',
                'name',
                false,
                array('sendernode' => $StorageNode->get('id'))
            );
            $Sessions = json_decode(
                Route::getData()
            );
            foreach ((array)$Sessions->multicastsessions as &$Session) {
                $pid = (int)$Session->senderpid;
                if ($pid < 1) {
                    unset($Session);
                    continue;
                }
                if ($this->_isSenderAlive($pid)) {
                    self::outall(
                        sprintf(
                            ' | ' . _('Session ID') . ': %s '
                            . _('orphaned udp-sender pid') . ': %d '
                            . _('terminating so it can be restarted'),
                            $Session->id,
                            $pid
                        )
                    );
                    $this->killAll($pid, SIGKILL);
                } else {
                    self::outall(
                        sprintf(
                            ' | ' . _('Session ID') . ': %s '
                            . _('stale sender reference cleared'),
                            $Session->id
                        )
                    );
                }
                self::getClass('MulticastSessionManager')->update(
                    array('id' => $Session->id),
                    '',
                    array(
                        'senderpid' => 0,
                        'sendernode' => 0
                    )
                );
                unset($Session);
            }
            unset($StorageNode);
        }
    }
    /**
     * Multicast tasks are a bit more than
     * the others, this is its service loop
     *
     * @return void
     */
    private function _serviceLoop()
    {
        $KnownTasks = [];
        $queueTasks = [];
        while (true) {
            // Ensure we have a fresh complete and cancel variable.
            $completeTasks = $cancelTasks = [];

            // Handles the sleep timer for us.
            $date = self::niceDate();
            if (!isset($nextrun)) {
                $first = true;
                $nextrun = clone $date;
            }
            // Actually holds and loops until the proper sleep time is met.
            if ($date < $nextrun && $first === false) {
                usleep(100000);
                continue;
            }
            // Check db connection and wait until db is ready.
            $this->waitDbReady();

            // Reset the next run time.
            $nextrun = self::niceDate();
            $nextrun->modify('+'.self::$zzz.' seconds');

            // Sets the queued States each iteration incase there is a change.
            $queuedStates = self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            );
            // Sets the Done states each iteration incase there is a change.
            $doneStates = [
                self::getCompleteState(),
                self::getCancelledState()
            ];

            // Check if status changed.
            self::$_mcOn = self::getSetting('MULTICASTGLOBALENABLED');

            try {
                // Any sender still recorded against a node we master
                // predates this fork, so reconcile once before the first
                // pass.
                //
                // Inside the try, because its first act is
                // checkIfNodeMaster(), which throws when this server masters
                // no node -- an ordinary configuration for a server running
                // this daemon, and one that must not kill it.
                //
                // Above the disabled check, because an orphaned sender left
                // by a previous run must still be cleaned up after multicast
                // is switched off -- that is precisely when nothing else
                // will ever come along to kill it.
                if ($first) {
                    $this->_reconcileOrphanedSenders();
                }

                // If disabled, state and restart loop.
                if (self::$_mcOn < 1) {
                    throw new Exception(
                        _(' * Multicast service is globally disabled')
                    );
                }

                // Common string used for logging.
                $startStr = ' | ' . _('Task ID') . ': %s '. _('Name') . ': %s %s';

                // A session that leaves the active set -- cancelled from the
                // UI, completed elsewhere, or deleted outright -- vanishes
                // from the per-node task list before it can ever be matched
                // below, because getAllMulticastTasks() sources that list
                // from Route::active(), which filters to the queued and
                // progress states. The sender it owns was therefore never
                // killed and ran on holding its portbase until the daemon
                // restarted. Sweep the known list against the sessions still
                // active in the database instead.
                //
                // This runs once per tick rather than inside the node loop
                // on purpose: $KnownTasks spans every node this server
                // masters, so differencing it against one node's task list
                // would flag another node's tasks as gone and kill them.
                Route::ids(
                    'multicastsession',
                    ['stateID' => $queuedStates]
                );
                $activeIDs = (array)json_decode(Route::getData(), true);
                foreach ($KnownTasks as $Known) {
                    if (in_array($Known->getID(), $activeIDs)) {
                        continue;
                    }
                    self::outall(
                        sprintf(
                            $startStr,
                            $Known->getID(),
                            $Known->getName(),
                            _('is no longer active, stopping its sender')
                        )
                    );
                    $Known->killTask();
                    $KnownTasks = self::_removeFromKnownList(
                        $KnownTasks,
                        $Known->getID()
                    );
                }

                foreach ($this->checkIfNodeMaster() as &$StorageNode) {
                    // Now that tasks are removed, lets check new/current tasks
                    $MulticastTask = new MulticastTask();
                    $allTasks = $MulticastTask->getAllMulticastTasks(
                        $StorageNode->get('path'),
                        $StorageNode->get('id'),
                        $queuedStates
                    );
                    $taskCount = count($allTasks ?: []);
                    if ($taskCount < 1) {
                        self::outall(
                            ' * ' . _('No new tasks found')
                        );
                        continue;
                    }

                    foreach ($allTasks as &$curTask) {
                        $totalSlots = $StorageNode->get('maxClients');
                        $usedSlots = $StorageNode->getUsedSlotCount();
                        $queuedSlots = $StorageNode->getQueuedSlotCount();
                        $groupOpenSlots = $totalSlots - $usedSlots;

                        $existing = self::_isMCTaskInList(
                            $KnownTasks,
                            $curTask->getID()
                        );
                        $queued = self::_isMCTaskInList(
                            $queueTasks,
                            $curTask->getID()
                        );

                        if (!$existing) {
                            if ($groupOpenSlots < 1) {
                                if ($queued) {
                                    continue;
                                }
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _(' No open slots ')
                                    )
                                );
                                $curTask->getSess()->set('stateID', 1);
                                if (!$curTask->getSess()->save()) {
                                    throw new Exception(_('Failed to update Task'));
                                } else {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $curTask->getID(),
                                            $curTask->getName(),
                                            _(' Task state has been updated, now the task is queued!')
                                        )
                                    );
                                }
                                $queueTasks[] = $curTask;
                                continue;
                            }
                            if (!file_exists($curTask->getImagePath())) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute, image file: ')
                                        . $curTask->getImagePath()
                                        . _('not found on this node')
                                    )
                                );
                                continue;
                            }
                            if (!$curTask->getClientCount()) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute, there are no clients included')
                                    )
                                );
                                continue;
                            }
                            if (!is_numeric($curTask->getPortBase())
                                || !($curTask->getPortBase() % 2 == 0)
                            ) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute, port must be even and numeric')
                                    )
                                );
                                continue;
                            }

                            if (!$curTask->startTask()) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to start')
                                    )
                                );
                                if (!$curTask->killTask()) {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $curTask->getID(),
                                            $curTask->getName(),
                                            _('could not be killed')
                                        )
                                    );
                                } else {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $curTask->getID(),
                                            $curTask->getName(),
                                            _('has been killed')
                                        )
                                    );
                                }
                                continue;
                            }
                            if ($queued) {
                                $queueTasks = self::_removeFromKnownList(
                                    $queueTasks,
                                    $curTask->getID()
                                );
                            }
                            $KnownTasks[] = $curTask;
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('is new')
                                )
                            );
                            $Session = $curTask->getSess();
                            $Session->set('stateID', self::getProgressState());
                            if (!$Session->save()) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('unable to be updated')
                                    )
                                );
                                continue;
                            }
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('image file found, file: ')
                                    . $curTask->getImagePath()
                                )
                            );
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    $curTask->getClientCount()
                                    . ' '
                                    . (
                                        $curTask->getClientCount() == 1 ?
                                        _('client') :
                                        _('clients')
                                    )
                                    . ' '
                                    . _('found')
                                )
                            );
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('sending on base port ')
                                    . $curTask->getPortBase()
                                )
                            );
                            self::outall(
                                sprintf(
                                    " | %s: %s",
                                    _('Command'),
                                    $curTask->getCMD()
                                )
                            );
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('has started')
                                )
                            );
                            continue;
                        }
                        $jobcancelled = $jobcompleted = false;
                        $runningTask = self::_getMCExistingTask(
                            $KnownTasks,
                            $curTask
                        );

                        $taskIDs = $runningTask->getTaskIDs();
                        $find = [];
                        $find['id'] = $taskIDs;
                        $find['stateID'] = self::getCancelledState();
                        Route::ids(
                            'task',
                            $find
                        );
                        $inTaskCancelledIDs = json_decode(Route::getData(), true);
                        $find['stateID'] = self::getCompleteState();
                        Route::ids(
                            'task',
                            $find
                        );
                        $inTaskCompletedIDs = json_decode(Route::getData(), true);
                        $Session = $runningTask->getSess();

                        if ($Session->get('stateID') != $curTask->getSess()->get('stateID')) {
                            $Session->set('stateID', $curTask->getSess()->get('stateID'));
                            if (!$Session->save()) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('unable to be updated')
                                    )
                                );
                            }
                        }

                        $SessCancelled = $Session->get('stateID')
                            == self::getCancelledState();
                        $SessCompleted = $Session->get('stateID')
                            == self::getCompleteState();
                        if ($SessCancelled
                            || count($inTaskCancelledIDs) > 0
                        ) {
                            $jobcancelled = true;
                        }
                        if ($SessCompleted
                            || (count($inTaskCompletedIDs) > 0 && count($inTaskCompletedIDs) >= count($taskIDs))
                            || ($runningTask->isNamedSessionFinished())
                        ) {
                            $jobcompleted = true;
                        }

                        if (!$jobcancelled && !$jobcompleted) {
                            if ($runningTask->isRunning($runningTask->procRef)) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('is already running with pid: ')
                                        . $runningTask->getPID($runningTask->procRef)
                                    )
                                );

                                $runningTask->updateStats();
                            } else {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('is no longer running')
                                    )
                                );
                                if (!$runningTask->killTask()) {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('could not be killed')
                                        )
                                    );
                                } else {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('has been killed')
                                        )
                                    );
                                    $KnownTasks = self::_removeFromKnownList(
                                        $KnownTasks,
                                        $runningTask->getID()
                                    );
                                }
                                // Set msClients to zero as a marker for a completed
                                // multicast session with unregistered clients
                                if (count($taskIDs) == 0) {
                                    $Session->set('clients', 0)->save();
                                }
                                // The udp-sender process exited on its own, so the
                                // session is finished even if the per-host tasks were
                                // never marked complete (e.g. hosts rebooted/shut down
                                // after imaging). Queue it for completion so its state
                                // is cleared and it stops blocking new group sessions.
                                $completeTasks[] = $runningTask;
                            }
                        } else {
                            if ($jobcompleted) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('has been completed')
                                    )
                                );
                                $completeTasks[] = $runningTask;
                            }
                            if ($jobcancelled) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('has been cancelled')
                                    )
                                );
                                $cancelTasks[] = $runningTask;
                            } else {
                                if (!$runningTask->killTask()) {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('could not be killed')
                                        )
                                    );
                                } else {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('has been killed')
                                        )
                                    );
                                    $KnownTasks = self::_removeFromKnownList(
                                        $KnownTasks,
                                        $runningTask->getID()
                                    );
                                }
                            }
                        }
                        unset($curTask);
                        unset($runningTask);
                    }
                    unset($StorageNode);
                }
                // We need to iterate the complete and cancelTasks
                //
                // Both loops re-clear the sender reference after the session
                // is closed out. Every task reaching them has already been
                // through killTask(), so clearSenderRef() has zeroed
                // senderpid and sendernode -- but cancel() and complete()
                // finish with save() on a session object loaded back when the
                // task was constructed, which still holds the pre-kill pid,
                // and FOGController::save() writes every field it holds. The
                // row therefore ended a completed session naming a sender
                // that is already dead.
                //
                // That matters because _reconcileOrphanedSenders() trusts the
                // column on the next start, and _isSenderAlive() only asks
                // whether the pid is *a* udp-sender, not whether it is this
                // session's. A recycled pid belonging to another session's
                // sender would be killed -- taking down an unrelated
                // deployment. Clearing after, rather than inside cancel() or
                // complete(), keeps the reference alive whenever the sender
                // has NOT been killed, which is exactly what lets the
                // reconciler find a real orphan.
                foreach ($cancelTasks as &$Task) {
                    $Session = $Task->getSess();
                    self::outall(
                        sprintf(
                            $startStr,
                            $Task->getID(),
                            $Task->getName(),
                            (
                                $Session->cancel() ?
                                _('is now cancelled') :
                                _('could not be cancelled')
                            )
                        )
                    );
                    $Task->clearSenderRef();
                    unset($Task);
                }
                foreach ($completeTasks as &$Task) {
                    $Session = $Task->getSess();
                    self::outall(
                        sprintf(
                            $startStr,
                            $Task->getID(),
                            $Task->getName(),
                            (
                                $Session->complete() ?
                                _('is now completed') :
                                _('could not be completed')
                            )
                        )
                    );
                    $Task->clearSenderRef();
                    unset($Task);
                }
            } catch (Exception $e) {
                self::outall($e->getMessage());
            }
            if ($first) {
                $first = false;
            }
            $tmpTime = self::getSetting(self::$sleeptime);
            if ($tmpTime > 0 && static::$zzz != $tmpTime) {
                static::$zzz = $tmpTime ?: 10;
                self::outall(
                    sprintf(
                        ' | %s %s %s.',
                        _('Wait time has changed to'),
                        static::$zzz,
                        (
                            static::$zzz != 1 ?
                            _('seconds') :
                            _('second')
                        )
                    )
                );
            }
        }
    }
    /**
     * This is what essentially "runs" the service
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_serviceLoop();
    }
}
