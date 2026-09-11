<?php
/**
 * Task manager class.
 *
 * PHP version 7.4+
 *
 * @category TaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Task manager class.
 *
 * @category TaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class TaskManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'dirCleaner';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'dcID',
                'dcPath'
            ),
            array(
                'INTEGER',
                'LONGTEXT'
            ),
            array(
                false,
                false
            ),
            array(
                false,
                false
            ),
            array(
                'dcID',
                'dcPath'
            ),
            'InnoDB',
            'utf8',
            'dcID',
            'dcID'
        );
        return self::$DB->query($sql);
    }
    /**
     * Cancels the specified tasks.
     *
     * @param array $taskids The tasks to cancel.
     *
     * @return void
     */
    public function cancel($taskids)
    {
        $cancelled = self::getCancelledState();
        $notComplete = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $findWhere = array(
            'id' => (array)$taskids,
            'stateID' => $notComplete
        );
        $hostIDs = self::getSubObjectIDs(
            'Task',
            $findWhere,
            'hostID'
        );
        $updateFields = [];
        foreach ($hostIDs as $hostID) {
            $updateFields[] = [
                'token' => self::createSecToken(),
                'tokenlock' => false
            ];
        }
        // Reset token and lock on hosts from task cancel
        self::getClass('HostManager')->update(
            ['id' => $hostIDs],
            '',
            $updateFields
        );
        // Enumerated BEFORE the update, because $findWhere selects on the
        // states being cancelled out of and matches nothing once they are
        // gone. Every bulk cancel comes through here, so without this a group
        // cancel left every one of its tasks reading In-Progress in the log --
        // the same hole Task::cancel() had.
        $cancelledIDs = self::getSubObjectIDs(
            'Task',
            $findWhere
        );
        $updated = $this->update(
            $findWhere,
            '',
            array(
                'stateID' => $cancelled
            )
        );
        if ($updated) {
            // Read back from `tasks` after the update, so each row records the
            // state the task is now in rather than the one it was cancelled
            // out of -- and in one SELECT plus one batched INSERT, because
            // this arm cancels a whole group and rebuilding a Task per id to
            // write its row cost a reload and an INSERT apiece.
            TaskLog::recordStates($cancelledIDs);
        }
        $findWhere = array(
            'hostID' => $hostIDs,
            'stateID' => $notComplete
        );
        $SnapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            $findWhere
        );
        $findWhere = array(
            'stateID' => $notComplete,
            'jobID' => $SnapinJobIDs
        );
        $SnapinTaskIDs = self::getSubObjectIDs(
            'SnapinTask',
            $findWhere
        );
        $findWhere = array(
            'taskID' => $taskids
        );
        $MulticastSessionAssocIDs = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            $findWhere
        );
        $MulticastSessionIDs = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            $findWhere,
            'msID'
        );
        $MulticastSessionIDs = self::getSubObjectIDs(
            'MulticastSession',
            array(
                'stateID' => $notComplete,
                'id' => $MulticastSessionIDs
            )
        );
        if (count($MulticastSessionAssocIDs) > 0) {
            self::getClass('MulticastSessionAssociationManager')
                ->destroy(array('id' => $MulticastSessionAssocIDs));
        }
        $StillLeft = self::getClass('MulticastSessionAssociationManager')
            ->count(array('msID' => $MulticastSessionIDs));
        if (count($SnapinTaskIDs) > 0) {
            self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        }
        if (count($SnapinJobIDs) > 0) {
            self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        }
        if ($StillLeft < 1 && count($MulticastSessionIDs) > 0) {
            self::getClass('MulticastSessionManager')->cancel($MulticastSessionIDs);
        }
    }
    /**
     * Moves active tasks that can never run to Failed, and says why.
     *
     * THE ROWS THIS IS FOR. A task whose taskHostID, taskTypeID or -- for an
     * imaging type -- taskImageID matches no row is permanent and unreadable
     * at the same time. taskStateID still resolves, so every "is this task
     * live" test in the tree is an allowlist of getQueuedStates() plus
     * getProgressState() and counts it as active forever. The Active Tasks
     * list renders each cell from a LEFT OUTER JOIN and guards it with
     * isset(), which is false for the NULL a non-matching join returns, so
     * the row draws with no host, no image and no type. No host can complete
     * it either -- TaskingElement cannot build the Image or the Host, so the
     * machine is turned away at check-in -- and until now nothing reaped it.
     * Reported as "null tasks" in forum topics 18228 and 18230.
     *
     * The write paths that produce them are closed separately (GH-1391); this
     * is for the rows an install is already carrying, which no code change can
     * reach. It runs from the task scheduler, so an install fixes itself
     * without anyone being told to run SQL.
     *
     * RESOLUTION, NOT ZERO. A dangling reference is found by asking whether it
     * joins, which is true of a 0 and of the id of something deleted alike --
     * there is no need to know which, and matching on 0 would be wrong twice
     * over. It would miss every task pointing at a deleted row, and it would
     * sweep up perfectly good tasking: a wipe, snapin, hardware inventory,
     * password reset or Secure Boot task has no image by design and stores
     * taskImageID 0. Confirmed on a live install carrying three such rows --
     * two All Snapins, one Enroll Secure Boot -- every one of them correct. So
     * the image is only asked about for an imaging task type.
     *
     * FAILED, NOT CANCELLED. Cancelled means an administrator stopped it.
     * Losing the difference between "somebody stopped this" and "this broke"
     * is exactly the distinction an operator needs at the moment they are
     * looking at the task list. Same reasoning as TaskState::getFailedState()
     * and TaskError::_markFailed().
     *
     * NOTHING IS UNWOUND, unlike cancel(). A task that cannot resolve its host
     * or its task type cannot have got past check-in -- TaskingElement builds
     * both before anything else -- so there is no token to reissue, no
     * multicast session behind it and no snapin job riding on it. Reaping is a
     * state change plus its record, deliberately.
     *
     * @return array taskID => the reason it was reaped, empty if none were.
     */
    public function reapUnrunnable()
    {
        /*
         * Guarded on the row actually existing rather than assuming schema
         * 281 has run, exactly as TaskError::_markFailed() is. A web tree can
         * be updated ahead of its database -- the ordinary state of an install
         * between the files landing and the admin loading a page -- and this
         * runs from a daemon, which reaches that window before any person
         * does. Pointing a task at a taskStates row that is not there renders
         * blank and cannot be filtered for, which is worse than leaving it
         * where it was for a few minutes.
         */
        $failed = (int)self::getFailedState();
        if (!self::getClass('TaskState', $failed)->isValid()) {
            return array();
        }
        $active = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $active = array_map('intval', $active);
        // Which type ids count as imaging, asked of TaskType rather than
        // written down here, so this and every other reader of "is this an
        // imaging task" cannot drift apart. isDeploy(true) already covers
        // multicast (type 8).
        $TaskType = self::getClass('TaskType');
        $imaging = self::fastmerge(
            (array)$TaskType->isDeploy(true),
            (array)$TaskType->isCapture(true)
        );
        $imaging = array_map('intval', array_unique($imaging));
        /*
         * Interpolated rather than bound, as TaskLog::recordStates() does with
         * its task ids: every element has been through (int) above, and a
         * bound IN list needs a placeholder per element.
         *
         * The three joins are the same three the Active Tasks list builds,
         * which is what makes "does this row draw empty" and "does this row
         * get reaped" the same question rather than two that can drift.
         */
        $rows = self::$DB->query(
            "SELECT `tasks`.`taskID` AS `taskID`, "
            . "`tasks`.`taskHostID` AS `hostID`, "
            . "`tasks`.`taskTypeID` AS `typeID`, "
            . "`tasks`.`taskImageID` AS `imageID`, "
            . "`tasks`.`taskCreateBy` AS `createdBy`, "
            . "`hosts`.`hostID` AS `hostFound`, "
            . "COALESCE(`hosts`.`hostName`, '') AS `hostName`, "
            . "`taskTypes`.`ttID` AS `typeFound`, "
            . "COALESCE(`taskTypes`.`ttName`, '') AS `taskTypeName`, "
            . "`images`.`imageID` AS `imageFound` "
            . "FROM `tasks` "
            . "LEFT OUTER JOIN `hosts` "
            . "ON `hosts`.`hostID` = `tasks`.`taskHostID` "
            . "LEFT OUTER JOIN `taskTypes` "
            . "ON `taskTypes`.`ttID` = `tasks`.`taskTypeID` "
            . "LEFT OUTER JOIN `images` "
            . "ON `images`.`imageID` = `tasks`.`taskImageID` "
            . "WHERE `tasks`.`taskStateID` IN (" . implode(',', $active) . ") "
            . "AND ("
            . "`hosts`.`hostID` IS NULL "
            . "OR `taskTypes`.`ttID` IS NULL "
            . "OR ("
            . "`images`.`imageID` IS NULL "
            . "AND `tasks`.`taskTypeID` IN (" . implode(',', $imaging) . ")"
            . ")"
            . ")"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (count((array)$rows) < 1) {
            return array();
        }

        $reasons = array();
        $logRows = array();
        $now = self::niceDate()->format('Y-m-d H:i:s');
        foreach ((array)$rows as $row) {
            $why = array();
            if (null === $row['hostFound']) {
                $why[] = sprintf(
                    '%s (%d)',
                    _('host no longer exists'),
                    (int)$row['hostID']
                );
            }
            if (null === $row['typeFound']) {
                $why[] = sprintf(
                    '%s (%d)',
                    _('task type no longer exists'),
                    (int)$row['typeID']
                );
            }
            if (null === $row['imageFound']) {
                $why[] = sprintf(
                    '%s (%d)',
                    _('image no longer exists'),
                    (int)$row['imageID']
                );
            }
            $text = sprintf(
                '%s: %s',
                _('Task can never run and was marked failed'),
                implode(', ', $why)
            );
            $reasons[(int)$row['taskID']] = $text;
            /*
             * `ip` and `type` are set here because a batched INSERT never runs
             * TaskLog's constructor, which is where a singly-written row gets
             * both -- the same note recordStates() carries.
             *
             * TYPE_ERROR, not TYPE_STATE: the reason is the whole value of
             * this row. A state row saying only "Failed" against a task whose
             * host is gone tells an operator nothing they can act on. The
             * state transition itself is recorded separately, below.
             *
             * hostName and taskTypeName ARE filled here, where a state row
             * leaves them empty. This is the case those columns were added
             * for: the host is already gone, so the name cannot be recovered
             * by joining, and it is the only thing that makes the row mean
             * anything to whoever reads it later.
             *
             * stateID is the state the task is being moved TO, so the row
             * reads as the transition it accompanies.
             */
            $logRows[] = array(
                (int)$row['taskID'],
                $failed,
                (string)self::$remoteaddr,
                $now,
                (string)$row['createdBy'],
                TaskLog::TYPE_ERROR,
                $text,
                (int)($row['hostFound'] ?: 0),
                (string)$row['hostName'],
                (string)$row['taskTypeName']
            );
        }

        $updated = $this->update(
            array('id' => array_keys($reasons)),
            '',
            array('stateID' => $failed)
        );
        if (!$updated) {
            return array();
        }
        // The state row, so every reader of the log sees the transition like
        // any other one. Read back from `tasks` after the update, as cancel()
        // does, so what is recorded is what the row says now.
        TaskLog::recordStates(array_keys($reasons));
        /*
         * Caught, because the reap has already happened. insertBatch() THROWS
         * where save() returns false, and losing the reason is not worth
         * turning a daemon pass into an exception once the tasks are already
         * moved -- the same call recordStates() makes for the same reason.
         */
        try {
            self::getClass('TaskLogManager')->insertBatch(
                array(
                    'taskID',
                    'stateID',
                    'ip',
                    'createdTime',
                    'createdBy',
                    'type',
                    'text',
                    'hostID',
                    'hostName',
                    'taskTypeName'
                ),
                $logRows
            );
        } catch (Exception $e) {
            self::logFault(
                sprintf(
                    '%s: %s: %s',
                    _('Failed to record why tasks were reaped'),
                    _('Error'),
                    $e->getMessage()
                )
            );
        }

        return $reasons;
    }
}
