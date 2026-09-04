<?php
/**
 * The multicast manager service
 *
 * PHP version 7.4+
 *
 * @category MulticastManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Items\MulticastSession;
use FOG\Router\Route;

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
     * One pass, not the scheduled loop the other nine daemons run.
     *
     * serviceRun() here does not return between passes -- it holds the
     * running udpcast sessions and polls them itself -- so FOGService::run()'s
     * nextrun scheduling has nothing to schedule. The preflight is the other
     * difference: without udp-sender there is no point starting at all, and
     * exiting non-zero is what makes systemd report the unit failed rather
     * than let it sit "active" doing nothing.
     *
     * @return void
     */
    public function run()
    {
        if (!file_exists(UDPSENDERPATH)) {
            self::outall(' * Unable to locate udp-sender!.');
            exit(1);
        }
        $this->getBanner();
        $this->waitInterfaceReady();
        $this->waitDbReady();
        $this->serviceStart();
        $this->serviceRun();
        self::outall(' * Service has ended.');
    }
    /**
     * Initializes the MulticastManager class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $multicastkeys = [
            'MULTICASTDEVICEOUTPUT',
            'MULTICASTLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($multicastkeys);
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            (
                $log ?
                $log :
                'multicast.log'
            )
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
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
    private static function _isMCTaskNew(
        $KnownTasks,
        $id
    ) {
        foreach ($KnownTasks as $Known) {
            if ($Known->getID() == $id) {
                return false;
            }
        }
        return true;
    }
    /**
     * Gets the multicast task
     *
     * @param array $KnownTasks the known tasks
     * @param int   $curTask    the current task
     *
     * @return object
     */
    private static function _getMCExistingTask(
        $KnownTasks,
        $curTask
    ) {
        foreach ($KnownTasks as $Known) {
            if ($Known->getID() == $curTask->getID()) {
                // This is very important for MC session joins via PXE menu
                $curTaskTaskIDs = $curTask->getTaskIDs();
                if (count($curTaskTaskIDs) > count($Known->getTaskIDs())) {
                    $Known->setTaskIDs($curTaskTaskIDs);
                }
                return $Known;
            }
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
        $new = [];
        foreach ($KnownTasks as $Known) {
            if ($Known->getID() != $id) {
                $new[] = $Known;
            }
        }
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
        return $this->isPidAlive($pid, basename(UDPSENDERPATH));
    }
    /**
     * Session IDs already reported as owned by another node.
     *
     * @var array
     */
    private $_claimNoted = [];
    /**
     * Whether no other node already owns this session's sender.
     *
     * @param MulticastTask $curTask The task about to be started.
     *
     * @return bool
     */
    private function _senderClaimIsFree($curTask)
    {
        $owner = (int)(new MulticastSession($curTask->getID()))->get('sendernode');

        return $owner < 1
            || $owner === $curTask->getNodeID();
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
        foreach ($this->checkIfNodeMaster() as $StorageNode) {
            $Sessions = Route::getList(
                'multicastsession',
                ['sendernode' => $StorageNode->id]
            );
            foreach ($Sessions as $Session) {
                $pid = (int)$Session->senderpid;
                if ($pid < 1) {
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
                (new MulticastSession($Session->id))
                    ->set('senderpid', 0)
                    // senderpid is a process id and 0 is a real "no process".
                    // sendernode is a reference, so its "none" is NULL --
                    // see schema step 386.
                    ->set('sendernode', null)
                    ->save();
            }
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
        while (true) {
            // Wait until db is ready.
            // This is in the loop just in case the db goes down in between sessions.
            $this->waitDbReady();

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
            // Reset the next run time.
            $nextrun->modify('+'.self::$zzz.' seconds');

            // Sets the Queued States each iteration incase there is a change.
            $queuedStates = self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            );
            // Sets the Done States each iteration incase there is a change.
            $doneStates = [
                self::getCompleteState(),
                self::getCancelledState()
            ];

            // Check if status changed.
            self::$_mcOn = (int) self::getSetting('MULTICASTGLOBALENABLED');

            try {
                // Any sender still recorded against a node we master
                // predates this fork, so reconcile once before the first
                // pass.
                //
                // Inside the try, because its first act is
                // checkIfNodeMaster(), which throws when this server masters
                // no node -- an ordinary configuration for a server running
                // this daemon. Ahead of the loop that throw was uncaught and
                // killed the child, which Service_persist() then re-forked
                // into the same throw, so a non-master crash-looped instead
                // of idling. In here the existing catch logs it per tick,
                // which is what it did before reconciliation was added.
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
                    throw new \Exception(
                        _(' * Multicast service is globally disabled')
                    );
                }

                // Common string used for logging.
                $startStr = ' | ' . _('Task ID') . ': %s '. _('Name') . ': %s %s';

                // A session that leaves the active set -- canceled from the
                // UI, completed elsewhere, or deleted outright -- vanishes
                // from the per-node task list before it can ever be matched
                // below, so the sender it owns was never killed and ran on
                // holding its portbase until the daemon restarted. Sweep the
                // known list against the sessions still active in the
                // database instead.
                //
                // This runs once per tick rather than inside the node loop
                // on purpose: $KnownTasks spans every node this server
                // masters, so differencing it against one node's task list
                // would flag another node's tasks as gone and kill them.
                $activeIDs = (array)Route::getIds(
                    'multicastsession',
                    ['stateID' => $queuedStates]
                );
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

                foreach ($this->checkIfNodeMaster() as $StorageNode) {
                    // Now that tasks are removed, lets check new/current tasks
                    $allTasks = MulticastTask::getAllMulticastTasks(
                        $StorageNode->path,
                        $StorageNode->id,
                        $queuedStates
                    );
                    $taskCount = count($allTasks ?: []);
                    if ($taskCount < 1) {
                        self::outall(
                            ' * ' . _('No new tasks found')
                        );
                        continue;
                    }
                    foreach ($allTasks as $curTask) {
                        $new = self::_isMCTaskNew(
                            $KnownTasks,
                            $curTask->getID()
                        );
                        // One session, one sender. That was only ever
                        // implicit -- a session is served by its group's
                        // master and there is one of those -- but the
                        // Location plugin promotes further masters, so two
                        // nodes can now reach the same session. The second
                        // to start would overwrite senderpid/sendernode and
                        // leave the first sender running with nothing
                        // pointing at it: _reconcileOrphanedSenders() finds a
                        // node's orphans by looking itself up in sendernode,
                        // so the record it needs would name the other node.
                        //
                        // Read the claim back from the database rather than
                        // from the session object built with the task list,
                        // which may be a tick stale. Deliberately not added
                        // to $KnownTasks: if the owning node's sender goes
                        // away the session stays active, and this node
                        // should be free to pick it up on a later tick.
                        if ($new) {
                            if (!$this->_senderClaimIsFree($curTask)) {
                                // Said once, not every tick. The session is
                                // re-checked on each pass by design, so
                                // logging unconditionally would write a line
                                // per foreign session every sleep interval
                                // for as long as the other node held it.
                                if (!in_array(
                                    $curTask->getID(),
                                    $this->_claimNoted
                                )) {
                                    $this->_claimNoted[] = $curTask->getID();
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $curTask->getID(),
                                            $curTask->getName(),
                                            _(
                                                'is already being sent '
                                                . 'by another node'
                                            )
                                        )
                                    );
                                }
                                continue;
                            }
                            // Free to take, so forget any earlier note and
                            // report again if it is lost to another node
                            // later.
                            $this->_claimNoted = array_values(
                                array_diff(
                                    $this->_claimNoted,
                                    [$curTask->getID()]
                                )
                            );
                            $KnownTasks[] = $curTask;
                            self::outall(
                                sprintf(
                                    $startStr,
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('is new')
                                )
                            );
                            if (!file_exists($curTask->getImagePath())) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute, image file')
                                        . ': '
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
                                        _(
                                            'failed to execute, '
                                            . 'there are no clients included'
                                        )
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
                                        _(
                                            'failed to execute, '
                                            . 'port must be even and numeric'
                                        )
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
                                    _('image file found, file')
                                    . ': '
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
                                    _('sending on base port')
                                    . ' '
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
                        $jobcanceled = $jobcompleted = false;
                        $runningTask = self::_getMCExistingTask(
                            $KnownTasks,
                            $curTask
                        );
                        $taskIDs = $runningTask->getTaskIDs();
                        $find = [];
                        $find['id'] = $taskIDs;
                        $find['stateID'] = self::getCancelledState();
                        $inTaskCanceledIDs = Route::getIds(
                            'task',
                            $find
                        );
                        $find['stateID'] = self::getCompleteState();
                        $inTaskCompletedIDs = Route::getIds(
                            'task',
                            $find
                        );
                        $Session = $runningTask->getSess();
                        $SessCanceled = $Session->get('stateID')
                            == self::getCancelledState();
                        $SessCompleted = $Session->get('stateID')
                            == self::getCompleteState();
                        if ($SessCanceled
                            || count($inTaskCanceledIDs) > 0
                        ) {
                            $jobcanceled = true;
                        }
                        if ($SessCompleted
                            || (count($inTaskCompletedIDs) > 0
                            && count($inTaskCompletedIDs) >= count($taskIDs))
                            || $runningTask->isNamedSessionFinished()
                        ) {
                            $jobcompleted = true;
                        }
                        if (!$jobcanceled && !$jobcompleted) {
                            // Capture status (running flag AND exit code) in
                            // ONE call -- proc_get_status only reports the real
                            // exit code on the first call after the process
                            // exits, so we must not call isRunning() first.
                            $procStatus = $runningTask->getProcStatus(
                                $runningTask->procRef
                            );
                            if ($procStatus !== false && $procStatus['running']) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('is already running with pid')
                                        . ': '
                                        . $runningTask->getPID($runningTask->procRef)
                                    )
                                );
                                $runningTask->updateStats();
                            } else {
                                // -1 == could not be determined (e.g. already
                                // reaped). Only a DEFINITE positive exit code
                                // marks a real udp-sender failure; 0 and -1
                                // keep the historical "treat as complete"
                                // behavior to avoid false failures.
                                $exitcode = ($procStatus !== false)
                                    ? (int)$procStatus['exitcode']
                                    : -1;
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
                                if ($exitcode > 0) {
                                    self::outall(
                                        sprintf(
                                            $startStr,
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            sprintf(
                                                _('exited abnormally with code %d; canceling task'),
                                                $exitcode
                                            )
                                        )
                                    );
                                    $cancelTasks[] = $runningTask;
                                } else {
                                    $completeTasks[] = $runningTask;
                                }
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
                            if ($jobcanceled) {
                                self::outall(
                                    sprintf(
                                        $startStr,
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('has been canceled')
                                    )
                                );
                                $cancelTasks[] = $runningTask;
                            }
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
                }
                // We need to iterate the completeTasks and cancelTasks
                //
                // Both loops re-clear the sender reference after the session
                // is closed out. Every task reaching them has already been
                // through killTask(), so clearSenderRef() has zeroed
                // senderpid and sendernode -- unless the sender survived the
                // kill, in which case it refuses and the reference stays put
                // for the reconciler. On the normal path cancel() and
                // complete()
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
                foreach ($cancelTasks as $Task) {
                    $Session = $Task->getSess();
                    self::outall(
                        sprintf(
                            $startStr,
                            $Task->getID(),
                            $Task->getName(),
                            (
                                $Session->cancel() ?
                                _('is now canceled') :
                                _('could not be canceled')
                            )
                        )
                    );
                    $Task->clearSenderRef();
                }
                foreach ($completeTasks as $Task) {
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
                }
            } catch (\Exception $e) {
                self::outall($e->getMessage());
            }
            if ($first) {
                $first = false;
            }
            $tmpTime = self::getSetting(self::$sleeptime);
            if (static::$zzz != $tmpTime) {
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
