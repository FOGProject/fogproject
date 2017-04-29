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
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'dirCleaner';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'dcID',
                'dcPath'
            ),
            array(
                'INTEGER',
                'LONGTEXT'
            ),
            array(
                false,
                false
            ),
            array(
                false,
                false
            ),
            array(
                'dcID',
                'dcPath'
            ),
            'MyISAM',
            'utf8',
            'dcID',
            'dcID'
        );
        return self::$DB->query($sql);
    }
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
        $findWhere = array(
            'id' => (array)$snapintaskids
        );
        /**
         * Get our cancelled state id
         */
        $cancelled = self::getCancelledState();
        /**
         * Get any snapin job IDs
         */
        $snapinJobIDs = self::getSubObjectIDs(
            'SnapinTask',
            $findWhere,
            'jobID'
        );
        /**
         * Update our entry to be cancelled
         */
        $this->update(
            $findWhere,
            '',
            array(
                'stateID' => $cancelled,
                'complete'=> self::formatTime('', 'Y-m-d H:i:s')
            )
        );
        /**
         * Iterate our jobID's to find out if
         * the job needs to be cancelled or not
         */
        foreach ((array)$snapinJobIDs as $i => &$jobID) {
            /**
             * Get the snapin task count
             */
            $jobCount = self::getClass('SnapinTaskManager')
                ->count(
                    array(
                        'jobID' => $jobID,
                        'stateID' => self::fastmerge(
                            (array) self::getQueuedStates(),
                            (array) self::getProgressState()
                        )
                    )
                );
            /**
             * If we still have tasks start with the next job ID.
             */
            if ($jobCount > 0) {
                continue;
            }
            /**
             * If the snapin job has 0 tasks left over cancel the job
             */
            unset($snapinJobIDs[$i], $jobID);
        }
        /**
         * Only remove snapin jobs if we have any to remove
         */
        if (count($snapinJobIDs) > 0) {
            self::getClass('SnapinJobManager')
                ->update(
                    array('id' => (array)$snapinJobIDs),
                    '',
                    array('stateID' => $cancelled)
                );
        }
        return true;
    }
}
