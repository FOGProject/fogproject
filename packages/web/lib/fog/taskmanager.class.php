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
        Route::ids(
            'task',
            $findWhere,
            'hostID'
        );
        $hostIDs = json_decode(Route::getData(), true);
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
        $this->update(
            $findWhere,
            '',
            ['stateID' => $cancelled]
        );
        $findWhere = [
            'hostID' => $hostIDs,
            'stateID' => $notComplete
        ];
        Route::ids(
            'snapinjob',
            $findWhere
        );
        $SnapinJobIDs = json_decode(Route::getData(), true);
        $findWhere = [
            'stateID' => $notComplete,
            'jobID' => $SnapinJobIDs
        ];
        Route::ids(
            'snapintask',
            $findWhere
        );
        $SnapinTaskIDs = json_decode(Route::getData(), true);
        $findWhere = ['taskID' => $taskids];
        Route::ids(
            'multicastsessionassociation',
            $findWhere
        );
        $MulticastSessionAssocIDs = json_decode(Route::getData(), true);
        Route::ids(
            'multicastsessionassociation',
            $findWhere,
            'msID'
        );
        $MulticastSessionIDs = json_decode(Route::getData(), true);
        $findNew = [
            'stateID' => $notComplete,
            'id' => $MulticastSessionIDs
        ];
        Route::ids(
            'multicastsession',
            $findNew
        );
        $MulticastSessionIDs = json_decode(Route::getData(), true);
        if (count($MulticastSessionAssocIDs ?: [])) {
            Route::deletemass(
                'multicastsessionassociation',
                ['id' => $MulticastSessionAssocIDs]
            );
        }
        Route::count(
            'multicastsessionassociation',
            ['msID' => $MulticastSessionIDs]
        );
        $StillLeft = json_decode(Route::getData());
        $StillLeft = $StillLeft->total;
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
