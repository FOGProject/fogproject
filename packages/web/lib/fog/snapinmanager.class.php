<?php
/**
 * Snapin manager mass management class.
 *
 * PHP version 5
 *
 * @category SnapinManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Snapin manager mass management class.
 *
 * @category SnapinManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'snapins';
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
                'sID',
                'sName',
                'sDesc',
                'sFilePath',
                'sArgs',
                'sCreateDate',
                'sCreator',
                'sReboot',
                'sRunWith',
                'sRunWithArgs',
                'sAnon3',
                'snapinProtect',
                'sEnabled',
                'sReplicate',
                'sShutdown',
                'sHideLog',
                'sTimeout',
                'sPackType',
                'sHash',
                'sSize'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'LONGTEXT',
                'LONGTEXT',
                'LONGTEXT',
                'TIMESTAMP',
                'VARCHAR(255)',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                'VARCHAR(255)',
                'VARCHAR(255)',
                'INTEGER',
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                "ENUM('0', '1')",
                'INTEGER',
                "ENUM('0', '1')",
                'VARCHAR(255)',
                'BIGINT(20)'
            ],
            [
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
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
                false,
                'CURRENT_TIMESTAMP',
                false,
                false,
                false,
                false,
                false,
                false,
                '1',
                '1',
                '0',
                '0',
                '0',
                '0',
                false,
                '0'
            ],
            [
                'sID',
                'sName'
            ],
            'InnoDB',
            'utf8',
            'sID',
            'sID'
        );
        return self::$DB->query($sql);
    }
}
