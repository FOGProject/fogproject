<?php
/**
 * Snapin Task Manager mass management class
 *
 * PHP version 7.4+
 *
 * @category SnapinTaskManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Items\TaskType;
use FOG\Router\Route;

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
         * Get our canceled state id
         */
        $canceled = self::getCancelledState();
        /**
         * Get any snapin job IDs
         */
        $snapinJobIDs = Route::getIds(
            'snapintask',
            $findWhere,
            'jobID'
        );
        /**
         * Get our queued/in progress states
         */
        $queuedStates = self::fastmerge(
            (array) self::getQueuedStates(),
            (array) self::getProgressState()
        );
        /**
         * Update our entry to be canceled
         */
        $this->update(
            $findWhere,
            '',
            [
                'stateID' => $canceled,
                'complete'=> self::storageNow()
            ]
        );
        $hostTasksToCancel = [];
        /**
         * Iterate our jobID's to find out if
         * the job needs to be canceled or not
         */
        foreach ((array)$snapinJobIDs as $i => &$jobID) {
            /**
             * Get the snapin task count
             */
            $jobCount = Route::getCount(
                'snapintask',
                [
                    'jobID' => $jobID,
                    'stateID' => $queuedStates
                ]
            );
            /**
             * If we still have tasks start with the next job ID.
             */
            if ($jobCount > 0) {
                unset($snapinJobIDs[$i]);
                continue;
            }
            $Host = self::getClass('SnapinJob', $jobID)
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
                    ['stateID' => $canceled]
                );
        }
        /**
         * Cancel tasks if they are snapin only tasks
         */
        if (count($hostTasksToCancel ?: []) > 0) {
            self::getClass('TaskManager')
                ->update(
                    ['id' => (array)$hostTasksToCancel],
                    '',
                    [
                        'stateID' => $canceled,
                        'stateChangedTime' => self::niceDate()
                            ->format('Y-m-d H:i:s')
                    ]
                );
        }
        return true;
    }
}
