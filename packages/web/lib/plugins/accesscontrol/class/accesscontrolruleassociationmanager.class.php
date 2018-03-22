<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlRuleAssociationManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlRuleAssociationManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlRuleAssociationManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'roleRuleAssoc';
    /**
     * Installs the database for the plugin.
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
                'rraID',
                'rraName',
                'rraRoleID',
                'rraRuleID'
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
                ['rraRoleID', 'rraRuleID']
            ],
            'MyISAM',
            'utf8',
            'rraID',
            'rraID'
        );
        if (self::$DB->query($sql)) {
            $sql = "CREATE UNIQUE INDEX `indexmul` "
                . "ON `roleRuleAssoc` (`rraRoleID`, `rraRuleID`)";
            return self::$DB->query($sql);
        }
        return false;
    }
}
