<?php
/**
 * Access Control plugin
 *
 * PHP version 5
 *
 * @category AccessControlManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Access Control plugin
 *
 * @category AccessControlManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AccessControlManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'roles';
    /**
     * Installs the database for the plugin.
     *
     * @return bool
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'rID',
                'rName',
                'rDesc',
                'rCreatedBy',
                'rCreatedTime'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'TIMESTAMP'
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
                false,
                'CURRENT_TIMESTAMP'
            ],
            [
                'rID',
                'rName'
            ],
            'InnoDB',
            'utf8',
            'rID',
            'rID'
        );
    }
    /**
     * Seeds the default roles. Rows carry explicit primary keys, so a re-run
     * is idempotent (duplicate entries are tolerated by the schema runner).
     * Used as a step in schema().
     *
     * @return string
     */
    public function seedSql()
    {
        return sprintf(
            "INSERT INTO `%s` VALUES"
            . "(1, 'Administrator', 'FOG Administrator', 'fog', NOW()),"
            . "(2, 'Technician', 'FOG Technician', 'fog', NOW())",
            $this->tablename
        );
    }
    /**
     * The plugin's ordered, append-only schema migration list. It covers all
     * four accesscontrol tables and their seed/index steps, in the original
     * install order. Append new steps (e.g. "ALTER TABLE `roles` ADD COLUMN
     * ...") to the END.
     *
     * @return array
     */
    public function schema()
    {
        $assoc = self::getClass('AccessControlAssociationManager');
        $rule = self::getClass('AccessControlRuleManager');
        $ruleAssoc = self::getClass('AccessControlRuleAssociationManager');
        return [
            // 0  roles
            $this->createSql(),
            // 1
            $this->seedSql(),
            // 2  roleUserAssoc
            $assoc->createSql(),
            // 3  (dynamic: resolves the fog user id at run time)
            function () use ($assoc) {
                return $assoc->seedAssoc();
            },
            // 4  rules
            $rule->createSql(),
            // 5
            $rule->seedSql(),
            // 6
            $rule->indexSql(),
            // 7  roleRuleAssoc
            $ruleAssoc->createSql(),
            // 8
            $ruleAssoc->indexSql(),
        ];
    }
    /**
     * Installs the database non-destructively (create-if-absent + seed any
     * missing default rows). Does not drop existing data.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
    /**
     * Uninstalls the plugin
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('AccessControlAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
