<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteAssocManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteAssocManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteUserAssociationManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'siteUserAssoc';
    /**
     * Installs the database for the plugin.
     *
     * @return bool
     */
    public function install()
    {
        $this->uninstall();
        $sql = Schema::createTable(
            $this->tablename,
            true,
            array(
                'suaID',
                'suaName',
                'suaSiteID',
                'suaUserID',
            ),
            array(
                'INTEGER',
                'VARCHAR(60)',
                'INTEGER',
                'INTEGER',
            ),
            array(
                false,
                false,
                false,
                false,
            ),
            array(
                false,
                false,
                false,
                false,
            ),
            array(),
            'MyISAM',
            'utf8',
            'suaID',
            'suaID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        //return true;
        return self::getClass('SiteUserRestrictionManager')->install();
    }
    /**
     * Uninstalls plugin.
     *
     * @return void
     */
    public function uninstall()
    {
        self::getClass('SiteUserRestrictionManager')->uninstall();
        return parent::uninstall();
    }
}
