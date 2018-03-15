<?php
/**
 * Task manager class.
 *
 * PHP version 5
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
            [
                'dcID',
                'dcPath'
            ],
            [
                'INTEGER',
                'LONGTEXT'
            ],
            [
                false,
                false
            ],
            [
                false,
                false
            ],
            [
                'dcID',
                'dcPath'
            ],
            'MyISAM',
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
        $findWhere = [
            'id' => (array)$taskids,
            'stateID' => $notComplete
        ];
        $hostIDs = self::getSubObjectIDs(
            'Task',
            $findWhere,
            'hostID'
        );
        $this->update(
            $findWhere,
            '',
            ['stateID' => $cancelled]
        );
        $findWhere = [
            'hostID' => $hostIDs,
            'stateID' => $notComplete
        ];
        $SnapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            $findWhere
        );
        $findWhere = [
            'stateID' => $notComplete,
            'jobID' => $SnapinJobIDs
        ];
        $SnapinTaskIDs = self::getSubObjectIDs(
            'SnapinTask',
            $findWhere
        );
        $findWhere = ['taskID' => $taskids];
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
            [
                'stateID' => $notComplete,
                'id' => $MulticastSessionIDs
            ]
        );
        if (count($MulticastSessionAssocIDs) > 0) {
            self::getClass('MulticastSessionAssociationManager')
                ->destroy(['id' => $MulticastSessionAssocIDs]);
        }
        $StillLeft = self::getClass('MulticastSessionAssociationManager')
            ->count(['msID' => $MulticastSessionIDs]);
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
}
