<?php
/**
 * File Delete Queue handler class (informative).
 *
 * PHP version 7.4+
 *
 * @category FileDeleteQueueManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * File Delete Queue handler class (informative).
 *
 * @category FileDeleteQueueManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileDeleteQueueManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'fileDeleteQueue';
    /**
     * Cancels the passed tasks
     *
     * @param mixed $filedeletequeueids the ids to cancel
     *
     * @return void
     */
    public function cancel($filedeletequeueids)
    {
        $cancelled = self::getCancelledState();
        $notComplete = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $findWhere = [
            'id' => (array)$filedeletequeueids,
            'stateID' => $notComplete
        ];
        $this->update(
            $findWhere,
            '',
            [
                'completedTime' => self::formatTime('now', 'Y-m-d H:i:s'),
                'stateID' => $cancelled
            ]
        );
    }
    /**
     * Completes the passed tasks
     *
     * @param mixed $filedeletequeueids the ids to complete
     *
     * @return void
     */
    public function complete($filedeletequeueids)
    {
        $completed = self::getCompleteState();
        $notComplete = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $findWhere = [
            'id' => (array)$filedeletequeueids,
            'stateID' => $notComplete,
        ];
        $this->update(
            $findWhere,
            '',
            [
                'completedTime' => self::formatTime('now', 'Y-m-d H:i:s'),
                'stateID' => $completed
            ]
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FileDeleteQueueManager', 'FileDeleteQueueManager');
