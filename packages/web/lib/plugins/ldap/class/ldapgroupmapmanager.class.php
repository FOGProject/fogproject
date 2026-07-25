<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPGroupMapManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager for the directory-group to role/user-group mappings.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPGroupMapManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPGroupMapManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'LDAPGroupMap';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in
     * LDAPManager::schema().
     *
     * The unique index across all four meaningful columns is what makes
     * the migration below idempotent: re-running it can only INSERT IGNORE
     * over rows that already exist. It also stops the same group being
     * mapped to the same target twice, which would be invisible in the UI
     * and would double nothing but confusion.
     *
     * lgmGroup is VARCHAR(255) rather than LONGTEXT (which is what
     * lsAdminGroup/lsUserGroup used) because it has to be indexable, and
     * one row now holds one group instead of a comma-separated list.
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'lgmID',
                'lgmServerID',
                'lgmGroup',
                'lgmTargetType',
                'lgmTargetID'
            ],
            [
                'INTEGER',
                'INTEGER',
                'VARCHAR(255)',
                "ENUM('role', 'usergroup')",
                'INTEGER'
            ],
            [
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
                "'role'",
                false
            ],
            [
                [
                    'lgmServerID',
                    'lgmGroup',
                    'lgmTargetType',
                    'lgmTargetID'
                ]
            ],
            'InnoDB',
            'utf8',
            'lgmID',
            'lgmID'
        );
    }
    /**
     * Installs the database non-destructively.
     *
     * @return bool
     */
    public function install()
    {
        return self::$DB->query($this->createSql());
    }
    /**
     * Uninstalls plugin.
     *
     * @return void
     */
    public function uninstall()
    {
        return parent::uninstall();
    }
}
