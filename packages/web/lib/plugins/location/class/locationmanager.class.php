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
     * Install our database
     *
     * @param string $name the name of the plugin
     *
     * @return bool
     */
    public function install($name)
    {
        $this->uninstall();
        $sql = "CREATE TABLE `location`
            (`lID` INTEGER NOT NULL AUTO_INCREMENT,
            `lName` VARCHAR(250) NOT NULL,
            `lDesc` longtext NOT NULL,
            `lStorageGroupID` INTEGER NOT NULL,
            `lStorageNodeID` INTEGER NOT NULL,
            `lCreatedBy` VARCHAR(30) NOT NULL,
            `lTftpEnabled` VARCHAR(1) NOT NULL,
            `lCreatedTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(`lID`),
        KEY new_index (`lName`),
        KEY new_index1 (`lStorageGroupID`))
        ENGINE = MyISAM";
        if (!self::$DB->query($sql)) {
            return false;
        }
        $sql = "CREATE TABLE `locationAssoc`
            (`laID` INTEGER NOT NULL AUTO_INCREMENT,
            `laLocationID` INTEGER NOT NULL,
            `laHostID` INTEGER NOT NULL,
            PRIMARY KEY (`laID`),
            KEY new_index (`laHostID`))
            ENGINE=MyISAM";
        return self::$DB->query($sql);
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
        if (!self::$DB->query("DROP TABLE IF EXISTS `locationAssoc`")) {
            $res = false;
        }
        if (!self::$DB->query("DROP TABLE IF EXISTS `location`")) {
            $res = false;
        }
        return $res;
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
