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
     * Host::destroy() destroys the host's tasks and taskLog is in no cascade
     * -- so deleting a host leaves the reports with nothing to join to,
     * losing the host name at the same moment the host row that could supply
     * it goes. This branch has no Task Management log pane, so the REST API
     * is the only reader, and it could not recover the host at all. Schema
     * 283 stores it here.
     *
     * Only the FOS report endpoint fills them. A state row leaves them
     * empty: it is an annotation on a task and means nothing without it,
     * and TaskingElement::taskLog() runs on every transition.
     *
     * @var array
     */
    protected $databaseFields = array(
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
    );
    /**
     * A row recording a state transition, which is what every row was
     * before schema 280.
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
     * taskID is a foreign key held in a text column.
     *
     * taskLog.taskID is mediumtext -- it has been since the table was added,
     * and it is the only *id column in the 1.5 schema that is not an integer
     * besides inventory's UUID. save() therefore read it as an integer,
     * FILTER_VALIDATE_INT rejected nothing (the values are numeric strings)
     * only as long as the value was clean, and any non-numeric one was
     * written as 0.
     *
     * 1.6 fixed the column instead (schema 336, GH-1156). A column type
     * change is a data migration, and a maintenance branch is the wrong
     * place to run one over a table that can hold every task this server
     * has ever run, so this line does the same job without touching the
     * schema: the value is stored as it is given.
     *
     * @var array
     */
    protected $databaseFieldsNotInt = array(
        'taskID',
    );
    /**
     * Initializes the class to set the ip from the remote.
     *
     * @param mixed $data the data to initialize with.
     *
     * @return void
     */
    public function __construct($data = '')
    {
        parent::__construct($data);
        $this->set('ip', self::$remoteaddr);
        // Types the row, because the column default cannot. Schema 280 gave
        // `logType` a DEFAULT of 'state', and a default only applies when the
        // column is left out of the INSERT -- which FOGController::save()
        // never does: it writes every declared field, so an unset one arrives
        // as ''. So TaskingElement::taskLog(), which has recorded task state
        // changes since long before this column existed and sets no type,
        // started writing untyped rows the moment the field was declared.
        // Found on 1.6 (#1213) against a live install; the code is the same
        // here, so the defect is too.
        //
        // Guarded rather than assigned, so loading an existing row and saving
        // it cannot retype it as a state change.
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
     * The timestamp is the moment of the TRANSITION. taskLog() passed the
     * task's own createdTime, and `createdTime` maps to this row's
     * `createTime` -- one column, not two -- so every row a task ever wrote
     * carried the instant the task was created. In-Progress and Complete came
     * out sharing a timestamp to the second, which is what made the log
     * unreadable: nothing could be ordered and repeated transitions looked
     * like duplicates.
     *
     * hostID/hostName/taskTypeName are deliberately left empty, which is this
     * branch's existing convention for a state row and the reason the
     * databaseFields note above gives: a state row is an annotation on its
     * task and says nothing without it, and this runs on every transition.
     * The reaper's error rows do fill them, because those outlive the host
     * they name -- that is the case the columns were added for.
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

        return self::getClass('TaskLog')
            ->set('taskID', $Task->get('id'))
            ->set('stateID', $Task->get('stateID'))
            ->set('createdTime', self::niceDate()->format('Y-m-d H:i:s'))
            ->set('createdBy', $Task->get('createdBy'))
            ->save();
    }
    /**
     * Records a state transition for many tasks at once.
     *
     * recordState()'s bulk sibling, and it exists because the per-object
     * shape does not survive TaskManager::cancel(). That method cancels every
     * active task in a group in ONE statement; recording the result by
     * rebuilding each Task cost a reload and an INSERT apiece, so a 300-host
     * group turned a two-statement cancel into hundreds of queries inside one
     * request. Same row, same columns, one SELECT and one batched INSERT.
     *
     * Reading `tasks` rather than trusting the ids is what makes this safe to
     * call after the update: the state written is whatever the row says NOW,
     * so a task whose state moved concurrently logs the truth rather than
     * what the caller assumed, and an id that no longer resolves returns no
     * row at all instead of one claiming a transition. That is the same
     * guarantee recordState()'s isValid() gate gives, expressed as a query.
     *
     * @param array $taskIDs ids of the tasks whose state just changed.
     *
     * @return int how many rows were written.
     */
    public static function recordStates($taskIDs)
    {
        $ids = array();
        foreach ((array)$taskIDs as $taskID) {
            $taskID = (int)$taskID;
            if ($taskID > 0) {
                $ids[$taskID] = $taskID;
            }
        }
        if (count($ids) < 1) {
            return 0;
        }
        // Interpolated rather than bound: every element has been through
        // (int) above, and a bound IN list needs a placeholder per element.
        $rows = self::$DB->query(
            "SELECT `taskID`, `taskStateID`, `taskCreateBy` "
            . "FROM `tasks` "
            . "WHERE `taskID` IN (" . implode(',', $ids) . ")"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (count((array)$rows) < 1) {
            return 0;
        }
        // `ip` and `type` as well: recordState() gets both from TaskLog's
        // constructor, which a batched INSERT never runs, and a bulk-cancelled
        // row must not be distinguishable from a singly-cancelled one.
        $fields = array(
            'taskID',
            'stateID',
            'ip',
            'createdTime',
            'createdBy',
            'type',
        );
        $now = self::niceDate()->format('Y-m-d H:i:s');
        $values = array();
        foreach ((array)$rows as $row) {
            $values[] = array(
                (int)$row['taskID'],
                (int)$row['taskStateID'],
                // Cast, because there is no remote address in a daemon.
                // filter_input(INPUT_SERVER, 'REMOTE_ADDR') is null under CLI,
                // `ip` is varchar(15) NOT NULL, and a null bound into a NOT
                // NULL column is error 1048 on a strict server -- which is
                // every server since GH-1245 stopped PDODB clearing sql_mode.
                // TaskManager::cancel() is reached from the multicast manager
                // and from TaskManager::reapUnrunnable(), both daemons.
                (string)self::$remoteaddr,
                $now,
                (string)$row['taskCreateBy'],
                self::TYPE_STATE,
            );
        }
        /*
         * Caught, because the cancel has already happened.
         *
         * insertBatch() THROWS where FOGController::save() returns false, and
         * its callers sit inside a try that answers a request -- so an
         * unhandled failure here would tell a caller whose tasks really were
         * cancelled that the cancel failed, which is the same class of lie
         * this change exists to remove. recordState() cannot do that to its
         * caller and neither should this.
         *
         * Not silent: logFault() is where a failure on a path with nobody
         * signed in gets written, exactly as save()'s own catch does with it.
         */
        try {
            list($insertID, $affected) = self::getClass('TaskLogManager')
                ->insertBatch($fields, $values);
            unset($insertID);
        } catch (Exception $e) {
            self::logFault(
                sprintf(
                    '%s: %s: %s',
                    _('Failed to record task state changes'),
                    _('Error'),
                    $e->getMessage()
                )
            );

            return 0;
        }

        return (int)$affected;
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
