<?php
/**
 * Host screen settings manager class.
 *
 * PHP version 5
 *
 * @category HostScreenSettingManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host screen settings manager class.
 *
 * @category HostScreenSettingManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostScreenSettingManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'hostScreenSettings';
    /**
     * Install our table.
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
                'hssID',
                'hssHostID',
                'hssWidth',
                'hssHeight',
                'hssRefresh',
                'hssOrientation',
                'hssOther1',
                'hssOther2'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER',
                'INTEGER'
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false
            ),
            array(
                'hssID',
                'hssHostID'
            ),
            'MyISAM',
            'utf8',
            'hssID',
            'hssID'
        );
        return self::$DB->query($sql);
    }
}
