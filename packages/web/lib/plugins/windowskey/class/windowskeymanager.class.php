<?php
/**
 * Windows Key manager mass management class
 *
 * PHP version 5
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows Key manager mass management class
 *
 * @category WindowsKeyManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'windowsKeys';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'wkID',
                'wkName',
                'wkDesc',
                'wkCreatedBy',
                'wkCreatedTime',
                'wkKey'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(200)'
            ],
            [
                false,
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
                'CURRENT_TIMESTAMP',
                false
            ],
            [
                'wkKey',
                'wkName'
            ],
            'InnoDB',
            'utf8',
            'wkID',
            'wkID'
        );
    }
    /**
     * The plugin's ordered, append-only schema migration list (all tables).
     * Append new steps (e.g. "ALTER TABLE `windowsKeys` ADD COLUMN ...") to
     * the END.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
            // 1
            self::getClass('WindowsKeyAssociationManager')->createSql(),
        ];
    }
    /**
     * Installs the database non-destructively (create-if-absent + apply any
     * pending additive steps). Does not drop existing data.
     *
     * @return bool
     */
    public function install()
    {
        $res = Schema::applyUpdates($this->schema(), 0);
        return $res['error'] === null;
    }
    /**
     * Uninstalls the database
     *
     * @return bool
     */
    public function uninstall()
    {
        self::getClass('WindowsKeyAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
