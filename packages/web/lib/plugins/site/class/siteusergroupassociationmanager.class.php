<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteUserGroupAssocManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteUserGroupAssocManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserGroupAssociationManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'siteUserGroupAssoc';
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
                'sugaID',
                'sugaName',
                'sugaSiteID',
                'sugaUserGroupID',
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
            'sugaID',
            'sugaID'
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
