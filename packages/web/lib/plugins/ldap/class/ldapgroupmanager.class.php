<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPGroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Manager for the LDAPGroups table.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPGroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPGroupManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'LDAPGroups';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run; used as a step in
     * LDAPManager::schema(), which always replays its list from zero.
     *
     * One row per directory group this server recognises. The unique index
     * is on (server, name): the same group name in two directories is two
     * different groups, and one directory cannot list the same group twice.
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'lgID',
                'lgServerID',
                'lgName'
            ],
            [
                'INTEGER',
                'INTEGER',
                'VARCHAR(255)'
            ],
            [
                false,
                false,
                false
            ],
            [
                false,
                false,
                false
            ],
            [
                [
                    'lgServerID',
                    'lgName'
                ]
            ],
            'InnoDB',
            'utf8',
            'lgID',
            'lgID'
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
