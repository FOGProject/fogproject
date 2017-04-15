<?php
/**
 * Multicast session manager mass management class.
 *
 * PHP version 5
 *
 * @category MulticastSessionManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
        $cancelled = self::getCancelledState();
        /**
         * Get sessions's associated task IDs (if any)
         */
        $taskIDs = self::getSubObjectIDs(
            'MulticastSessionAssociation',
            array('msID' => $multicastsessionids),
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
                    'stateID' => self::getCancelledState()
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
        self::arrayChangeKey(
            $findWhere,
            'id',
            'msID'
        );
        /*
         * Remove the other entries
         */
        self::getClass('MulticastSessionAssociationManager')
            ->destroy($findWhere);
    }
}
