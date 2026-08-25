<?php
/**
 * Task manager class.
 *
 * PHP version 7.4+
 *
 * @category TaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
        $hostIDs = Route::getIds(
            'task',
            $findWhere,
            'hostID'
        );
        $updateFields = [];
        foreach ($hostIDs as $hostID) {
            $updateFields[] = [
                'token' => self::createSecToken(),
                'tokenlock' => false
            ];
        }
        // Reset token and lock on hosts from task cancel
        self::getClass('HostManager')->update(
            ['id' => $hostIDs],
            '',
            $updateFields
        );
        // Enumerated BEFORE the update, because $findWhere selects on the
        // states being cancelled out of and matches nothing once they are
        // gone. Route::cancel() sends the group arm and the task arm here, so
        // without this a bulk cancel left every one of its tasks reading
        // In-Progress in the log -- the same hole Task::cancel() had.
        $cancelledIDs = Route::getIds(
            'task',
            $findWhere
        );
        $updated = $this->update(
            $findWhere,
            '',
            [
                'stateID' => $cancelled,
                'stateChangedTime' => self::niceDate()->format('Y-m-d H:i:s')
            ]
        );
        if ($updated) {
            // Reloaded after the update so each row records the state the task
            // is now in, not the one it was cancelled out of.
            foreach ((array)$cancelledIDs as $cancelledID) {
                TaskLog::recordState(new Task($cancelledID));
            }
        }
        $findWhere = [
            'hostID' => $hostIDs,
            'stateID' => $notComplete
        ];
        $SnapinJobIDs = Route::getIds(
            'snapinjob',
            $findWhere
        );
        $findWhere = [
            'stateID' => $notComplete,
            'jobID' => $SnapinJobIDs
        ];
        $SnapinTaskIDs = Route::getIds(
            'snapintask',
            $findWhere
        );
        $findWhere = ['taskID' => $taskids];
        $MulticastSessionAssocIDs = Route::getIds(
            'multicastsessionassociation',
            $findWhere
        );
        $MulticastSessionIDs = Route::getIds(
            'multicastsessionassociation',
            $findWhere,
            'msID'
        );
        $findNew = [
            'stateID' => $notComplete,
            'id' => $MulticastSessionIDs
        ];
        $MulticastSessionIDs = Route::getIds(
            'multicastsession',
            $findNew
        );
        if (count($MulticastSessionAssocIDs ?: [])) {
            Route::deletemass(
                'multicastsessionassociation',
                ['id' => $MulticastSessionAssocIDs]
            );
        }
        $StillLeft = Route::getCount(
            'multicastsessionassociation',
            ['msID' => $MulticastSessionIDs]
        );
        if (count($SnapinTaskIDs ?: [])) {
            self::getClass('SnapinTaskManager')->cancel($SnapinTaskIDs);
        }
        if (count($SnapinJobIDs ?: [])) {
            self::getClass('SnapinJobManager')->cancel($SnapinJobIDs);
        }
        if ($StillLeft < 1 && count($MulticastSessionIDs ?: [])) {
            self::getClass('MulticastSessionManager')->cancel($MulticastSessionIDs);
        }
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\TaskManager', 'TaskManager');
