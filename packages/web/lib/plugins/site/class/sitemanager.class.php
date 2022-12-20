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
                'sID',
                'sName',
                'sDesc'
            ),
            array(
                'INTEGER',
                'VARCHAR(60)',
                'VARCHAR(255)',
            ),
            array(
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false
            ),
            array(),
            'InnoDB',
            'utf8',
            'sID',
            'sID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        //return true;
        return self::getClass('SiteHostAssociationManager')->install();
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
