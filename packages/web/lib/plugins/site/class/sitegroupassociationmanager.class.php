<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteGroupAssocManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteGroupAssocManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteGroupAssociationManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'siteGroupAssoc';
    /**
     * Returns the CREATE TABLE (IF NOT EXISTS) statement for this table.
     *
     * Non-destructive and safe to re-run. Used as a step in
     * SiteManager::schema().
     *
     * @return string
     */
    public function createSql()
    {
        return Schema::createTable(
            $this->tablename,
            true,
            [
                'sgaID',
                'sgaName',
                'sgaSiteID',
                'sgaGroupID',
            ],
            [
                'INTEGER',
                'VARCHAR(60)',
                'INTEGER',
                'INTEGER',
            ],
            [
                false,
                false,
                false,
                false,
            ],
            [
                false,
                false,
                false,
                false,
            ],
            [],
            'InnoDB',
            'utf8',
            'sgaID',
            'sgaID'
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
}
