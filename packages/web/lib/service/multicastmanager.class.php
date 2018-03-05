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
    private function _isMCTaskNew(
        $KnownTasks,
        $id
    ) {
        foreach ((array)$KnownTasks as &$Known) {
            if ($Known->getID() == $id) {
                return false;
            }
            unset($Known);
        }
        return true;
    }
    /**
     * Gets the multicast task
     *
     * @param array $KnownTasks the known tasks
     * @param int   $id         the id to get
     *
     * @return object
     */
    private function _getMCExistingTask(
        $KnownTasks,
        $id
    ) {
        foreach ((array)$KnownTasks as &$Known) {
            if ($Known->getID() == $id) {
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
    private function _removeFromKnownList(
        $KnownTasks,
        $id
    ) {
        $new = array();
        foreach ((array)$KnownTasks as $i => $Known) {
            if ($Known->getID() != $id) {
                $new[] = $Known;
            }
        }
        unset($Known);
        return array_filter((array)$new);
    }
    /**
     * Multicast tasks are a bit more than
     * the others, this is its service loop
     *
     * @return void
     */
    private function _serviceLoop()
    {
        while (true) {
            if (!isset($nextrun)) {
                $first = true;
                $nextrun = self::niceDate()
                    ->modify(
                        sprintf(
                            '+%s second%s',
                            self::$zzz,
                            self::$zzz != 1 ? '' : 's'
                        )
                    );
            }
            if (self::niceDate() < $nextrun && $first === false) {
                usleep(100000);
                continue;
            }
            $nextrun = self::niceDate()
                ->modify(
                    sprintf(
                        '+%s second%s',
                        self::$zzz,
                        self::$zzz != 1 ? '' : 's'
                    )
                );
            $this->waitDbReady();
            $queuedStates = self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            );
            $doneStates = array(
                self::getCompleteState(),
                self::getCancelledState()
            );
            try {
                // Check if status changed.
                self::$_mcOn = self::getSetting('MULTICASTGLOBALENABLED');
                if (self::$_mcOn < 1) {
                    throw new Exception(
                        _(' * Multicast service is globally disabled')
                    );
                }
                $StorageNodes = $this->checkIfNodeMaster();
                foreach ((array)$this->checkIfNodeMaster() as &$StorageNode) {
                    $myroot = $StorageNode->get('path');
                    $RMTasks = array();
                    foreach ((array)$KnownTasks as &$mcTask) {
                        $activeCount = self::getClass('TaskManager')
                            ->count(
                                array(
                                    'id' => $mcTask->getTaskIDs(),
                                    'stateID' => $queuedStates
                                )
                            );
                        $MultiSess = $mcTask->getSess();
                        if ($activeCount < 1
                            && ($mcTask->getSessClients() == 0
                            || in_array($MultiSess->get('stateID'), $doneStates))
                        ) {
                            $RMTasks[] = $mcTask;
                        }
                        unset($mcTask);
                    }
                    $jobcancelled = false;
                    $RMCount = count($RMTasks);
                    if ($RMCount > 0) {
                        self::outall(
                            sprintf(
                                " | %s %d %s%s %s",
                                _('Cleaning'),
                                $RMCount,
                                _('task'),
                                (
                                    $RMCount != 1 ?
                                    's' :
                                    ''
                                ),
                                _('as they have been removed')
                            )
                        );
                        foreach ((array)$RMTasks as &$RMTask) {
                            $RTask = $this->_getMCExistingTask(
                                $KnownTasks,
                                $RMTask->getID()
                            );
                            self::outall(
                                sprintf(
                                    " | %s (%s) %s %s.",
                                    _('Task'),
                                    $RTask->getID(),
                                    $RTask->getName(),
                                    _('is being cleaned')
                                )
                            );
                            $taskIDs = $RMTask->getTaskIDs();
                            $inTaskIDs = self::getSubObjectIDs(
                                'Task',
                                array(
                                    'id' => $taskIDs,
                                    'stateID' => self::getCancelledState()
                                )
                            );
                            $MultiSess = $RMTask->getSess();
                            $SessCancelled = $MultiSess->get('stateID')
                                ==
                                self::getCancelledState();
                            if ($SessCancelled
                                || count($inTaskIDs) > 0
                            ) {
                                $jobcancelled = true;
                            }
                            if ($jobcancelled) {
                                self::getClass('TaskManager')
                                    ->update(
                                        array('id' => $taskIDs),
                                        '',
                                        array(
                                            'stateID' => self::getCancelledState()
                                        )
                                    );
                                $MultiSess
                                    ->set(
                                        'stateID',
                                        self::getCancelledState()
                                    )->set('name', '')
                                    ->save();
                                self::outall(
                                    sprintf(
                                        " | %s (%s) %s %s.",
                                        _('Task'),
                                        $RMTask->getID(),
                                        $RMTask->getName(),
                                        _('has been cancelled')
                                    )
                                );
                            } else {
                                self::getClass('TaskManager')
                                    ->update(
                                        array('id' => $taskIDs),
                                        '',
                                        array(
                                            'stateID' => self::getCompleteState()
                                        )
                                    );
                                $MultiSess
                                    ->set('stateID', self::getCompleteState())
                                    ->save();
                                self::outall(
                                    sprintf(
                                        " | %s (%s) %s %s.",
                                        _('Task'),
                                        $RMTask->getID(),
                                        $RMTask->getName(),
                                        _('has been completed')
                                    )
                                );
                            }
                            $RTask->killTask();
                            $KnownTasks = $this->_removeFromKnownList(
                                $KnownTasks,
                                $RTask->getID()
                            );
                            self::getClass('MulticastSessionAssociationManager')
                                ->destroy(
                                    array('msID' => $RTask->getID())
                                );
                            unset($RMTask, $RTask);
                        }
                    }
                    $allTasks = self::getClass('MulticastTask')
                        ->getAllMulticastTasks(
                            $myroot,
                            $StorageNode->get('id')
                        );
                    $taskCount = count($allTasks);
                    if ($taskCount < 1
                        || !$taskCount
                    ) {
                        self::outall(
                            sprintf(
                                ' * %s!',
                                _('No tasks found')
                            )
                        );
                    }
                    foreach ((array)$allTasks as &$curTask) {
                        $new = $this->_isMCTaskNew(
                            $KnownTasks,
                            $curTask->getID()
                        );
                        if ($new) {
                            self::outall(
                                sprintf(
                                    " | %s (%s) %s %s!",
                                    _('Task'),
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('is new')
                                )
                            );
                            if (!file_exists($curTask->getImagePath())) {
                                self::outall(
                                    sprintf(
                                        '%s (%s) %s %s, %s: %s %s!',
                                        _('Task'),
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute'),
                                        _('image file'),
                                        $curTask->getImagePath(),
                                        _('not found on this node')
                                    )
                                );
                                continue;
                            }
                            if (!$curTask->getClientCount()) {
                                self::outall(
                                    sprintf(
                                        '%s (%s) %s %s, %s!',
                                        _('Task'),
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute'),
                                        _('no clients are included')
                                    )
                                );
                                continue;
                            }
                            if (!is_numeric($curTask->getPortBase())
                                || !($curTask->getPortBase() % 2 == 0)
                            ) {
                                self::outall(
                                    sprintf(
                                        '%s (%s) %s %s, %s!',
                                        _('Task'),
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to execute'),
                                        _('port must be even and numeric')
                                    )
                                );
                                continue;
                            }
                            if (!$curTask->startTask()) {
                                self::outall(
                                    sprintf(
                                        " | %s (%s) %s %s!",
                                        _('Task'),
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('failed to start')
                                    )
                                );
                                $curTask->killTask();
                                self::outall(
                                    sprintf(
                                        " %s (%s) %s %s.",
                                        _('Task'),
                                        $curTask->getID(),
                                        $curTask->getName(),
                                        _('has been cleaned')
                                    )
                                );
                                continue;
                            }
                            $curTask
                                ->getSess()
                                ->set('stateID', self::getProgressState())
                                ->save();
                            self::outall(
                                sprintf(
                                    " | %s (%s) %s %s.",
                                    _('Task'),
                                    $curTask->getID(),
                                    $curTask->getImagePath(),
                                    _('image file found')
                                )
                            );
                            self::outall(
                                sprintf(
                                    " | %s (%s) %s %d %s%s %s.",
                                    _('Task'),
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    $curTask->getClientCount(),
                                    _('client'),
                                    (
                                        $curTask->getClientCount() != 1 ?
                                        's' :
                                        ''
                                    ),
                                    _('found')
                                )
                            );
                            self::outall(
                                sprintf(
                                    " | %s (%s) %s %s: %d.",
                                    _('Task'),
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('sending on base port'),
                                    $curTask->getPortBase()
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
                                    ' | %s (%s) %s %s!',
                                    _('Task'),
                                    $curTask->getID(),
                                    $curTask->getName(),
                                    _('has started')
                                )
                            );
                            $KnownTasks[] = $curTask;
                        } else {
                            $jobcancelled = $jobcompleted = false;
                            $runningTask = $this->_getMCExistingTask(
                                $KnownTasks,
                                $curTask->getID()
                            );
                            $taskIDs = $runningTask->getTaskIDs();
                            $inTaskCancelledIDs = self::getSubObjectIDs(
                                'Task',
                                array(
                                    'id' => $taskIDs,
                                    'stateID' => self::getCancelledState()
                                )
                            );
                            $inTaskIDs = self::getSubObjectIDs(
                                'Task',
                                array(
                                    'id' => $taskIDs,
                                    'stateID' => self::getCompleteState()
                                )
                            );
                            if (count($inTaskIDs) > 0) {
                                $jobcompleted = true;
                            }
                            $MultiSess = $runningTask->getSess();
                            $SessCancelled = $MultiSess->get('stateID')
                                ==
                                self::getCancelledState();
                            if ($SessCancelled
                                || count($inTaskCancelledIDs) > 0
                            ) {
                                $jobcancelled = true;
                            }
                            if ($runningTask->isNamedSession
                                && $runningTask->getSessClients() == 0
                            ) {
                                $jobcompleted = true;
                            }
                            if (!$jobcompleted
                                && !$jobcancelled
                                && $runningTask->isRunning($runningTask->procRef)
                            ) {
                                self::outall(
                                    sprintf(
                                        ' | %s (%s) %s %s: %s.',
                                        _('Task'),
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('is already running with pid'),
                                        $runningTask->getPID($runningTask->procRef)
                                    )
                                );
                                $runningTask->updateStats();
                            } else {
                                self::outall(
                                    sprintf(
                                        ' | %s (%s) %s %s.',
                                        _('Task'),
                                        $runningTask->getID(),
                                        $runningTask->getName(),
                                        _('is no longer running')
                                    )
                                );
                                if ($jobcancelled) {
                                    $KnownTasks = $this->_removeFromKnownList(
                                        $KnownTasks,
                                        $runningTask->getID()
                                    );
                                    if (!$runningTask->killTask()) {
                                        continue;
                                    }
                                    self::outall(
                                        sprintf(
                                            ' | %s (%s) %s %s.',
                                            _('Task'),
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('has been cancelled')
                                        )
                                    );
                                    $MultiSess->cancel();
                                } else {
                                    $MultiSess
                                        ->set('clients', 0)
                                        ->set(
                                            'completetime',
                                            self::niceDate()->format('Y-m-d H:i:s')
                                        )->set('name', '')
                                        ->set('stateID', self::getCompleteState())
                                        ->save();
                                    $KnownTasks = $this->_removeFromKnownList(
                                        $KnownTasks,
                                        $runningTask->getID()
                                    );
                                    self::outall(
                                        sprintf(
                                            " | %s (%s) %s %s.",
                                            _('Task'),
                                            $runningTask->getID(),
                                            $runningTask->getName(),
                                            _('has been completed')
                                        )
                                    );
                                }
                            }
                        }
                        unset($curTask);
                    }
                    unset($StorageNode);
                }
                unset($StorageNodes);
            } catch (Exception $e) {
                self::outall($e->getMessage());
            }
            if ($first) {
                $first = false;
            }
            $tmpTime = self::getSetting(self::$sleeptime);
            if (static::$zzz != $tmpTime) {
                static::$zzz = $tmpTime ? $tmpTime : 10;
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
            $oldCount = $taskCount;
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
