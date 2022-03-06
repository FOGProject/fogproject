<?php
/**
 * FileIntegrity Manager class.
 *
 * PHP version 5
 *
 * @category FileIntegrityManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * FileIntegrity Manager class.
 *
 * @category FileIntegrityManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileIntegrityManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'fileChecksums';
    /**
     * Install the database and plugin
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
                'fcsID',
                'fcsStorageNodeID',
                'fcsFileModTime',
                'fcsFileChecksum',
                'fcsFilePath',
                'fcsStatus'
            ),
            array(
                'INTEGER',
                'INTEGER',
                'DATETIME',
                'VARCHAR(255)',
                'VARCHAR(255)',
                "ENUM('0', '1', '2')"
            ),
            array(
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
                false
            ),
            array(
                'fcsID'
            ),
            'InnoDB',
            'utf8',
            'fcsID',
            'fcsID'
        );
        return self::$DB->query($sql);
    }
}
