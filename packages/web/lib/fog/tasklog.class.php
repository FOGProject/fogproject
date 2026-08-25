<?php
/**
 * Task log class.
 *
 * PHP version 7.4+
 *
 * @category TaskLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Task log class.
 *
 * @category TaskLog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskLog extends FOGController
{
    /**
     * The task log table.
     *
     * @var string
     */
    protected $databaseTable = 'taskLog';
    /**
     * The task log fields and common names.
     *
     * hostID/hostName/taskTypeName are a copy of who the report was about,
     * kept because the row outlives what it points at. taskLog reaches host
     * and task type through `tasks`; nothing deletes taskLog rows, but
     * Route::deletemass('host') cascades to `task` and taskLog is in no
     * cascade -- so deleting a host destroys its tasks and leaves the
     * reports with nothing to join to, losing the host name at the same
     * moment the host row that could supply it goes. Host name is the first
     * thing anyone searches a failure by, so schema 341 stores it here.
     *
     * imageName joined them under ADR 0022 decision 3, when imagingLog was
     * retired and this row became the whole record of an imaging run. It is
     * only set for an imaging task; every other task type leaves it empty,
     * and the dashboard chart counts rows where it is not.
     *
     * All four are written on every transition, not just on the FOS report
     * rows -- schema 341 backfilled only the report rows, which left
     * capture-versus-deploy absent from exactly the rows a per-event count
     * reads. See TaskingElement::taskLog().
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'id',
        'taskID' => 'taskID',
        'stateID' => 'taskStateID',
        'ip' => 'ip',
        'createdTime' => 'createTime',
        'createdBy' => 'createdBy',
        'type' => 'logType',
        'text' => 'logText',
        'hostID' => 'logHostID',
        'hostName' => 'logHostName',
        'taskTypeName' => 'logTaskTypeName',
        'imageName' => 'logImageName'
    ];
    /**
     * The grid list query, with the host joined in.
     *
     * The default template carries no join, so a grid can only ORDER BY the
     * listed table's own columns. That is fine for everything this class
     * shows except one thing: the group page's Task History tab groups its
     * rows by host, and RowGroup only groups correctly when the grouped
     * column is also the primary sort -- so the group order is whatever the
     * sort key is. Sorting on `logHostID` puts the hosts in id order, which
     * is the order they were created in and means nothing to a reader.
     *
     * `logHostName` cannot stand in for the join. It is a denormalized copy
     * taken at write time, so a host renamed midway through its history has
     * two different values against one id, and ordering on it would split
     * that host into two runs with the group header repeated. The joined
     * name is a function of the id alone, which is what grouping needs.
     *
     * LEFT OUTER so a row whose host has since been deleted is still listed
     * -- keeping those rows readable is why the name is denormalized at all.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `taskLog`.`logHostID` = `hosts`.`hostID`
        %s
        %s
        %s";
    /**
     * The sql filter string, carrying the same join as the query.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `taskLog`.`logHostID` = `hosts`.`hostID`
        %s";
    /**
     * The sql total string, carrying the same join as the query.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `taskLog`.`logHostID` = `hosts`.`hostID`";
    /**
     * A row recording a state transition, which is what every row was
     * before schema 338.
     *
     * @var string
     */
    const TYPE_STATE = 'state';
    /**
     * A row recording that something went wrong and the task stopped.
     *
     * @var string
     */
    const TYPE_ERROR = 'error';
    /**
     * A row recording that something went wrong and the task carried on.
     *
     * @var string
     */
    const TYPE_WARNING = 'warning';
    /**
     * Initializes the class to set the ip from the remote.
     *
     * Also types the row, because the column default cannot. Schema 338 gave
     * `logType` a DEFAULT of 'state', and a default only applies when the
     * column is left out of the INSERT -- which FOGController::save() never
     * does: it writes every declared field, so an unset one arrives as ''.
     * So TaskingElement::taskLog(), which has recorded task state changes
     * since long before this column existed and sets no type, started writing
     * untyped rows the moment the field was declared. Proven on a live
     * install 2026-08-19: one row with logType '' against 52 pre-existing
     * rows reading 'state', those 52 being rows the ALTER had backfilled.
     *
     * The consequence is silent: Task Management's log pane filters on
     * `logType IN ('state')`, so every state row written after the upgrade
     * would be missing from the one view built to show them.
     *
     * Guarded rather than assigned, so loading an existing row and saving it
     * cannot retype it as a state change.
     *
     * @param mixed $data the data to initialize with.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        parent::__construct($data);
        $this->set('ip', self::$remoteaddr);
        if ('' === (string) $this->get('type')) {
            $this->set('type', self::TYPE_STATE);
        }
    }
    /**
     * Records one task state transition.
     *
     * The single definition of what a state row looks like. It used to live
     * inline in TaskingElement::taskLog(), which meant only the two callers
     * that go through a TaskingElement -- checkIn() and checkout() -- could
     * write one. Cancellation does not go through either, so a cancelled task
     * left no row at all and the last thing the log said about it was
     * In-Progress, forever.
     *
     * The timestamp is the moment of the TRANSITION. taskLog() used to pass the
     * task's own createdTime, and `createdTime` maps to this row's `createTime`
     * -- one column, not two -- so every row a task ever wrote carried the
     * instant the task was created. In-Progress and Complete came out sharing a
     * timestamp to the second, which is what made the log unreadable: nothing
     * could be ordered, and repeated transitions looked like duplicates.
     *
     * hostName/taskTypeName/imageName are denormalized here for the reason
     * schema 341 gave: tasks are deleted routinely and this row outlives them.
     *
     * @param object $Task the task whose state just changed.
     *
     * @return bool|object false if there is no task to record.
     */
    public static function recordState($Task)
    {
        if (!$Task instanceof Task || !$Task->isValid()) {
            return false;
        }
        $Host = $Task->getHost();
        $hasHost = ($Host && $Host->isValid());
        // Only an imaging task has an image; every other type leaves this empty
        // and the dashboard chart counts rows where it is not.
        $imageName = '';
        if ($Task->isImagingTask()) {
            $Image = $Task->getImage();
            if ($Image && $Image->isValid()) {
                $imageName = $Image->get('name');
            }
        }

        return self::getClass('TaskLog')
            ->set('taskID', $Task->get('id'))
            ->set('taskStateID', $Task->get('stateID'))
            ->set('createdTime', self::niceDate()->format('Y-m-d H:i:s'))
            ->set('createdBy', $Task->get('createdBy'))
            ->set('hostID', ($hasHost ? $Host->get('id') : 0))
            ->set('hostName', ($hasHost ? $Host->get('name') : ''))
            ->set('taskTypeName', $Task->getTaskTypeText())
            ->set('imageName', $imageName)
            ->save();
    }
    /**
     * Gets the task object.
     *
     * @return object
     */
    public function getTask()
    {
        return new Task($this->get('taskID'));
    }
    /**
     * Gets the task state.
     *
     * @return object
     */
    public function getTaskState()
    {
        return new TaskState($this->get('stateID'));
    }
    /**
     * Gets the tasks host.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->getTask()->getHost();
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskLog', 'TaskLog');
