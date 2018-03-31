<?php
/**
 * Displays tasks to the user.
 *
 * PHP version 5
 *
 * @category TaskManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Displays tasks to the user.
 *
 * @category TaskManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskManagement extends FOGPage
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

        $this->_buttons = self::makeButton(
            'resume-refresh',
            _('Resume Reload'),
            'btn btn-success'
        );
        $this->_buttons .= self::makeButton(
            'pause-refresh',
            _('Pause Reload'),
            'btn btn-warning'
        );
        $this->_buttons .= self::makeButton(
            'cancel-selected',
            _('Cancel Selected'),
            'btn btn-danger',
            $props
        );
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
            $columns[] = [
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
     * Get the active snapin tasks
     *
     * @return void
     */
    public function getActiveSnapinTasks()
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
            . "') AND `taskTypes`.`ttName` IN ('All Snapins','Single Snapin')";

        $tasksSqlStr = "SELECT `%s`
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `snapinTasks`.`stState` = `taskStates`.`tsID`
            LEFT OUTER JOIN `snapinJobs`
            ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
            LEFT OUTER JOIN `hosts`
            ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `snapins`
            ON `snapinTasks`.`stSnapinID` = `snapins`.`sID`
            %s
            %s
            %s";
        $tasksFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `snapinTasks`.`stState` = `taskStates`.`tsID`
            LEFT OUTER JOIN `snapinJobs`
            ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
            LEFT OUTER JOIN `hosts`
            ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `snapins`
            ON `snapinTasks`.`stSnapinID` = `snapins`.`sID`
            %s";
        $tasksTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            CROSS JOIN `taskTypes`
            LEFT OUTER JOIN `taskStates`
            ON `snapinTasks`.`stState` = `taskStates`.`tsID`
            LEFT OUTER JOIN `snapinJobs`
            ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
            LEFT OUTER JOIN `hosts`
            ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `snapins`
            ON `snapinTasks`.`stSnapinID` = `snapins`.`sID`
            WHERE $where";
        foreach (self::getClass('SnapinTaskManager')
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
            $columns[] = [
                'db' => $real,
                'dt' => 'taskstate'.$common
            ];
            unset($real);
        }
        foreach (self::getClass('HostManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'host'.$common
            ];
            unset($real);
        }
        foreach (self::getClass('SnapinManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'snapin'.$common
            ];
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'snapinTasks',
                'stID',
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
     * Get the scheduled tasks list.
     *
     * @return void
     */
    public function getScheduledTasks()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $tasksSqlStr = "SELECT `%s`
            FROM `%s`
            %s
            %s
            %s";
        $tasksFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            %s";
        $tasksTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`";
        foreach (self::getClass('ScheduledTaskManager')
            ->getColumns() as $common => &$real
        ) {
            switch ($common) {
            case 'hostID':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        if ($row['stIsGroup']) {
                            $groupName = self::getClass('Group', $d)->get('name');
                            return '<a href="'
                                . '../management/index.php?node=group&sub=edit&id='
                                . $d
                                . '">'
                                . _('Group')
                                . ': '
                                . $groupName
                                . '</a>';
                        } else {
                            $hostName = self::getClass('Host', $d)->get('name');
                            return '<a href="'
                                . '../management/index.php?node=host&sub=edit&id='
                                . $d
                                . '">'
                                . _('Host')
                                . ': '
                                . $hostName
                                . '</a>';
                        }
                    }
                ];
                break;
            case 'type':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        $type = strtolower($d);
                        switch ($type) {
                        case 'c':
                            return _('Cron');
                        default:
                            return _('Delayed');
                        }
                    }
                ];
                $columns[] = [
                    'dt' => 'starttime',
                    'formatter' => function ($d, $row) {
                        $type = strtolower($d);
                        switch ($type) {
                        case 'c':
                            $cronstr = sprintf(
                                '%s %s %s %s %s',
                                $row['stMinute'],
                                $row['stHour'],
                                $row['stDOM'],
                                $row['stMonth'],
                                $row['stDOW']
                            );
                            return FOGCron::parse($cronstr);
                        default:
                            return self::niceDate($row['stMinute']);
                        }
                    }
                ];
                break;
            case 'taskType':
                $columns[] = [
                    'db' => $real,
                    'dt' => $common,
                    'formatter' => function ($d, $row) {
                        return self::getClass('TaskType', $d)->get('name');
                    }
                ];
                break;
            default:
                $columns[] = [
                    'db' => $real,
                    'dt' => $common
                ];
            }
            unset($real);
        }
        echo json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'scheduledTasks',
                'stID',
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
        $this->render(12, 'active-tasks-table');
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
        $this->render(12, 'active-multicast-table');
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
                ['msID' => $mtasks],
                'taskID'
            );
            self::getClass('TaskManager')->cancel($tasks);
            self::getClass('MulticastSessionManager')->cancel($mtasks);
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
        $this->title = 'Active Snapin Tasks';
        $this->headerData = [
            _('Snapin Name'),
            _('Host Name'),
            _('Start Time'),
            _('Status')
        ];
        $this->attributes = [
            [],
            [],
            [],
            []
        ];
        echo '<!-- Active Snapin Tasks -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'active-snapintasks-table');
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<div class="btn-group">';
        echo $this->_buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Cancels and snapin taskings.
     *
     * @return void
     */
    public function activesnapinsPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVESNAPIN'
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
            $SnapinJobIDs = self::getSubObjectIDs(
                'SnapinTask',
                ['id' => $tasks],
                'jobID'
            );
            self::getClass('SnapinTaskManager')->cancel($tasks);
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
     * Active scheduled tasks (delayed or cron)
     *
     * @return void
     */
    public function activescheduled()
    {
        $this->title = _('Scheduled Tasks');
        $this->headerData = [
            _('Host/Group Name'),
            _('Task Type'),
            _('Start Time'),
            _('Active'),
            _('Type')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            []
        ];
        echo '<!-- Scheduled Tasks -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'scheduled-task-table');
        echo '</div>';
        echo '<div class="box-footer">';
        echo '<div class="btn-group">';
        echo $this->_buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
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
