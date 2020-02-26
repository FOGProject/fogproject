<?php
/**
 * Site plugin
 *
 * PHP version 5
 *
 * @category SiteHostAssocManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Site plugin
 *
 * @category SiteHostAssocManager
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteHostAssociationManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'siteHostAssoc';
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
            [
                'shaID',
                'shaName',
                'shaSiteID',
                'shaHostID',
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
            'shaID',
            'shaID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        //return true;
        return self::getClass('SiteUserAssociationManager')->install();
    }
    /**
     * Uninstalls plugin.
     *
     * @return void
     */
    public function uninstall()
    {
        self::getClass('SiteUserAssociationManager')->uninstall();
        return parent::uninstall();
    }
}
