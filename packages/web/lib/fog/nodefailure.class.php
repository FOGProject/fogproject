<?php
/**
 * The node failure class.
 *
 * PHP version 5
 *
 * @category NodeFailure
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The node failure class.
 *
 * @category NodeFailure
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NodeFailure extends FOGController
{
    /**
     * The load query template custom for this class.
     *
     * @var string
     */
    protected $loadQueryTemplate = "SELECT * FROM `%s` WHERE `%s`='%s' AND TIMESTAMP(`nfDateTime`) BETWEEN TIMESTAMP(DATE_ADD(NOW(), INTERVAL -5 MINUTE)) AND TIMESTAMP(NOW())";
    /**
     * The node failure table.
     *
     * @var string
     */
    protected $databaseTable = 'nfsFailures';
    /**
     * The node failure fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'nfID',
        'storagenodeID' => 'nfNodeID',
        'taskID' => 'nfTaskID',
        'hostID' => 'nfHostID',
        'storagegroupID' => 'nfGroupID',
        'failureTime' => 'nfDateTime'
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'storagenodeID',
        'taskID',
        'hostID',
        'storagegroupID',
        'failureTime'
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'storagenode',
        'storagegroup',
        'host',
        'task'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'StorageNode' => array(
            'id',
            'storagenodeID',
            'storagenode'
        ),
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup'
        ),
        'Host' => array(
            'id',
            'hostID',
            'host'
        ),
        'Task' => array(
            'id',
            'taskID',
            'task'
        )
    );
    /**
     * Returns storage node object.
     *
     * @return object
     */
    public function getStorageNode()
    {
        return $this->get('storagenode');
    }
    /**
     * Returns task object.
     *
     * @return object
     */
    public function getTask()
    {
        return $this->get('task');
    }
    /**
     * Returns host object.
     *
     * @return object
     */
    public function getHost()
    {
        return $this->get('host');
    }
    /**
     * Returns the storage group object.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
}
