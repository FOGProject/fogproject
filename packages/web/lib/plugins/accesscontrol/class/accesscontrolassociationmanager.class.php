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
    public function createSql()
    {
        return Schema::createTable(
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
    }
    /**
     * Seeds the default Administrator-to-fog-user association. The row has
     * an explicit primary key, so a re-run is idempotent (the duplicate
     * entry is tolerated). The fog user id is resolved at run time, so this
     * is a callable schema() step rather than a static SQL string.
     *
     * @return bool|string True on success, error string on failure.
     */
    public function seedAssoc()
    {
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
        if (false !== self::$DB->query($sql)->error
            && self::$DB->errorCode != 1062
        ) {
            return self::$DB->error;
        }
        return true;
    }
    /**
     * Installs the table + seed non-destructively.
     *
     * @return bool
     */
    public function install()
    {
        if (false === self::$DB->query($this->createSql())) {
            return false;
        }
        return $this->seedAssoc() === true;
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
