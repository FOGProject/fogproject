<?php
/**
 * Handles scheduled tasks and performs other "ondemand" related tasks.
 *
 * PHP version 5
 *
 * @category TaskSchedule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles scheduled tasks and performs other "ondemand" related tasks.
 *
 * @category TaskSchedule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskScheduler extends FOGService
{
    /**
     * Is the host lookup/ping enabled
     *
     * @var int
     */
    private static $_schedOn = 0;
    /**
     * Contains the string holding the service's sleep cycle
     *
     * @var string
     */
    public static $sleeptime = 'SCHEDULERSLEEPTIME';
    /**
     * Initializes The services environment
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
                    'SCHEDULERDEVICEOUTPUT',
                    'SCHEDULERLOGFILENAME',
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
            self::$logpath ?
            self::$logpath :
            '/opt/fog/log/',
            $log ?
            $log :
            'fogscheduler.log'
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty5'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            60
        );
    }
    /**
     * Makes the output for this service
     *
     * @return void
     */
    private function _commonOutput()
    {
        try {
            self::$_schedOn = self::getSetting('SCHEDULERGLOBALENABLED');
            if (self::$_schedOn < 1) {
                throw new Exception(_(' * Task Scheduler is globally disabled'));
            }
            $findWhere = array(
                'stateID' => self::getQueuedStates(),
                'wol' => 1
            );
            $taskHostIDs = self::getSubObjectIDs(
                'Task',
                $findWhere,
                'hostID'
            );
            $hostCount = count($taskHostIDs);
            if ($hostCount > 0) {
                $hostMACs = self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array(
                        'hostID' => $taskHostIDs,
                        'pending' => array(0, ''),
                    ),
                    'mac'
                );
                $hostMACs = self::parseMacList($hostMACs);
                $macCount = count($hostMACs);
                if ($macCount > 0) {
                    self::outall(
                        sprintf(
                            ' * Sending %d wake on lan request%s.',
                            $hostCount,
                            $hostCount === 1 ? '' : 's'
                        )
                    );
                    self::outall(
                        sprintf(
                            ' * %d total mac%s attempting to wake up.',
                            $macCount,
                            $macCount === 1 ? '' : 's'
                        )
                    );
                    self::wakeUp($hostMACs);
                }
            }
            $findWhere = array(
                'isActive' => 1
            );
            $schedCnt = $taskCount = self::getClass('ScheduledTaskManager')
                ->count($findWhere);
            $taskCount += self::getClass('PowerManagementManager')
                ->count(
                    array(
                        'action' => 'wol',
                        'onDemand' => 0
                    )
                );
            $pmCnt = $taskCount - $schedCnt;
            if ($taskCount < 1) {
                throw new Exception(' * No tasks found!');
            }
            self::outall(
                sprintf(
                    " * %s task%s found.",
                    $taskCount,
                    (
                        $taskCount === 1 ?
                        '' :
                        's'
                    )
                )
            );
            unset($taskCount);
            $ScheduledTasks = (array)self::getClass('ScheduledTaskManager')
                ->find($findWhere);
            self::outall(
                sprintf(
                    ' * %d %s.',
                    $schedCnt,
                    _('scheduled task(s) to run')
                )
            );
            self::outall(
                sprintf(
                    ' * %d %s.',
                    $pmCnt,
                    _('power management task(s) to run')
                )
            );
            foreach ($ScheduledTasks as &$Task) {
                $Task = self::getClass('ScheduledTask', $Task->get('id'));
                $Timer = $Task->getTimer();
                self::outall(
                    ' * '
                    . _('Scheduled Task run time')
                    . ': '
                    . $Timer->toString()
                );
                self::outall(
                    sprintf(
                        ' * %s',
                        $Timer->shouldRunNowCheck()
                    )
                );
                if (!$Timer->shouldRunNow()) {
                    continue;
                }
                self::outall(
                    ' * '
                    . _('Found a scheduled task that should run.')
                );
                $type = _('host');
                $getter = 'getHost';
                $gbased = 0;
                if ($Task->isGroupBased()) {
                    $type = _('group');
                    $getter = 'getGroup';
                    $gbased = 1;
                }
                self::outall(
                    "\t\t - "
                    . _('Is a')
                    . ' '
                    . $type
                    . ' '
                    . _('based task.')
                );
                self::outall(
                    sprintf(
                        "\t\t - %s %s!",
                        (
                            $Task->isMulticast() ?
                            _('Multicast') :
                            _('Unicaset')
                        ),
                        _('task found')
                    )
                );
                $Item = $Task->{$getter}();
                self::outall(
                    sprintf(
                        "\t\t - %s %s",
                        get_class($Item),
                        $Item->get('name')
                    )
                );
                $Item->createImagePackage(
                    $Task->get('taskTypeID'),
                    $Task->get('name'),
                    $Task->get('shutdown'),
                    false,
                    $Task->get('other2'),
                    $gbased,
                    $Task->get('other3'),
                    false,
                    false,
                    (bool)$Task->get('other4')
                );
                self::outall(
                    sprintf(
                        "\t\t - %s %s %s!",
                        _('Task started for'),
                        $type,
                        $Item->get('name')
                    )
                );
                if ($Timer->isSingleRun()) {
                    $Task
                        ->set('isActive', 0)
                        ->save();
                }
            }
            $PMTasks = (array)self::getClass('PowerManagementManager')
                ->find(
                    array(
                        'action' => 'wol',
                        'onDemand' => array(0, '')
                    )
                );
            foreach ($PMTasks as &$Task) {
                $Task = self::getClass('PowerManagement', $Task->get('id'));
                $Timer = $Task->getTimer();
                self::outall(
                    ' * '
                    . _('Power Management Task run time')
                    . ': '
                    . $Timer->toString()
                );
                self::outall(
                    sprintf(
                        ' * %s.',
                        $Timer->shouldRunNowCheck()
                    )
                );
                if (!$Timer->shouldRunNow()) {
                    continue;
                }
                self::outall(
                    ' * '
                    . _('Found a wake on lan task that should run.')
                );
                $Task->wakeOnLAN();
                self::outall(
                    sprintf(
                        ' | %s %s',
                        _('Task sent to'),
                        $Task->getHost()->get('name')
                    )
                );
                unset($Task);
            }
        } catch (Exception $e) {
            self::outall($e->getMessage());
        }
    }
    /**
     * Runs the service
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_commonOutput();
        parent::serviceRun();
    }
}
