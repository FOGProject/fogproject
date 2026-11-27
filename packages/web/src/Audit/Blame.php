<?php
/**
 * If a node fails with a host we write the information
 * into the db using this script.
 *
 * PHP version 7.4+
 *
 * @category Blame
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Audit;

use FOG\Items\NodeFailure;
use FOG\TaskHandling\BootToken;
use FOG\TaskHandling\TaskingElement;

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
        // A node failure is recorded per host, and a hostless task has none:
        // the node is not blamed, the task still goes back in the queue.
        if (!$this->Task->isHostless()
            && $taskStorageNodeID > 0
            && !in_array(
                $taskStorageNodeID,
                self::getAllBlamedNodes(self::$Host)
            )
        ) {
            (new NodeFailure())
                ->set('storagegroupID', $taskStorageGroupID)
                ->set('storagenodeID', $taskStorageNodeID)
                ->set('failureTime', $failtime)
                ->set('taskID', $this->Task->get('id'))
                ->set('hostID', self::$Host->get('id'))
                ->save();
        }
        // FOS could not read the image from the node it was sent to. The
        // node is blamed above and the task goes back in the queue for a
        // different one -- a state transition nobody asked for and, until
        // now, nothing recorded outside the nodeFailure row.
        Audit::record([
            'type' => 'task.blamed',
            'authSource' => Audit::SOURCE_ANONYMOUS,
            'subjectType' => 'task',
            'subjectID' => (int)$this->Task->get('id'),
            'subjectLabel' => BootToken::label($this->Task, self::$Host),
            'renderable' => 1
        ]);
        $this->Task->set('stateID', self::getQueuedState());
        if ($this->Task->save()) {
            echo '##';
        }
        exit;
    }
}
