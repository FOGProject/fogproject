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
    public $tablename = 'snapins';
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
            [
                'sID',
                'sName',
                'sDesc',
                'sFilePath',
                'sArgs',
                'sCreateDate',
                'sCreator',
                'sReboot',
                'sRunWith',
                'sRunWithArgs',
                'sAnon3',
                'snapinProtect',
                'sEnabled',
                'sReplicate',
                'sShutdown',
                'sHideLog',
                'sTimeout',
                'sPackType',
                'sHash',
                'sSize'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                'LONGTEXT',
                'TIMESTAMP',
                'VARCHAR(255)',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'INTEGER',
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                'INTEGER',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                'BIGINT(20)'
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                false,
                false,
                false,
                false,
                false,
                false,
                '1',
                '1',
                '0',
                '0',
                '0',
                '0',
                false,
                '0'
            ],
            [
                'sID',
                'sName'
            ],
            'MyISAM',
            'utf8',
            'sID',
            'sID'
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
        $findWhere = [],
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
            $findWhere = ['snapinID' => $findWhere['id']];
        }
        /**
         * Get any snapin jobs with these snapins.
         */
        Route::ids(
            'snapintask',
            $findWhere,
            'jobID'
        );
        $snapJobIDs = json_decode(Route::getData(), true);
        /**
         * Get any snapin tasks with these snapins.
         */
        Route::ids(
            'snapintask',
            $findWhere
        );
        $snapTasks = json_decode(Route::getData(), true);
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
            Route::count(
                'snapintask',
                ['jobID' => $jobID]
            );
            $jobCount = json_decode(Route::getData());
            $jobCount = $jobCount->total;
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
                ->cancel(['id' => $snapJobIDs]);
        }
        /*
         * Remove the storage group associations for these snapins
         */
        return self::getClass('SnapinAssociationManager')
            ->destroy($findWhere);
    }
}
