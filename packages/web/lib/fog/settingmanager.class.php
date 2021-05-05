<?php
/**
 * The global settings manager class.
 *
 * PHP version 5
 *
 * @category SettingManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The global settings manager class.
 *
 * @category SettingManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SettingManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'globalSettings';
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
            [
                'settingID',
                'settingKey',
                'settingDesc',
                'settingValue',
                'settingCategory'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                'LONGTEXT'
            ],
            [
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
                false
            ],
            [
                'settingID',
                'settingKey'
            ],
            'InnoDB',
            'utf8',
            'settingID',
            'settingID'
        );
        return self::$DB->query($sql);
    }
}
