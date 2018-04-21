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
