<?php
/**
 * Windows keys association manager class.
 *
 * PHP version 5
 *
 * @category WindowsKeyAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Windows keys association manager class.
 *
 * @category WindowsKeyAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WindowsKeyAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'windowsKeysAssoc';
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
                'wkaID',
                'wkaImageID',
                'wkaKeyID'
            ],
            [
                'INTEGER',
                'INTEGER',
                'INTEGER'
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
            [
                [
                    'wkaImageID',
                    'wkaKeyID'
                ]
            ],
            'InnoDB',
            'utf8',
            'wkaID',
            'wkaID'
        );
        return self::$DB->query($sql);
    }
}
