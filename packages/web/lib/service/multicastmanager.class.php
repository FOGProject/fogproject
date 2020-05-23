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
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'MULTICASTDEVICEOUTPUT',
                    'MULTICASTLOGFILENAME',
                    self::$sleeptime
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
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
                // If disabled, state and restart loop.
                if (self::$_mcOn < 1) {
                    throw new Exception(
                        _(' * Multicast service is globally disabled')
                    );
                }

                // Common string used for logging.
                $startStr = ' | ' . _('Task ID') . ': %s '. _('Name') . ': %s %s';

                foreach ($this->checkIfNodeMaster() as &$StorageNode) {
                    // Now that tasks are removed, lets check new/current tasks
                    $allTasks = MulticastTask::getAllMulticastTasks(
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
                                }
                                // Set msClients to zero as a marker for a completed
                                // multicast session with unregistered clients
                                if (count($taskIDs) == 0) {
                                    $Session->set('clients', 0)->save();
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
