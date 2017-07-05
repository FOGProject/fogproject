<?php
/**
 * The snapin task class.
 *
 * PHP version 5
 *
 * @category SnapinTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin task class.
 *
 * @category SnapinTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTask extends FOGController
{
    /**
     * The snapin task table.
     *
     * @var string
     */
    protected $databaseTable = 'snapinTasks';
    /**
     * The snapin task fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'stID',
        'jobID' => 'stJobID',
        'stateID' => 'stState',
        'checkin' => 'stCheckinDate',
        'complete' => 'stCompleteDate',
        'snapinID' => 'stSnapinID',
        'return' => 'stReturnCode',
        'details' => 'stReturnDetails',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'jobID',
        'snapinID',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'snapin',
        'state',
        'snapinjob'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Snapin' => array(
            'id',
            'snapinID',
            'snapin'
        ),
        'TaskState' => array(
            'id',
            'stateID',
            'state'
        ),
        'SnapinJob' => array(
            'id',
            'jobID',
            'snapinjob'
        )
    );
    /**
     * Return the snapin job object.
     *
     * @return object
     */
    public function getSnapinJob()
    {
        return new SnapinJob($this->get('jobID'));
    }
    /**
     * Return the snapin object.
     *
     * @return object
     */
    public function getSnapin()
    {
        return new Snapin($this->get('snapinID'));
    }
    /**
     * Cancels the snapin task.
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->getManager()->cancel($this->get('id'));
    }
    /**
     * Get's the state object.
     *
     * @return object
     */
    public function getState()
    {
        return new TaskState($this->get('stateID'));
    }
}
