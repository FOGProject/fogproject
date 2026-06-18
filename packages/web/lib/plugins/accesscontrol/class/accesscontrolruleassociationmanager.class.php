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
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in
     * AccessControlManager::schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
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
            'InnoDB',
            'utf8',
            'rraID',
            'rraID'
        );
    }
    /**
     * The unique index step for this table (idempotent: a duplicate key
     * name is tolerated by the schema runner). Used in schema().
     *
     * @return string
     */
    public function indexSql()
    {
        return "CREATE UNIQUE INDEX `indexmul` "
            . "ON `roleRuleAssoc` (`rraRoleID`, `rraRuleID`)";
    }
    /**
     * Installs the table non-destructively.
     *
     * @return bool
     */
    public function install()
    {
        if (false === self::$DB->query($this->createSql())) {
            return false;
        }
        self::$DB->query($this->indexSql());
        return true;
    }
}
