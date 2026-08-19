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
        'text' => 'logText'
    ];
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
