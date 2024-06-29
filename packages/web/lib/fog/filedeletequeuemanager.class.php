<?php
/**
 * File Delete Queue handler class (informative).
 *
 * PHP version 5
 *
 * @category FileDeleteQueueManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * File Delete Queue handler class (informative).
 *
 * @category FileDeleteQueueManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileDeleteQueueManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'fileDeleteQueue';
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
                'fdqID',
                'fqdPathName',
                'fqdStorageGroupID',
                'fqdCreateDate',
                'fqdCompletedDate',
                'fqdCreateBy'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'DATETIME',
                'DATETIME',
                'VARCHAR(40)'
            ],
            [
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                '0000-00-00 00:00:00',
                false
            ],
            [],
            'InnoDB',
            'utf8',
            'fqdID',
            'fqdID'
        );
    }
}
