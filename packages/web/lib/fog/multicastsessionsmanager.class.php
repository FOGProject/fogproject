<?php
/**
 * Multicast session manager mass management class.
 *
 * PHP version 5
 *
 * @category MulticastSessionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Multicast session manager mass management class.
 *
 * @category MulticastSessionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSessionsManager extends FOGManagerController
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
        $findWhere = array(
            'id' => (array) $multicastsessionids,
        );
        /**
         * Get the current id for cancelled state.
         */
        $cancelled = $this->getCancelledState();
        /**
         * Get sessions's associated task IDs (if any)
         */
        $taskIDs = self::getSubObjectIDs(
            'MulticastSessionsAssociations',
            array('msID' => $this->get('id')),
            'taskID'
        );
        /**
         * Set tasks to cancelled as the main session was cancelled.
         */
        self::getClass('TaskManager')
            ->update(
                array('id' => $taskIDs),
                '',
                array(
                    'stateID' => $this->getCancelledState()
                )
            );
        /*
         * Set our cancelled state
         */
        $this->update(
            $findWhere,
            '',
            array(
                'stateID' => $cancelled,
                'name' => '',
            )
        );
        /*
         * Perform change for alternative data
         */
        $this->arrayChangeKey(
            $findWhere,
            'id',
            'msID'
        );
        /*
         * Remove the other entries
         */
        self::getClass('MulticastSessionsAssociationManager')
            ->destroy($findWhere);
    }
}
