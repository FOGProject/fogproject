<?php
/**
 * The snapin job handler class.
 *
 * PHP version 5
 *
 * @category SnapinJob
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin job handler class.
 *
 * @category SnapinJob
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinJob extends FOGController
{
    /**
     * The snapin job name.
     *
     * @var string
     */
    protected $databaseTable = 'snapinJobs';
    /**
     * The snapin job fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sjID',
        'hostID' => 'sjHostID',
        'stateID' => 'sjStateID',
        'createdTime' => 'sjCreateTime'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'stateID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'host',
        'state',
        'snapintasks'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Host' => [
            'id',
            'hostID',
            'host'
        ],
        'TaskState' => [
            'id',
            'stateID',
            'state'
        ]
    ];
    /**
     * Load tasks
     *
     * @return void
     */
    protected function loadSnapintasks()
    {
        $snapintasks = self::getSubObjectIDs(
            'SnapinTask',
            ['jobID' => $this->get('id')]
        );
        $this->set('snapintasks', $snapintasks);
    }
    /**
     * Cancel's the current job.
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->getManager()->cancel($this->get('id'));
    }
}
