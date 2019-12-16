<?php
/**
 * Snapin Task Manager mass management class
 *
 * PHP version 5
 *
 * @category SnapinTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin Task Manager mass management class
 *
 * @category SnapinTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinTaskManager extends FOGManagerController
{
    /**
     * Cancels the passed tasks
     *
     * @param mixed $snapintaskids the ids to cancel
     *
     * @return bool
     */
    public function cancel($snapintaskids)
    {
        /**
         * Setup our finders
         */
        $findWhere = [
            'id' => (array)$snapintaskids
        ];
        /**
         * Get our cancelled state id
         */
        $cancelled = self::getCancelledState();
        /**
         * Get any snapin job IDs
         */
        Route::ids(
            'snapintask',
            $findWhere,
            'jobID'
        );
        $snapinJobIDs = json_decode(Route::getData(), true);
        /**
         * Get our queued/in progress states
         */
        $queuedStates = self::fastmerge(
            (array) self::getQueuedStates(),
            (array) self::getProgressState()
        );
        /**
         * Update our entry to be cancelled
         */
        $this->update(
            $findWhere,
            '',
            [
                'stateID' => $cancelled,
                'complete'=> self::formatTime('', 'Y-m-d H:i:s')
            ]
        );
        $hostTasksToCancel = [];
        /**
         * Iterate our jobID's to find out if
         * the job needs to be cancelled or not
         */
        foreach ((array)$snapinJobIDs as $i => &$jobID) {
            /**
             * Get the snapin task count
             */
            Route::count(
                'snapintask',
                [
                    'jobID' => $jobID,
                    'stateID' => $queuedStates
                ]
            );
            $jobCount = json_decode(Route::getData());
            $jobCount = $jobCount->total;
            /**
             * If we still have tasks start with the next job ID.
             */
            if ($jobCount > 0) {
                unset($snapinJobIDs[$i]);
                continue;
            }
            $Host = self::getClass('snapinjob', $jobID)
                ->get('host');
            $Task = $Host->get('task');
            if (in_array($Task->get('typeID'), TaskType::SNAPINTASKS)) {
                $hostTasksToCancel[] = $Task->get('id');
            }
            unset($jobID, $jobCount);
        }
        /**
         * Only remove snapin jobs if we have any to remove
         */
        if (count($snapinJobIDs ?: []) > 0) {
            self::getClass('SnapinJobManager')
                ->update(
                    ['id' => (array)$snapinJobIDs],
                    '',
                    ['stateID' => $cancelled]
                );
        }
        /**
         * Cancel tasks if they are snapin only tasks
         */
        if (count($hostTasksToCancel ?: [] ) > 0) {
            self::getClass('TaskManager')
                ->update(
                    ['id' => (array)$hostTasksToCancel],
                    '',
                    ['stateID' => $cancelled]
                );
        }
        return true;
    }
}
