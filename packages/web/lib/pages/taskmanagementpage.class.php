<?php
/**
 * Displays tasks to the user.
 *
 * PHP version 5
 *
 * @category TaskManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays tasks to the user.
 *
 * @category TaskManagementPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskManagementPage extends FOGPage
{
    /**
     * The buttons elements are more or less common
     * to all of the pages.
     *
     * @var string
     */
    private $_buttons = '';
    /**
     * The node this page works with.
     *
     * @var string
     */
    public $node = 'task';
    /**
     * Initializes the task page items.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Task Management';
        parent::__construct($this->name);
        $this->headerData = [
            _('Host Name'),
            _('Image Name'),
            _('Started By'),
            _('Status'),
            _('Progress')
        ];
        $this->templates = [
            '',
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];
        $props = ' method="post" action="'
            . $this->formAction
            . '" ';

        $this->_buttons = self::makeButton('resume-refresh', _('Resume Reload'), 'btn btn-success', null);
        $this->_buttons .= self::makeButton('pause-refresh', _('Pause Reload'), 'btn btn-warning', null);
        $this->_buttons .= self::makeButton('cancel-selected', _('Cancel Selected'), 'btn btn-danger', $props);
    }
    /**
     * Get the active tasks
     *
     * @return void
     */
    public function getActiveTasks()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $activestates = [
            'queued',
            'checked in',
            'in-progress'
        ];

        $where = "`taskStates`.`tsName` IN ('"
            . implode("','", $activestates)
            . "')";

        $tasksSqlStr = "SELECT `%s`
            FROM `%s`
            LEFT OUTER JOIN `taskTypes`
            ON `tasks`.`taskTypeID` = `taskTypes`.`ttID`
            LEFT OUTER JOIN `taskStates`
            ON `tasks`.`taskStateID` = `taskStates`.`tsID`
            LEFT OUTER JOIN `hosts`
            ON `tasks`.`taskHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `images`
            ON `tasks`.`taskImageID` = `images`.`imageID`
            LEFT OUTER JOIN `nfsGroupMembers`
            ON `tasks`.`taskNFSMemberID` = `nfsGroupMembers`.`ngmID`
            LEFT OUTER JOIN `users`
            ON `tasks`.`taskCreateBy` = `users`.`uName`
            %s
            %s
            %s";
        $tasksFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `taskTypes`
            ON `tasks`.`taskTypeID` = `taskTypes`.`ttID`
            LEFT OUTER JOIN `taskStates`
            ON `tasks`.`taskStateID` = `taskStates`.`tsID`
            LEFT OUTER JOIN `hosts`
            ON `tasks`.`taskHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `images`
            ON `tasks`.`taskImageID` = `images`.`imageID`
            LEFT OUTER JOIN `nfsGroupMembers`
            ON `tasks`.`taskNFSMemberID` = `nfsGroupMembers`.`ngmID`
            LEFT OUTER JOIN `users`
            ON `tasks`.`taskCreateBy` = `users`.`uName`
            %s";
        $tasksTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            LEFT OUTER JOIN `taskTypes`
            ON `tasks`.`taskTypeID` = `taskTypes`.`ttID`
            LEFT OUTER JOIN `taskStates`
            ON `tasks`.`taskStateID` = `taskStates`.`tsID`
            LEFT OUTER JOIN `hosts`
            ON `tasks`.`taskHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `images`
            ON `tasks`.`taskImageID` = `images`.`imageID`
            LEFT OUTER JOIN `nfsGroupMembers`
            ON `tasks`.`taskNFSMemberID` = `nfsGroupMembers`.`ngmID`
            LEFT OUTER JOIN `users`
            ON `tasks`.`taskCreateBy` = `users`.`uName`
            WHERE $where";
        foreach (self::getClass('TaskManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'host' . $common
            ];
            unset($real);
        }
        foreach (self::getClass('ImageManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'image' . $common
            ];
            unset($real);
        }
        foreach (self::getclass('TaskStateManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'taskstate' . $common
            ];
            unset($real);
        }
        foreach (self::getClass('StorageNodeManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'storagenode' . $common
            ];
            unset($real);
        }
        foreach (self::getClass('UserManager')
            ->getColumns() as $common => &$real
        ) {
            if (in_array($common, ['id', 'name'])) {
                $columns[] = [
                    'db' => $real,
                    'dt' => 'user' . $common
                ];
                continue;
            }
            break;
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'tasks',
                'taskID',
                $columns,
                $tasksSqlStr,
                $tasksFilterStr,
                $tasksTotalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Get the active multicast tasks
     *
     * @return void
     */
    public function getActiveMulticastTasks()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $activestates = [
            'queued',
            'checked in',
            'in-progress'
        ];

        $where = "`taskStates`.`tsName` IN ('"
            . implode("','", $activestates)
            . "') AND `taskTypes`.`ttName` = 'Multi-Cast'";

        $tasksSqlStr = "SELECT `%s`
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `multicastSessions`.`msState` = `taskStates`.`tsID`
            %s
            %s
            %s";
        $tasksFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `multicastSessions`.`msState` = `taskStates`.`tsID`
            %s";
        $tasksTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `multicastSessions`.`msState` = `taskStates`.`tsID`
            WHERE $where";
        foreach (self::getClass('MulticastSessionManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        foreach (self::getClass('TaskTypeManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'tasktype'.$common
            ];
            unset($real);
        }
        foreach (self::getClass('TaskStateManager')
            ->getColumns() as $common => &$real
        ) {
            $collumns[] = [
                'db' => $real,
                'dt' => 'taskstate'.$common
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'multicastSessions',
                'msID',
                $columns,
                $tasksSqlStr,
                $tasksFilterStr,
                $tasksTotalStr,
                $where
            )
        );
        exit;
    }
    /**
     * Display the active tasks.
     *
     * @return void
     */
    public function active()
    {
        $this->title = _('Active Tasks');
        echo '<!-- Active Tasks -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'tasks-active-table');
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<div class="btn-group">';
        echo $this->_buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * For cancelling/forcing tasks.
     *
     * @return void
     */
    public function activePost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVE_CANCEL'
        );
        try {
            if (isset($_POST['cancelconfirm'])) {
                $tasks = filter_input_array(
                    INPUT_POST,
                    [
                        'tasks' => [
                            'flags' => FILTER_REQUIRE_ARRAY
                        ]
                    ]
                );
                $tasks = $tasks['tasks'];
                self::getClass('TaskManager')->cancel($tasks);
            }
            $code = 201;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook
            );
        echo $msg;
        exit;
    }
    /**
     * Display active multicast tasks.
     *
     * @return void
     */
    public function activemulticast()
    {
        $this->title = _('Active Multi-cast Tasks');
        $this->headerData = [
            _('Task Name'),
            _('Hosts in tasking'),
            _('Start Time'),
            _('Status')
        ];
        $this->templates = [
            '',
            '',
            '',
            ''
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
        echo '<!-- Active Multi-cast Tasks -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'tasks-active-table');
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<div class="btn-group">';
        echo $this->_buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Removes multicast sessions.
     *
     * @return void
     */
    public function activemulticastPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVEMULTICAST'
        );
        $serverFault = false;
        try {
            if (isset($_POST['cancelconfirm'])) {
                $tasks = filter_input_array(
                    INPUT_POST,
                    [
                        'tasks' => [
                            'flags' => FILTER_REQUIRE_ARRAY
                        ]
                    ]
                );
            }
            $tasks = $tasks['tasks'];
            $mtasks = $tasks;
            $tasks = self::getSubObjectIDs(
                'MulticastSessionAssociation',
                [
                    'msID' => $mtasks
                ],
                'taskID'
            );
            self::getClass('TaskManager')->cancel($tasks);
            self::getClass('MulticastSessionManager')->cancel($mtasks);
            $code = 201;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook
            );
        echo $msg;
        exit;
    }
    /**
     * Displays active snapin tasks.
     *
     * @return void
     */
    public function activesnapins()
    {
        $this->title = 'Active Snapins';
        $this->headerData = [
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler2"/><label for="'
            . 'toggler2"></label>',
            _('Host Name'),
            _('Snapin'),
            _('Start Time'),
            _('State'),
        ];
        $this->templates = [
            '<input type="checkbox" name="task[]" value="${id}" class='
            . '"toggle-action" id="sntasks-${id}"/><label for="'
            . 'sntasks-${id}"></label>',
            sprintf(
                '<p><a href="?node=host&sub=edit&id=${host_id}" title='
                . '"%s">${host_name}</a></p><small>${host_mac}</small>',
                _('Edit Host')
            ),
            '${name}',
            '${startDate}',
            '${state}',
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];
        Route::active('snapintask');
        $SnapinTasks = json_decode(
            Route::getData()
        );
        $activestate = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $SnapinTasks = $SnapinTasks->data;
        foreach ((array)$SnapinTasks as &$SnapinTask) {
            Route::indiv('snapin', $SnapinTask->snapinID);
            $Snapin = json_decode(Route::getData());
            if (!$Snapin->id) {
                continue;
            }
            $SnapinJob = $SnapinTask->snapinjob;
            if (!$SnapinJob->id) {
                continue;
            }
            Route::indiv('host', $SnapinJob->hostID);
            $Host = json_decode(
                Route::getData()
            );
            if (!$Host->id) {
                continue;
            }
            $state = $SnapinJob->stateID;
            $inArr = in_array($state, $activestate);
            if (!$inArr) {
                continue;
            }
            $this->data[] = [
                'id' => $SnapinTask->id,
                'name' => $Snapin->name,
                'host_id' => $Host->id,
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'startDate' => self::formatTime(
                    $SnapinTask->checkin,
                    'Y-m-d H:i:s'
                ),
                'state' => $SnapinTask->state->name
            ];
            unset(
                $SnapinTask,
                $Snapin,
                $SnapinJob,
                $Host
            );
        }
        self::$HookManager
            ->processEvent(
                'TaskActiveSnapinsData',
                [
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                ]
            );
        unset($SnapinTasks);
        if (self::$ajax) {
            return $this->render();
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Cancels and snapin taskings.
     *
     * @return void
     */
    public function activesnapinsPost()
    {
        $SnapinTaskIDs = (array)$_POST['task'];
        if (count($SnapinTaskIDs) > 0) {
            $SnapinJobIDs = self::getSubObjectIDs(
                'SnapinTask',
                ['id' => $SnapinTaskIDs],
                'jobID'
            );
            self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        }
        if (count($SnapinJobIDs) > 0) {
            $HostIDs = self::getSubObjectIDs(
                'SnapinJob',
                ['id' => $SnapinJobIDs],
                'hostID'
            );
        }
        if (count($HostIDs) > 0) {
            $SnapTaskIDs = self::getSubObjectIDs(
                'SnapinTask',
                ['jobID' => $SnapinJobIDs]
            );
            $TaskIDs = array_diff(
                $SnapTaskIDs,
                $SnapinTaskIDs
            );
        }
        if (count($TaskIDs) < 1) {
            $TaskIDs = self::getSubObjectIDs(
                'Task',
                [
                    'hostID' => $HostIDs,
                    'typeID' => [
                        12,
                        13
                    ]
                ]
            );
            self::getClass('TaskManager')->cancel($TaskIDs);
        }
        exit;
    }
    /**
     * Active scheduled tasks (delayed or cron)
     *
     * @return void
     */
    public function activescheduled()
    {
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
        $this->title = _('Scheduled Tasks');
        $this->headerData = [
            '<input type="checkbox" name="toggle-checkbox" class='
            . '"toggle-checkboxAction" id="toggler3"/><label for="'
            . 'toggler3"></label>',
            _('Host/Group Name'),
            _('Is Group'),
            _('Task Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active'),
            _('Type'),
        ];
        $this->templates = [
            '<input type="checkbox" name="task[]" value="${id}" class='
            . '"toggle-action" id="sctasks-${id}"/><label for="'
            . 'sctasks-${id}"></label>',
            '<a href="?node=${hostgroup}&sub=edit&id=${hostgroupid}" title='
            . '"Edit ${nametype}: ${hostgroupname}">${hostgroupname}</a>${extra}',
            '${groupbased}',
            '<span class="icon" title="${hover}">${details_taskname}</span>',
            '${task_type}',
            '<small>${start_time}</small>',
            '${active}',
            '${type}',
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
        Route::active('scheduledtask');
        $ScheduledTasks = json_decode(
            Route::getData()
        );
        $ScheduledTasks = $ScheduledTasks->data;
        foreach ((array)$ScheduledTasks as &$ScheduledTask) {
            $method = 'host';
            if ($ScheduledTask->isGroupTask) {
                $method = 'group';
            }
            $ObjTest = $ScheduledTask->{$method};
            if (!$ObjTest->id) {
                continue;
            }
            $TaskType = $ScheduledTask->tasktype;
            if (!$TaskType->id) {
                continue;
            }
            $sID = $ScheduledTask->other2;
            if ($TaskType->isSnapinTasking) {
                if ($TaskType->id == 12
                    || $ScheduledTask->other2 == -1
                ) {
                    $hover = _('All snapins');
                } elseif ($TaskType->id == 13) {
                    $snapin = new Snapin($sID);
                    if (!$snapin->isValid()) {
                        $hover = _('Invalid snapin');
                    } else {
                        $hover = _('Snapin to be installed')
                            . ': '
                            . $snapin->get('name');
                    }
                }
            }
            $this->data[] = [
                'id' => $ScheduledTask->id,
                'start_time' => $ScheduledTask->runtime,
                'groupbased' => (
                    $ScheduledTask->isGroupTask ?
                    _('Yes') :
                    _('No')
                ),
                'active' => (
                    $ScheduledTask->isActive ?
                    _('Yes') :
                    _('No')
                ),
                'type' => $ScheduledTask->type == 'C' ? _('Cron') : _('Delayed'),
                'hostgroup' => (
                    $ScheduledTask->isGroupTask ?
                    _('group') :
                    _('host')
                ),
                'hostgroupname' => $ObjTest->name,
                'hostgroupid' => $ObjTest->id,
                'details_taskname' => $ScheduledTask->name,
                'task_type' => $TaskType->name,
                'extra' => (
                    $ScheduledTask->isGroupTask ?
                    '' :
                    sprintf(
                        '<br/>%s',
                        $ObjTest->primac
                    )
                ),
                'nametype' => $method,
                'hover' => $hover
            ];
            unset($ScheduledTask, $ObjTest, $TaskType);
        }
        self::$HookManager
            ->processEvent(
                'TaskScheduledData',
                [
                    'headerData' => &$this->headerData,
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                ]
            );
        if (self::$ajax) {
            return $this->render();
        }
        unset(
            $this->data,
            $this->form,
            $this->headerData,
            $this->attributes,
            $this->templates
        );
    }
    /**
     * Canceled tasks for us.
     *
     * @return void
     */
    public function activescheduledPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVE_CANCEL'
        );
        try {
            if (isset($_POST['cancelconfirm'])) {
                $tasks = filter_input_array(
                    INPUT_POST,
                    [
                        'tasks' => [
                            'flags' => FILTER_REQUIRE_ARRAY
                        ]
                    ]
                );
                self::getClass('ScheduledTaskManager')->destroy($tasks);
            }
            $code = 201;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = ($serverFault ? 500 : 400);
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook
            );
        echo $msg;
        exit;
    }
}
