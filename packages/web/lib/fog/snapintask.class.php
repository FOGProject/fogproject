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
}
