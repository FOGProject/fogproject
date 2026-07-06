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
     * The plugin's ordered, append-only schema migration list.
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
            // 3 - historical: siteUserRestriction, retired by step 4. The
            // manager class is gone so its createSql() output is inlined
            // (steps are immutable; fresh installs create then drop it).
            'CREATE TABLE IF NOT EXISTS `siteUserRestriction` ('
            . '`surID` INTEGER NOT NULL AUTO_INCREMENT,'
            . '`surUserID` INTEGER NOT NULL,'
            . "`surRestricted` ENUM('0', '1') NOT NULL,"
            . 'PRIMARY KEY (`surID`)) ENGINE=InnoDB AUTO_INCREMENT=1'
            . ' DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
            // 4 - the restriction feature was never enforced and its only
            // reader (a hook listening for events that no longer fire) has
            // been removed.
            'DROP TABLE IF EXISTS `siteUserRestriction`',
            // 5 - explicit group -> site scope (host groups).
            self::getClass('SiteGroupAssociationManager')->createSql(),
            // 6 - explicit user-group -> site scope.
            self::getClass('SiteUserGroupAssociationManager')->createSql(),
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
        self::getClass('SiteUserAssociationManager')->uninstall();
        self::getClass('SiteGroupAssociationManager')->uninstall();
        self::getClass('SiteUserGroupAssociationManager')->uninstall();
        // Installs that never applied schema step 4 still have this table.
        self::$DB->query('DROP TABLE IF EXISTS `siteUserRestriction`');
        return parent::uninstall();
    }
}
