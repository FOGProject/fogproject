<?php
/**
 * The snapin task class.
 *
 * PHP version 7.4+
 *
 * @category SnapinTask
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

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
    protected $databaseFields = [
        'id' => 'stID',
        'jobID' => 'stJobID',
        'stateID' => 'stState',
        'checkin' => 'stCheckinDate',
        'complete' => 'stCompleteDate',
        'snapinID' => 'stSnapinID',
        'sequence' => 'stSequence',
        'return' => 'stReturnCode',
        'details' => 'stReturnDetails',
        'status' => 'stStatus'
    ];
    /**
     * The grid list query, with the host joined in through the job.
     *
     * Same reason as TaskLog's, which carries the full explanation: the
     * group page's Snapin History tab groups by host, RowGroup needs the
     * grouped column to be the primary sort, and without a host name in the
     * query the only thing to sort on is an id.
     *
     * Two hops, because a snapin task holds no host of its own -- it points
     * at the job, and the job points at the host. That is the same route the
     * grid's own hostLink formatter takes; joining it here just makes it
     * available to ORDER BY as well.
     *
     * LEFT OUTER on both so a task whose job or host has since been deleted
     * is still listed.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `snapinJobs`
        ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
        LEFT OUTER JOIN `hosts`
        ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
        %s
        %s
        %s";
    /**
     * The sql filter string, carrying the same joins as the query.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `snapinJobs`
        ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
        LEFT OUTER JOIN `hosts`
        ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`
        %s";
    /**
     * The sql total string, carrying the same joins as the query.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `snapinJobs`
        ON `snapinTasks`.`stJobID` = `snapinJobs`.`sjID`
        LEFT OUTER JOIN `hosts`
        ON `snapinJobs`.`sjHostID` = `hosts`.`hostID`";
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'jobID',
        'snapinID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'snapin',
        'state',
        'snapinjob'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Snapin' => [
            'id',
            'snapinID',
            'snapin'
        ],
        'TaskState' => [
            'id',
            'stateID',
            'state'
        ],
        'SnapinJob' => [
            'id',
            'jobID',
            'snapinjob'
        ]
    ];
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
