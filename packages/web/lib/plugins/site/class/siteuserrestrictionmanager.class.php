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
class SiteUserRestrictionManager extends FOGManagerController
{
    /**
     * The table name.
     *
     * @var string
     */
    public $tablename = 'siteUserRestriction';
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
                'surID',
                'surUserID',
                'surRestricted'
            ),
            array(
                'INTEGER',
                'INTEGER',
                "ENUM('0', '1')"
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
            'MyISAM',
            'utf8',
            'surID',
            'surID'
        );
        if (!self::$DB->query($sql)) {
            return false;
        }
        return true;
    }
}
