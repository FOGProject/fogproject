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
        $this->name = _('Task Management');
        parent::__construct($this->name);
    }
    /**
     * The default landing: the tabbed task view.
     *
     * @param mixed ...$args Unused, signature match with FOGPage.
     *
     * @return void
     */
    public function index(...$args)
    {
        $this->_tabbed('active');
    }
    /**
     * Legacy deep link: pre-select the active tasks tab.
     *
     * @return void
     */
    public function active()
    {
        $this->_tabbed('active');
    }
    /**
     * Legacy deep link: pre-select the multicast tab.
     *
     * @return void
     */
    public function activemulticast()
    {
        $this->_tabbed('multicast');
    }
    /**
     * Legacy deep link: pre-select the snapins tab.
     *
     * @return void
     */
    public function activesnapins()
    {
        $this->_tabbed('snapins');
    }
    /**
     * Legacy deep link: pre-select the scheduled tab.
     *
     * @return void
     */
    public function activescheduled()
    {
        $this->_tabbed('scheduled');
    }
    /**
     * Legacy deep link: pre-select the path deletions tab.
     *
     * @return void
     */
    public function activescheduleddels()
    {
        $this->_tabbed('deletions');
    }
    /**
     * Deep link: pre-select the recent tasks tab.
     *
     * @return void
     */
    public function recent()
    {
        $this->_tabbed('recent');
    }
    /**
     * Renders the single tabbed task page.
     *
     * @param string $initialTab The tab pane id to pre-select client side.
     *
     * @return void
     */
    private function _tabbed($initialTab)
    {
        $this->title = _('Task Management');
        $badge = function ($key) {
            return ' <span class="badge task-count-badge" data-count="'
                . $key
                . '"></span>';
        };
        $tabData = [
            [
                'name' => self::$foglang['ActiveTasks'] . $badge('active'),
                'id' => 'active',
                'generator' => function () {
                    $this->_activePane();
                }
            ],
            [
                'name' => self::$foglang['ActiveMCTasks'] . $badge('multicast'),
                'id' => 'multicast',
                'generator' => function () {
                    $this->_multicastPane();
                }
            ],
            [
                'name' => self::$foglang['ActiveSnapins'] . $badge('snapins'),
                'id' => 'snapins',
                'generator' => function () {
                    $this->_snapinsPane();
                }
            ],
            [
                'name' => self::$foglang['ScheduledTasks'] . $badge('scheduled'),
                'id' => 'scheduled',
                'generator' => function () {
                    $this->_scheduledPane();
                }
            ],
            [
                'name' => _('Queued Path Deletions') . $badge('deletions'),
                'id' => 'deletions',
                'generator' => function () {
                    $this->_deletionsPane();
                }
            ],
            [
                'name' => _('Recent'),
                'id' => 'recent',
                'generator' => function () {
                    $this->_recentPane();
                }
            ]
        ];
        self::$HookManager->processEvent(
            'TASK_TABS',
            ['tabData' => &$tabData]
        );
        echo self::tabFields($tabData, false);
        // Create is this modal's commit action, so it is the primary and sits
        // right; the dismiss sits left. Matches the multicast session modal on
        // imagemanagement, which is the reference for this pane shape.
        $modalApprovalBtns = self::makeButton(
            'tasking-send',
            _('Create'),
            'btn btn-primary float-end'
        );
        $modalApprovalBtns .= self::makeButton(
            'tasking-close',
            _('Cancel'),
            'btn btn-outline-secondary float-start',
            'data-bs-dismiss="modal"'
        );
        echo self::makeModal(
            'task-modal',
            '<h4 class="card-title">'
            . _('Create new tasking')
            . '<span class="task-name"></span></h4>',
            '<div id="task-form-holder"></div>',
            $modalApprovalBtns,
            '',
            'success'
        );
        echo '<input type="hidden" id="task-initial-tab" value="'
            . $initialTab
            . '"/>';
    }
    /**
     * The cancel/pause/resume footer buttons for one pane.
     *
     * Ids are suffixed so all panes can coexist in one page; the JS
     * binds by class and reads the POST endpoint off the action prop.
     *
     * @param string $sub    The sub endpoint the cancel POST targets.
     * @param string $suffix The per-pane button id suffix.
     *
     * @return string
     */
    private function _paneButtons($sub, $suffix)
    {
        $props = ' method="post" action="'
            . '../management/index.php?node=task&sub='
            . $sub
            . '" ';
        $buttons = self::makeButton(
            'cancel-selected-' . $suffix,
            _('Cancel Selected'),
            'btn btn-danger cancel-selected float-start',
            $props
        );
        $buttons .= self::makeButton(
            'pause-refresh-' . $suffix,
            _('Pause Reload'),
            'btn btn-warning pause-refresh float-start'
        );
        $buttons .= self::makeButton(
            'resume-refresh-' . $suffix,
            _('Resume Reload'),
            'btn btn-success resume-refresh float-end'
        );
        return $buttons;
    }
    /**
     * Renders the active tasks pane.
     *
     * @return void
     */
    private function _activePane()
    {
        $this->headerData = [
            _('Host Name'),
            _('Image Name'),
            _('Storage Node'),
            _('Started By'),
            _('First Check In'),
            _('Last Check In'),
            _('Task Type'),
            _('Status'),
            _('Progress')
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            []
        ];
        echo '<!-- Active Tasks -->';
        $this->render(
            12,
            'active-tasks-table',
            $this->_paneButtons('active', 'active')
        );
    }
    /**
     * Renders the active multicast tasks pane.
     *
     * @return void
     */
    private function _multicastPane()
    {
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
        $this->render(
            12,
            'active-multicast-table',
            $this->_paneButtons('activemulticast', 'multicast')
        );
    }
    /**
     * Renders the active snapin tasks pane.
     *
     * @return void
     */
    private function _snapinsPane()
    {
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
        $this->render(
            12,
            'active-snapintasks-table',
            $this->_paneButtons('activesnapins', 'snapins')
        );
    }
    /**
     * Renders the scheduled tasks pane.
     *
     * @return void
     */
    private function _scheduledPane()
    {
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
        $this->render(
            12,
            'scheduled-task-table',
            $this->_paneButtons('activescheduled', 'scheduled')
        );
    }
    /**
     * Renders the queued path deletions pane.
     *
     * @return void
     */
    private function _deletionsPane()
    {
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
        $this->render(
            12,
            'scheduled-deletion-table',
            $this->_paneButtons('activescheduleddels', 'deletions')
        );
    }
    /**
     * Renders the recent (completed/cancelled) tasks pane.
     *
     * @return void
     */
    private function _recentPane()
    {
        $this->headerData = [
            _('Host Name'),
            _('Image Name'),
            _('Task Type'),
            _('Started By'),
            _('State'),
            _('Completed'),
            ''
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
        echo '<!-- Recent Tasks -->';
        echo '<div class="row mb-3">';
        echo '<div class="col-sm-4">';
        echo '<label class="form-label" for="recent-type-filter">'
            . _('Task Type')
            . '</label>';
        echo '<select id="recent-type-filter" class="form-select" '
            . 'autocomplete="off">';
        echo '<option value="imaging" selected>' . _('Imaging') . '</option>';
        echo '<option value="snapins">' . _('Snapins') . '</option>';
        echo '<option value="wipes">' . _('Wipes') . '</option>';
        echo '<option value="other">' . _('Other') . '</option>';
        echo '<option value="all">' . _('All Types') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="col-sm-8">';
        echo '<label class="form-label d-block">' . _('State') . '</label>';
        echo '<div class="btn-group" role="group" aria-label="'
            . _('State filter')
            . '">';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-both" value="both" autocomplete="off" checked/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-both">'
            . _('Both')
            . '</label>';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-complete" value="complete" autocomplete="off"/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-complete">'
            . _('Complete')
            . '</label>';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-cancelled" value="cancelled" autocomplete="off"/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-cancelled">'
            . _('Cancelled')
            . '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $this->render(12, 'recent-tasks-table');
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
        $columns = $this->_taskJoinColumns();
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
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
        ));
    }
    /**
     * Get recently completed/cancelled tasks.
     *
     * The Recent tab posts two extra filter vars alongside the
     * DataTables request: states (both|complete|cancelled) and
     * typegroup (imaging|snapins|wipes|other|all).
     *
     * @return void
     */
    public function getRecentTasks()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $complete = (int)self::getCompleteState();
        $cancelled = (int)self::getCancelledState();
        switch ($pass_vars['states'] ?? '') {
            case 'complete':
                $states = [$complete];
                break;
            case 'cancelled':
                $states = [$cancelled];
                break;
            default:
                $states = [$complete, $cancelled];
        }
        $where = "`tasks`.`taskStateID` IN ("
            . implode(',', $states)
            . ")";

        $groups = [
            'imaging' => array_unique(
                array_merge(
                    TaskType::DEPLOYTASKS,
                    TaskType::CAPTURETASKS
                )
            ),
            'snapins' => TaskType::SNAPINTASKS,
            'wipes' => TaskType::WIPETASKS
        ];
        $typegroup = $pass_vars['typegroup'] ?? 'imaging';
        if (isset($groups[$typegroup])) {
            $where .= " AND `tasks`.`taskTypeID` IN ("
                . implode(',', $groups[$typegroup])
                . ")";
        } elseif ($typegroup == 'other') {
            $where .= " AND `tasks`.`taskTypeID` NOT IN ("
                . implode(',', array_unique(array_merge(...array_values($groups))))
                . ")";
        }
        // Anything else ('all' included) adds no type clause.

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
        $columns = $this->_taskJoinColumns();
        $columns[] = [
            'db' => 'taskStateChangedTime',
            'dt' => 'statechanged',
            'formatter' => function ($d, $row) {
                // Rows created before taskStateChangedTime existed have
                // NULL/zero dates; fall back to the newest of the task's
                // check-in and creation times for display.
                $empty = function ($v) {
                    return !$v || strpos($v, '0000') === 0;
                };
                if (!$empty($d)) {
                    return $d;
                }
                $best = '';
                foreach (['taskCheckIn', 'taskCreateTime'] as $col) {
                    $v = $row[$col] ?? '';
                    if (!$empty($v) && $v > $best) {
                        $best = $v;
                    }
                }
                return $best;
            }
        ];
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
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
        ));
    }
    /**
     * The shared column set for the joined tasks queries.
     *
     * @return array
     */
    private function _taskJoinColumns()
    {
        $columns = [];
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
        foreach (self::getClass('TaskStateManager')
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
        return $columns;
    }
    /**
     * Live counts for the tab badges. Each count mirrors the WHERE
     * its tab's table query uses so badge and row counts agree.
     *
     * @return void
     */
    public function getTaskCounts()
    {
        header('Content-type: application/json');
        $activeStates = [
            self::getQueuedState(),
            self::getCheckedInState(),
            self::getProgressState()
        ];
        $queuedProgress = self::getQueuedStates();
        $queuedProgress[] = self::getProgressState();
        echo json_encode(
            [
                'active' => Route::getCount(
                    'task',
                    ['stateID' => $activeStates],
                    true
                ),
                'multicast' => Route::getCount(
                    'multicastsession',
                    ['stateID' => $activeStates],
                    true
                ),
                'snapins' => Route::getCount(
                    'snapintask',
                    ['stateID' => $queuedProgress],
                    true
                ),
                'scheduled' => Route::getCount(
                    'scheduledtask',
                    ['isActive' => 1],
                    true
                ),
                'deletions' => Route::getCount(
                    'filedeletequeue',
                    ['stateID' => $queuedProgress],
                    true
                )
            ]
        );
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
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
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
        ));
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

        $states = self::getQueuedStates();
        $states[] = self::getProgressState();
        $stateList = implode(
            ',',
            array_map('intval', (array)$states)
        );

        $where = "`snapinTasks`.`stState` IN ($stateList)";

        // Join snapins/hosts so the snapin and host NAMES are real, sortable
        // columns (the generic snapintask listing only exposes their IDs, so
        // sorting by "name" silently sorted by ID). Mirrors getActiveTasks().
        $joins = "LEFT OUTER JOIN `snapins`
            ON `snapinTasks`.`stSnapinID` = `snapins`.`sID`
            LEFT OUTER JOIN `snapinJobs`
            ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
            LEFT OUTER JOIN `hosts`
            ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
            LEFT OUTER JOIN `taskStates`
            ON `snapinTasks`.`stState` = `taskStates`.`tsID`";
        $snapinSqlStr = "SELECT `%s`
            FROM `%s`
            $joins
            %s
            %s
            %s";
        $snapinFilterStr = "SELECT COUNT(`%s`)
            FROM `%s`
            $joins
            %s";
        $snapinTotalStr = "SELECT COUNT(`%s`)
            FROM `%s`
            $joins
            WHERE $where";
        $columns = [
            ['db' => 'stID', 'dt' => 'id'],
            [
                'db' => 'stID',
                'dt' => 'DT_RowId',
                'formatter' => function ($d, $row) {
                    return 'row_' . $d;
                }
            ],
            ['db' => 'stSnapinID', 'dt' => 'snapinid'],
            ['db' => 'sName', 'dt' => 'snapinname'],
            ['db' => 'hostID', 'dt' => 'hostid'],
            ['db' => 'hostName', 'dt' => 'hostname'],
            ['db' => 'stCheckinDate', 'dt' => 'checkin'],
            ['db' => 'tsName', 'dt' => 'taskstatename'],
            ['db' => 'tsIcon', 'dt' => 'taskstateicon'],
        ];
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'snapinTasks',
                'stID',
                $columns,
                $snapinSqlStr,
                $snapinFilterStr,
                $snapinTotalStr,
                $where
            )
        ));
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
     * For cancelling/forcing tasks.
     *
     * @return void
     */
    public function activePost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVE_CANCEL'
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
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Removes multicast sessions.
     *
     * @return void
     */
    public function activemulticastPost()
    {
        self::checkAuthAndCSRF();
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
                $tasks = $tasks['tasks'];
                $mtasks = $tasks;
                $find = ['msID' => $mtasks];
                $tasks = Route::getIds(
                    'multicastsessionassociation',
                    $find,
                    'taskID'
                );
                self::getClass('TaskManager')->cancel($tasks);
                self::getClass('MulticastSessionManager')->cancel($mtasks);
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
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Cancels and snapin taskings.
     *
     * @return void
     */
    public function activesnapinsPost()
    {
        self::checkAuthAndCSRF();
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
                $tasks = $tasks['tasks'];
                self::getClass('SnapinTaskManager')->cancel($tasks);
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
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Canceled tasks for us.
     *
     * @return void
     */
    public function activescheduledPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'TASK_ACTIVESCHEDULED_CANCEL'
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
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'TASK_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Task Cancel Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
    /**
     * Canceled scheduled path deletions.
     *
     * @return void
     */
    public function activescheduleddelsPost()
    {
        self::checkAuthAndCSRF();
        header('Content-type: application/json');
        self::$HookManager->processEvent(
            'QUEUED_DELETION_CANCEL'
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
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'QUEUED_DELETION_CANCEL_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Queue Deletion Cancel Fail')
                ]
            );
        }
        $this->jsonHookResponse(
            [
                'hook' => &$hook,
                'code' => &$code,
                'msg' => &$msg,
                'serverFault' => &$serverFault
            ],
            $hook
        );
    }
}
