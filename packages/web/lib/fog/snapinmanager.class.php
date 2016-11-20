<?php
/**
 * Snapin manager mass management class.
 *
 * PHP version 5
 *
 * @category SnapinManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin manager mass management class.
 *
 * @category SnapinManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinManager extends FOGManagerController
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
     * Removes fields.
     *
     * Customized for hosts
     *
     * @param array  $findWhere     What to search for
     * @param string $whereOperator Join multiple where fields
     * @param string $orderBy       Order returned fields by
     * @param string $sort          How to sort, ascending, descending
     * @param string $compare       How to compare fields
     * @param mixed  $groupBy       How to group fields
     * @param mixed  $not           Comparator but use not instead
     *
     * @return parent::destroy
     */
    public function destroy(
        $findWhere = array(),
        $whereOperator = 'AND',
        $orderBy = 'name',
        $sort = 'ASC',
        $compare = '=',
        $groupBy = false,
        $not = false
    ) {
        /*
         * Destroy the base snapins
         */
        parent::destroy(
            $findWhere,
            $whereOperator,
            $orderBy,
            $sort,
            $compare,
            $groupBy,
            $not
        );
        /*
         * Get our other finding portions where/when necessary
         */
        if (isset($findWhere['id'])) {
            $findWhere = array('snapinID' => $findWhere['id']);
        }
        /**
         * Get any snapin jobs with these snapins.
         */
        $snapJobIDs = self::getSubObjectIDs(
            'SnapinTask',
            $findWhere,
            'jobID'
        );
        /**
         * Get any snapin tasks with these snapins.
         */
        $snapTasks = self::getSubObjectIDs(
            'SnapinTask',
            $findWhere
        );
        /*
         * Cancel any tasks with these snapins
         */
        self::getClass('SnapinTaskManager')
            ->cancel($findWhere['snapinID']);
        /*
         * Iterate our jobID's to find out if
         * the job needs to be cancelled or not
         */
        foreach ((array) $snapJobIDs as $i => &$jobID) {
            /**
             * Get the snapin task count.
             */
            $jobCount = self::getClass('SnapinTaskManager')
                ->count(array('jobID' => $jobID));
            /*
             * If we still have tasks start with the next job ID.
             */
            if ($jobCount > 0) {
                continue;
            }
            /*
             * If the snapin job has 0 tasks left over cancel the job
             */
            unset($snapJobIDs[$i], $jobID);
        }
        /**
         * Filter our snapJobIDs.
         */
        $snapJobIDs = array_filter((array) $snapJobIDs);
        /*
         * Only remove snapin jobs if we have any to remove
         */
        if (count($snapJobIDs) > 0) {
            self::getClass('SnapinJobManager')
                ->cancel(array('id' => $snapJobIDs));
        }
        /*
         * Remove the storage group associations for these snapins
         */
        return self::getClass('SnapinAssociationManager')
            ->destroy($findWhere);
    }
}
