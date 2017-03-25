<?php
/**
 * If a node fails with a host we write the information
 * into the db using this script.
 *
 * PHP version 5
 *
 * @category Blame
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * If a node fails with a host we write the information
 * into the db using this script.
 *
 * @category Blame
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Blame extends TaskingElement
{
    /**
     * Initializes the blame class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $taskStorageNodeID = $this->Task->get('storagenodeID');
        $taskStorageGroupID = $this->Task->get('storagegroupID');
        $failtime = self::niceDate('+5 minutes')
            ->format('Y-m-d H:i:s');
        foreach ((array)$this->StorageNodes as &$StorageNode) {
            if ($taskStorageID < 1
                || in_array($taskStorageID, self::getAllBlamedNodes($this->Host))
            ) {
                $this
                    ->Task
                    ->set('stateID', self::getQueuedState());
                continue;
            }
            self::getClass('NodeFailure')
                ->set('storagegroupID', $taskStorageGroupID)
                ->set('storagenodeID', $taskStorageNodeID)
                ->set('failureTime', $failtime)
                ->set('taskID', $this->Task->get('id'))
                ->set('hostID', $this->Host->get('id'))
                ->save();
            $this->Task
                ->set('stateID', self::getQueuedState());
            unset($StorageNode);
        }
        if ($this->Task->save()) {
            echo '##';
        }
        exit;
    }
}
