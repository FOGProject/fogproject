<?php
/**
 * Multicast session manager mass management class.
 *
 * PHP version 7.4+
 *
 * @category MulticastSessionManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;
use FOG\Router\Route;

/**
 * Multicast session manager mass management class.
 *
 * @category MulticastSessionManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSessionManager extends FOGManagerController
{
    /**
     * Cancels all passed tasks.
     *
     * @param mixed $multicastsessionids the id's to cancel
     *
     * @return void
     */
    public function cancel($multicastsessionids)
    {
        /**
         * Setup for our finding needs.
         */
        $findWhere = ['id' => (array)$multicastsessionids];
        /**
         * Get the current id for canceled state.
         */
        $cancelled = self::getCancelledState();
        /**
         * Get sessions's associated task IDs (if any)
         */
        $taskIDs = Route::getIds(
            'multicastsessionassociation',
            ['msID' => $multicastsessionids],
            'taskID'
        );
        /**
         * Set tasks to canceled as the main session was canceled.
         */
        self::getClass('TaskManager')
            ->update(
                ['id' => $taskIDs],
                '',
                [
                    'stateID' => $cancelled,
                    'checkInTime' => self::niceDate()->format('Y-m-d H:i:s'),
                    'stateChangedTime' => self::niceDate()->format('Y-m-d H:i:s')
                ]
            );
        /*
         * Set our canceled state
         */
        $this->update(
            $findWhere,
            '',
            [
                'stateID' => $cancelled,
                'name' => '',
                'clients' => 0,
                'completetime' => self::niceDate()->format('Y-m-d H:i:s')
            ]
        );
        /*
         * Perform change for alternative data
         */
        self::arrayChangeKey(
            $findWhere,
            'id',
            'msID'
        );
        /*
         * Remove the other entries
         */
        Route::deletemass(
            'multicastsessionassociation',
            $findWhere
        );
    }
}
