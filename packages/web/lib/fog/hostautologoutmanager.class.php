<?php
/**
 * Host auto logout manager class.
 *
 * PHP version 5
 *
 * @category HostAutoLogoutManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host auto logout manager class.
 *
 * @category HostAutoLogoutManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostAutoLogoutManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'hostAutoLogout';
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
                'hID',
                'hText',
                'hUser',
                'hTime',
                'hIP'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'VARCHAR(40)',
                'TIMESTAMP',
                'VARCHAR(50)'
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
                'CURRENT_TIMESTAMP',
                false
            ],
            [
                'hID',
                [
                    'hUser',
                    'hTime'
                ]
            ],
            'MyISAM',
            'utf8',
            'hID',
            'hID'
        );
        return self::$DB->query($sql);
    }
}
