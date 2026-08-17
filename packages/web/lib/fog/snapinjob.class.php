<?php
/**
 * The snapin job handler class.
 *
 * PHP version 7.4+
 *
 * @category SnapinJob
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
        'abortOnFail' => 'sjAbortOnFail',
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
        $find = ['jobID' => $this->get('id')];
        $snapintasks = Route::getIds(
            'snapintask',
            $find
        );
        $this->set('snapintasks', (array)$snapintasks);
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SnapinJob', 'SnapinJob');
