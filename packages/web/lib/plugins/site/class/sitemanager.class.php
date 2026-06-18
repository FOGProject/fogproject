<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'site';
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
                'sID',
                'sName',
                'sDesc'
            ],
            [
                'INTEGER',
                'VARCHAR(60)',
                'VARCHAR(255)',
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
            [],
            'InnoDB',
            'utf8',
            'sID',
            'sID'
        );
    }
    /**
     * The plugin's ordered, append-only schema migration list (all 4 tables).
     * Append new steps (e.g. "ALTER TABLE `site` ADD COLUMN ...") to the END.
     *
     * @return array
     */
    public function schema()
    {
        return [
            // 0
            $this->createSql(),
            // 1
            self::getClass('SiteHostAssociationManager')->createSql(),
            // 2
            self::getClass('SiteUserAssociationManager')->createSql(),
            // 3
            self::getClass('SiteUserRestrictionManager')->createSql(),
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
     * Uninstalls plugin.
     *
     * @return void
     */
    public function uninstall()
    {
        self::getClass('SiteHostAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
