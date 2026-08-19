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
