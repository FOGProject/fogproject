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
            _('Task Type'),
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

        $this->_buttons .= self::makeButton(
            'cancel-selected',
            _('Cancel Selected'),
            'btn btn-danger pull-left',
            $props
        );
        $this->_buttons .= self::makeButton(
            'pause-refresh',
            _('Pause Reload'),
            'btn btn-warning pull-left'
        );
        $this->_buttons .= self::makeButton(
            'resume-refresh',
            _('Resume Reload'),
            'btn btn-success pull-right'
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
        foreach (self::getClass('TaskTypeManager')
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'tasktype' . $common
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
        Route::active('snapintask');
        echo Route::getData();
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

        Route::active('scheduledtask');
        echo Route::getData();
    }
    /**
     * Get the scheduled deletions list.
     *
     * @return void
     */
    public function getScheduledDeleteQueues()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        Route::active('filedeletequeue');
        echo Route::getData();
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
        echo '<div class="box-footer with-border">';
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
                $hook,
                [
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
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
        echo '<div class="box-footer with-border">';
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
            'TASK_ACTIVEMULTICAST_CANCEL'
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
            $find = ['msID' => $mtasks];
            Route::ids(
                'multicastsessionassociation',
                $find,
                'taskID'
            );
            $tasks = json_decode(
                Route::getData(),
                true
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
                $hook,
                [
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
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
        echo '<div class="box-footer with-border">';
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
            'TASK_ACTIVESNAPIN_CANCEL'
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
            self::getClass('SnapinTaskManager')->cancel($tasks);
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
                $hook,
                [
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
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
        echo '<div class="box-footer with-border">';
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
            'TASK_ACTIVESCHEDULED_CANCEL'
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
                self::getClass('ScheduledTaskManager')->cancel($tasks);
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
                $hook,
                [
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        echo $msg;
        exit;
    }
    /**
     * Active scheduled path deletions 
     *
     * @return void
     */
    public function activescheduleddels()
    {
        $this->title = _('Queued Path Deletions');
        $this->headerData = [
            _('Storage Group Name'),
            _('Path Name'),
            _('Path Type'),
            _('Created Time'),
            _('Completed Time'),
            _('Created By'),
            _('State'),
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
        echo '<!-- Scheduled Deletions -->';
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        $this->render(12, 'scheduled-deletion-table');
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo '<div class="btn-group">';
        echo $this->_buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Canceled scheduled path deletions.
     *
     * @return void
     */
    public function activescheduleddelsPost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'QUEUED_DELETION_CANCEL'
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
                self::getClass('FileDeleteQueueManager')->cancel($tasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'QUEUED_DELETION_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks cancelled!'),
                    'title' => _('Queue Deletion Cancel Success')
                ]
            );
        } catch (Exception $e) {
            $code = (
                $serverFault ?
                HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR :
                HTTPResponseCodes::HTTP_BAD_REQUEST
            );
            $hook = 'QUEUED_DELETION_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Queue Deletion Cancel Fail')
                ]
            );
        }
        http_response_code($code);
        self::$HookManager
            ->processEvent(
                $hook,
                [
                    'hook' => &$hook,
                    'code' => &$code,
                    'msg' => &$msg,
                    'serverFault' => &$serverFault
                ]
            );
        echo $msg;
        exit;
    }
}
