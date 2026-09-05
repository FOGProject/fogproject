<?php
/**
 * Displays tasks to the user.
 *
 * PHP version 7.4+
 *
 * @category TaskManagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Pages;

use FOG\Base\FOGManagerController;
use FOG\Base\FOGPage;
use FOG\Items\TaskLog;
use FOG\Items\TaskType;
use FOG\Managers\FileDeleteQueueManager;
use FOG\Managers\HostManager;
use FOG\Managers\ImageManager;
use FOG\Managers\MulticastSessionManager;
use FOG\Managers\ScheduledTaskManager;
use FOG\Managers\SnapinTaskManager;
use FOG\Managers\StorageNodeManager;
use FOG\Managers\TaskManager;
use FOG\Managers\TaskStateManager;
use FOG\Managers\TaskTypeManager;
use FOG\Managers\UserManager;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
     * This grid does not select.
     *
     * Tasks are canceled per-pane, never deleted, so there is nothing for a
     * selection to act on.
     *
     * @var bool
     */
    public $selectable = false;
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
     * Deep link: pre-select the task log tab.
     *
     * @return void
     */
    public function logs()
    {
        $this->_tabbed('logs');
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
            ],
            [
                'name' => _('Logs'),
                'id' => 'logs',
                'generator' => function () {
                    $this->_logsPane();
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
        // One self-relabeling toggle, not a pause/resume pair -- pausing the
        // auto-refresh destroys nothing so it never belonged on the left with
        // Cancel Selected, and only ever one of the two was pressable. It is
        // the sole right-side button here, so it takes primary.
        $buttons .= self::makeReloadToggle(
            'reload-toggle-' . $suffix,
            'btn btn-primary float-end'
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
     * Renders the recent (completed/canceled) tasks pane.
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
        // 'all' rather than 'both': there are three finished states since
        // schema 339 added Failed, so a two-way label was about to start
        // lying. getRecentTasks() still treats any unrecognized value as
        // all-of-them, so a page cached before this keeps working.
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-all" value="all" autocomplete="off" checked/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-all">'
            . _('All')
            . '</label>';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-complete" value="complete" autocomplete="off"/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-complete">'
            . _('Complete')
            . '</label>';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-cancelled" value="cancelled" autocomplete="off"/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-cancelled">'
            . _('Canceled')
            . '</label>';
        echo '<input type="radio" class="btn-check" name="recent-state-filter"'
            . ' id="recent-state-failed" value="failed" autocomplete="off"/>';
        echo '<label class="btn btn-outline-primary" for="recent-state-failed">'
            . _('Failed')
            . '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $this->render(12, 'recent-tasks-table');
    }
    /**
     * Renders the task log pane.
     *
     * taskLog has existed since 1.2 and nothing has ever shown it. Every task
     * state transition wrote a row and the only way to read one was SQL --
     * which stopped being merely untidy at schema 338, when FOS reports
     * started landing in the same table: the text a machine sends when a
     * deploy dies was being stored and shown to nobody.
     *
     * Defaults to reports rather than everything. State rows are one per
     * transition per task, so they outnumber reports by roughly five to one
     * and would bury them on the tab that exists to surface them; 'All' is
     * one click away.
     *
     * @return void
     */
    private function _logsPane()
    {
        $this->headerData = [
            _('Time'),
            _('Host Name'),
            _('Task Type'),
            _('State'),
            _('Type'),
            _('Message'),
            _('Recorded By')
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
        echo '<!-- Task Logs -->';
        echo '<div class="row mb-3">';
        echo '<div class="col-sm-6">';
        echo '<label class="form-label d-block">' . _('Entry Type') . '</label>';
        echo '<div class="btn-group" role="group" aria-label="'
            . _('Log entry type filter')
            . '">';
        $types = [
            'reports' => _('Reports'),
            'error' => _('Errors'),
            'warning' => _('Warnings'),
            'state' => _('State changes'),
            'all' => _('All')
        ];
        foreach ($types as $value => $label) {
            echo '<input type="radio" class="btn-check" name="log-type-filter"'
                . ' id="log-type-' . $value . '" value="' . $value . '"'
                . ' autocomplete="off"'
                . ($value == 'reports' ? ' checked' : '')
                . '/>';
            echo '<label class="btn btn-outline-primary" for="log-type-'
                . $value
                . '">'
                . $label
                . '</label>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $this->render(12, 'task-logs-table');
        // Clicking a row opens this. The message column is the one that gets
        // truncated on a narrow viewport, and it is also the only column
        // whose whole point is its text -- a FOS report carries the script it
        // came from and the arguments it was passed. The modal is filled
        // client side from the row the grid already has, so opening it costs
        // no request.
        //
        // Dismiss only: nothing here is editable, so there is no commit
        // button for it to sit to the left of, and it takes the outline
        // secondary a modal dismiss always takes.
        echo self::makeModal(
            'task-log-modal',
            '<h4 class="card-title">'
            . _('Log entry')
            . '</h4>',
            '<dl class="row mb-0" id="task-log-detail"></dl>',
            self::makeButton(
                'task-log-close',
                _('Close'),
                'btn btn-outline-secondary float-start',
                'data-bs-dismiss="modal"'
            ),
            '',
            'default',
            'modal-lg'
        );
    }
    /**
     * The derived table every task-log query reads from.
     *
     * The row's own copy of the host and task type wins; the joins
     * through `tasks` are the fallback for rows written before schema
     * 341.
     *
     * State rows used to be part of that fallback -- 341's backfill excluded
     * them explicitly, so they never carried a copy at all. They do now:
     * TaskLog::recordState() writes one on every transition, and schema 373
     * backfilled the existing rows whose task is still there. What is left in
     * the fallback is the historical rows whose task survives, and what no
     * query can answer is a pre-373 state row whose task is already gone --
     * the grid renders those with a placeholder.
     *
     * That order is deliberate and not just a null-check: a report is a
     * historical record, so the name the host had WHEN IT FAILED is the
     * answer, not the name it has been renamed to since.
     *
     * The state a row records needs none of this -- taskLog stores
     * taskStateID itself, so that join survives its task.
     *
     * `logHostID` still comes from the `hosts` join, because the grid
     * links the name with it and a link to a deleted host is worse than
     * no link. Resolving the join through the STORED id first is what
     * keeps that link working once the task is gone but the host is not.
     *
     * `reportType` resolves the stored type name back to its icon;
     * taskTypes.ttName is UNIQUE, so it matches at most one row.
     *
     * Its own method so tests/tasklog-report-retention.test.php can run the
     * real statement rather than a copy of it that drifts.
     *
     * @return string a FROM clause with one %s for the derived table alias
     */
    private static function _logQueryFrom()
    {
        return "FROM (
            SELECT `taskLog`.`id` AS `id`,
                `taskLog`.`createTime` AS `logTime`,
                `taskLog`.`createdBy` AS `logBy`,
                `taskLog`.`logType` AS `logType`,
                `taskLog`.`logText` AS `logText`,
                `taskLog`.`taskID` AS `logTaskID`,
                `taskStates`.`tsName` AS `logStateName`,
                `taskStates`.`tsIcon` AS `logStateIcon`,
                COALESCE(
                    NULLIF(`taskLog`.`logTaskTypeName`, ''),
                    `taskTypes`.`ttName`
                ) AS `logTypeName`,
                COALESCE(
                    `reportType`.`ttIcon`,
                    `taskTypes`.`ttIcon`
                ) AS `logTypeIcon`,
                `hosts`.`hostID` AS `logHostID`,
                COALESCE(
                    NULLIF(`taskLog`.`logHostName`, ''),
                    `hosts`.`hostName`
                ) AS `logHostName`
            FROM `taskLog`
            LEFT OUTER JOIN `taskStates`
            ON `taskLog`.`taskStateID` = `taskStates`.`tsID`
            LEFT OUTER JOIN `tasks`
            ON `taskLog`.`taskID` = `tasks`.`taskID`
            LEFT OUTER JOIN `taskTypes`
            ON `tasks`.`taskTypeID` = `taskTypes`.`ttID`
            LEFT OUTER JOIN `taskTypes` AS `reportType`
            ON `reportType`.`ttName` = NULLIF(`taskLog`.`logTaskTypeName`, '')
            LEFT OUTER JOIN `hosts`
            ON `hosts`.`hostID` = COALESCE(
                `taskLog`.`logHostID`,
                `tasks`.`taskHostID`
            )
        ) AS `%s`";
    }
    /**
     * Get the task log entries.
     *
     * Read through a derived table because taskLog and tasks both have
     * `taskID` and `taskStateID` columns, and complex() builds its select
     * list as bare backticked names -- an unqualified `taskID` across that
     * join is ambiguous and the query dies. Aliasing inside the subquery
     * gives every column a name of its own, and MariaDB merges a derived
     * table with no aggregate in it, so this is not a materialisation.
     *
     * @return void
     */
    public function getTaskLogs()
    {
        header('Content-type: application/json');
        parse_str(
            file_get_contents('php://input'),
            $pass_vars
        );

        $reports = [TaskLog::TYPE_ERROR, TaskLog::TYPE_WARNING];
        switch ($pass_vars['logtypes'] ?? '') {
            case 'error':
                $types = [TaskLog::TYPE_ERROR];
                break;
            case 'warning':
                $types = [TaskLog::TYPE_WARNING];
                break;
            case 'state':
                $types = [TaskLog::TYPE_STATE];
                break;
            case 'all':
                $types = [];
                break;
            default:
                $types = $reports;
        }
        $where = '';
        if (count($types) > 0) {
            $where = "`logType` IN ('" . implode("','", $types) . "')";
        }

        $from = self::_logQueryFrom();
        $logsSqlStr = "SELECT `%s` $from %s %s %s";
        $logsFilterStr = "SELECT COUNT(`%s`) $from %s";
        $logsTotalStr = "SELECT COUNT(`%s`) $from";

        $columns = [
            ['db' => 'id', 'dt' => 'id'],
            ['db' => 'logTime', 'dt' => 'logtime'],
            ['db' => 'logHostName', 'dt' => 'hostname'],
            ['db' => 'logHostID', 'dt' => 'hostid'],
            ['db' => 'logTypeName', 'dt' => 'tasktypename'],
            ['db' => 'logTypeIcon', 'dt' => 'tasktypeicon'],
            ['db' => 'logStateName', 'dt' => 'taskstatename'],
            ['db' => 'logStateIcon', 'dt' => 'taskstateicon'],
            ['db' => 'logType', 'dt' => 'logtype'],
            ['db' => 'logText', 'dt' => 'logtext'],
            ['db' => 'logBy', 'dt' => 'createdBy'],
            ['db' => 'logTaskID', 'dt' => 'taskid']
        ];
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            FOGManagerController::complex(
                $pass_vars,
                'taskLogView',
                'id',
                $columns,
                $logsSqlStr,
                $logsFilterStr,
                $logsTotalStr,
                $where
            )
        ));
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
     * Get recently completed/canceled tasks.
     *
     * The Recent tab posts two extra filter vars alongside the
     * DataTables request: states (both|complete|canceled) and
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
        $canceled = (int)self::getCancelledState();
        // Failed has to be here or it is in no pane at all: it is not an
        // active state, so the active pane excludes it by construction, and
        // this is the only view of finished tasks there is.
        $failed = (int)self::getFailedState();
        switch ($pass_vars['states'] ?? '') {
            case 'complete':
                $states = [$complete];
                break;
            case 'cancelled':
                $states = [$canceled];
                break;
            case 'failed':
                $states = [$failed];
                break;
            default:
                $states = [$complete, $canceled, $failed];
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
        foreach ((new TaskManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        foreach ((new HostManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'host' . $common
            ];
            unset($real);
        }
        foreach ((new ImageManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'image' . $common
            ];
            unset($real);
        }
        foreach ((new TaskTypeManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'tasktype' . $common
            ];
            unset($real);
        }
        foreach ((new TaskStateManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'taskstate' . $common
            ];
            unset($real);
        }
        foreach ((new StorageNodeManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'storagenode' . $common
            ];
            unset($real);
        }
        foreach ((new UserManager())
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
        foreach ((new MulticastSessionManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => $common
            ];
            unset($real);
        }
        foreach ((new TaskTypeManager())
            ->getColumns() as $common => &$real
        ) {
            $columns[] = [
                'db' => $real,
                'dt' => 'tasktype'.$common
            ];
            unset($real);
        }
        foreach ((new TaskStateManager())
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
     * For canceling/forcing tasks.
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
                (new TaskManager())->cancel($tasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks canceled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (\Exception $e) {
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
                (new TaskManager())->cancel($tasks);
                (new MulticastSessionManager())->cancel($mtasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks canceled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (\Exception $e) {
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
                (new SnapinTaskManager())->cancel($tasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks canceled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (\Exception $e) {
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
                (new ScheduledTaskManager())->cancel($tasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'TASK_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks canceled!'),
                    'title' => _('Task Cancel Success')
                ]
            );
        } catch (\Exception $e) {
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
                (new FileDeleteQueueManager())->cancel($tasks);
            }
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'QUEUED_DELETION_CANCEL_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Selected tasks canceled!'),
                    'title' => _('Queue Deletion Cancel Success')
                ]
            );
        } catch (\Exception $e) {
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
