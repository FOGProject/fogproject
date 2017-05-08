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
    public $tablename = 'images';
    /**
     * Install our table.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $compress = sprintf(
            "ENUM('%s')",
            implode("','", range(0, 9))
        );
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'imageID',
                'imageName',
                'imageDesc',
                'imagePath',
                'imageProtect',
                'imageMagnetUri',
                'imageDateTime',
                'imageCreateBy',
                'imageBuilding',
                'imageSize',
                'imageTypeID',
                'imagePartitionTypeID',
                'imageOSID',
                'imageFormat',
                'imageLastDeploy',
                'imageCompress',
                'imageEnabled',
                'imageReplicate'
            ),
            array(
                'INTEGER',
                'VARCHAR(40)',
                'LONGTEXT',
                'LONGTEXT',
                "ENUM('0', '1')",
                'LONGTEXT',
                'TIMESTAMP',
                'VARCHAR(40)',
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'VARCHAR(2)',
                'DATETIME',
                $compress,
                "ENUM('0', '1')",
                "ENUM('0', '1')"
            ),
            array(
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
            ),
            array(
                false,
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
                false,
                false,
                false,
                false,
                false
            ),
            array(
                'imageID',
                array(
                    'imageName',
                    'imageTypeID'
                )
            ),
            'MyISAM',
            'utf8',
            'imageID',
            'imageID'
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
            'MulticastSession',
            $msFindWhere
        );
        /**
         * Cancel any mc tasks using the destroyed images
         */
        if (count($mcTaskIDs)) {
            self::getClass('MulticastSessionManager')
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
