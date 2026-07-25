<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPUserGrantManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager for the ldapUserGrant table.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPUserGrantManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPUserGrantManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'ldapUserGrant';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run; used as a step in
     * LDAPManager::schema().
     *
     * The unique index across user, kind and target is what lets the sync
     * rewrite a user's grants with plain INSERT IGNORE after clearing them,
     * and stops a double sign in recording the same grant twice.
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'lugID',
                'lugName',
                'lugUserID',
                'lugTargetType',
                'lugTargetID'
            ],
            [
                'INTEGER',
                'VARCHAR(60)',
                'INTEGER',
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
                "''",
                false,
                "'role'",
                false
            ],
            [
                [
                    'lugUserID',
                    'lugTargetType',
                    'lugTargetID'
                ]
            ],
            'InnoDB',
            'utf8',
            'lugID',
            'lugID'
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
