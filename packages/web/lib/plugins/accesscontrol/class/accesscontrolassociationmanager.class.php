<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlAssociationManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlAssociationManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'roleUserAssoc';
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
                'ruaID',
                'ruaName',
                'ruaRoleID',
                'ruaUserID'
            ],
            [
                'INTEGER',
                'VARCHAR(60)',
                'INTEGER',
                'INTEGER'
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                false,
                false,
                false,
                false
            ],
            [
                'ruaID',
                'ruaUserID',
            ],
            'InnoDB',
            'utf8',
            'ruaID',
            'ruaID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        } else {
            Route::ids(
                'user',
                ['name' => 'fog']
            );
            $fogUserID = json_decode(
                Route::getData(),
                true
            );
            $fogUserID = array_shift($fogUserID);
            $sql = sprintf(
                "INSERT INTO `%s` VALUES (1, '%s', 1, %d)",
                $this->tablename,
                'Administrator-fog',
                intval($fogUserID[0])
            );
            self::$DB->query($sql);
        }
        return self::getClass('AccessControlRuleManager')->install();
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('AccessControlRuleManager')->uninstall();
        return parent::uninstall();
    }
}
