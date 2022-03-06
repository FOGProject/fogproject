<?php
/**
 * Location manager mass management class
 *
 * PHP version 5
 *
 * @category LocationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Location manager mass management class
 *
 * @category LocationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'location';
    /**
     * Install our database
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
                'lID',
                'lName',
                'lDesc',
                'lStorageGroupID',
                'lStorageNodeID',
                'lCreatedBy',
                'lCreatedTime',
                'lTftpEnabled'
            ),
            array(
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'INTEGER',
                'INTEGER',
                'VARCHAR(40)',
                'TIMESTAMP',
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
                false
            ),
            array(
                'lID',
                'lName',
                array(
                    'lStorageGroupID',
                    'lStorageNodeID'
                )
            ),
            'InnoDB',
            'utf8',
            'lID',
            'lID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return self::getClass('LocationAssociationManager')
            ->install();
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        $res = true;
        self::getClass('Service')
            ->set('name', 'FOG_SNAPIN_LOCATION_SEND_ENABLED')
            ->load('name')
            ->destroy();
        self::getClass('LocationAssociationManager')->uninstall();
        return parent::uninstall();
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
        parent::destroy(
            $findWhere,
            $whereOperator,
            $orderBy,
            $sort,
            $compare,
            $groupBy,
            $not
        );
        if (isset($findWhere['id'])) {
            $findWhere = array('locationID' => $findWhere['id']);
        }
        self::getClass('LocationAssociationManager')->destroy($findWhere);
        return true;
    }
}
