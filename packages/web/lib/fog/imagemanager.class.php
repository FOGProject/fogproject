<?php
/**
 * Image manager mass management class
 *
 * PHP version 5
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Image manager mass management class
 *
 * @category ImageManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageManager extends FOGManagerController
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
     * @param mixed  $not           Comparator but use not instead.
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
        /**
         * Destroy the main images
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
        /**
         * Get our other associative areas as needed
         */
        if (isset($findWhere['id'])) {
            $findWhere = array('imageID' => $findWhere['id']);
            $msFindWhere = array('image' => $findWhere['id']);
        }
        /**
         * Get running task ID's using these images
         */
        $taskIDs = self::getSubObjectIDs(
            'Task',
            $findWhere
        );
        /**
         * Get running multicast tasks using these images
         */
        $mcTaskIDs = self::getSubObjectIDs(
            'MulticastSessions',
            $msFindWhere
        );
        /**
         * Cancel any mc tasks using the destroyed images
         */
        if (count($mcTaskIDs)) {
            self::getClass('MulticastSessionsManager')
                ->cancel($mcTaskIDs);
        }
        /**
         * Cancel any tasks using the destroyed images
         */
        if (count($taskIDs)) {
            self::getClass('TaskManager')
                ->cancel($taskIDs);
        }
        /**
         * Remove the storage group associations with these
         * images.
         */
        return self::getClass('ImageAssociationManager')
            ->destroy($findWhere);
    }
}
