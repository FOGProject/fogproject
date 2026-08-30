<?php
/**
 * Handles scheduled tasks and performs other "ondemand" related tasks.
 *
 * PHP version 7.4+
 *
 * @category TaskSchedule
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Service;

use FOG\Router\Route;

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
     * Fallback sleep when the globalSetting above is unset.
     *
     * @var int
     */
    public static $sleepdefault = 60;
    /**
     * Initializes The services environment
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $schedulerkeys = [
            'SCHEDULERDEVICEOUTPUT',
            'SCHEDULERLOGFILENAME',
            self::$sleeptime
        ];
        list(
            $dev,
            $log,
            $zzz
        ) = self::getSetting($schedulerkeys);
        static::$log = sprintf(
            '%s%s',
            self::$logpath ?
            self::$logpath :
            FOG_LOG_DIR . DS,
            $log ?
            $log :
            'fogscheduler.log'
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
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
                throw new \Exception(_(' * Task Scheduler is globally disabled'));
            }
            $findWhere = [
                'stateID' => self::getQueuedStates(),
                'wol' => 1
            ];
            $taskHostIDs = Route::getIds(
                'task',
                $findWhere,
                'hostID'
            );
            $hostCount = count($taskHostIDs);
            if ($hostCount > 0) {
                $find = [
                    'hostID' => $taskHostIDs,
                    'pending' => [0, '']
                ];
                $hostMACs = Route::getIds(
                    'macaddressassociation',
                    $find,
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
            /*
             * The two housekeeping sweeps run BEFORE the "no tasks found"
             * exit below, and that ordering is the point of moving them.
             *
             * That exit counts SCHEDULED and power-management tasks -- the
             * two things the rest of this method acts on -- and throws when
             * there are none, which ends the pass. The expired-check-in sweep
             * sat after it, so on any install with no scheduled task and no
             * power-management task it never ran at all. That is most small
             * installs, and it is exactly the population reporting tasks
             * stuck active forever. Neither sweep has anything to do with
             * whether a schedule exists.
             */
            self::outall(
                ' * '
                . _('Checking for tasks that can never run...')
            );
            $reaped = self::getClass('TaskManager')->reapUnrunnable();
            foreach ($reaped as $taskID => $why) {
                self::outall(
                    sprintf(
                        ' * %s %d: %s',
                        _('Marked failed, task'),
                        $taskID,
                        $why
                    )
                );
            }
            if (count($reaped ?: []) < 1) {
                self::outall(' * ' . _('No unrunnable tasks found.'));
            }
            //check for expired check-ins on active Tasks
            self::outall(
                ' * '
                . _('Checking for expired checked-in tasks...')
            );
            $used = explode(',', (string)self::getSetting('FOG_USED_TASKS'));
            $find = [
                'stateID' => self::getCheckedInState(),
                'typeID' => $used
            ];
            $Tasks = Route::getList('task', $find);
            foreach ($Tasks as $Task) {
                if(self::getClass('Task', $Task->id)->expireTaskCheckin()) {
                    self::outall(
                        ' * '
                        . _('Found an expired task, resetting to queued for task of id')
                        . ': '
                        . $Task->id
                    );
                }
            }
            // asValue(), not getList(): both counts below come off the
            // envelope, so the envelope has to survive. What this buys is the
            // other half -- a failure raises instead of ending the daemon.
            // Scheduled Task Information
            $ScheduledTasks = Route::asValue(
                function () {
                    Route::active('scheduledtask');
                }
            );
            $staskcount = $ScheduledTasks->recordsFiltered;

            // Powermanagement Task Information
            $PMTasks = Route::asValue(
                function () {
                    Route::active('powermanagement');
                }
            );
            $ptaskcount = $PMTasks->recordsFiltered;
            $taskCount = $staskcount + $ptaskcount;
            if ($taskCount <= 0) {
                throw new \Exception(' * No tasks found!');
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
            // Scheduled Tasks
            foreach ($ScheduledTasks->data as $Task) {
                $Task = self::getClass('ScheduledTask', $Task->id);
                $Timer = $Task->getTimer();
                self::outall(
                    ' * '
                    . _('Scheduled Task run time')
                    . ': '
                    . $Timer->toString()
                );
                self::outall(
                    sprintf(
                        ' * %s ',
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
                            _('Unicast')
                        ),
                        _('task found')
                    )
                );
                $Item = $Task->{$getter}();
                self::outall(
                    sprintf(
                        "\t\t - %s %s",
                        self::shortName($Item),
                        $Item->get('name')
                    )
                );
                // getItem(), not indiv(): a scheduled task pointing at a task
                // type that has since been deleted used to exit the daemon
                // here rather than skip the one task. Refs #907.
                $tasktype = Route::getItem('tasktype', $Task->get('taskTypeID'));
                if (!$tasktype) {
                    self::outall(
                        sprintf(
                            "\t\t - %s: %s",
                            _('Skipping, no such task type'),
                            $Task->get('taskTypeID')
                        )
                    );
                    continue;
                }
                $Item->createImagePackage(
                    $tasktype,
                    $Task->get('name'),
                    $Task->get('shutdown'),
                    false,
                    $Task->get('other2'),
                    $gbased,
                    $Task->get('other3'),
                    false,
                    false,
                    (bool)$Task->get('other4'),
                    (bool)$Task->get('other5'),
                    (bool)$Task->get('other1')
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
                    $Task->set('isActive', 0)->save();
                }
            }
            // Power Management Tasks.
            foreach ($PMTasks->data as $Task) {
                $Task = self::getClass('PowerManagement', $Task->id);
                $Timer = $Task->getTimer();
                self::outall(
                    ' * '
                    . _('Power Management Task run time')
                    . ': '
                    . $Timer->toString()
                );
                self::outall(
                    sprintf(
                        ' * %s ',
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
            }
        } catch (\Exception $e) {
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
