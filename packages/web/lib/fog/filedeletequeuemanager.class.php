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
                'fdqPathName',
                'fdqStorageGroupID',
                'fdqCreateDate',
                'fdqCompletedDate',
                'fdqCreateBy',
                'fdqState'
            ],
            [
                'INTEGER',
                'VARCHAR(255)',
                'INTEGER',
                'DATETIME',
                'DATETIME',
                'VARCHAR(40)',
                'INT(11)'
            ],
            [
                false,
                false,
                false,
                'CURRENT_TIMESTAMP',
                '0000-00-00 00:00:00',
                false,
                false
            ],
            [],
            'InnoDB',
            'utf8',
            'fqdID',
            'fqdID'
        );
    }
    /**
     * Cancels the passed tasks
     *
     * @param mixed $filedeletequeueids the ids to cancel
     *
     * @return bool
     */
    public function cancel($filedeletequeueids)
    {
        $cancelled = self::getCancelledState();
        $notComplete = self::fastmerge(
            (array)self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $findWhere = [
            'id' => (array)$filedeletequeueids,
            'stateID' => $notComplete
        ];
        $this->update(
            $findWhere,
            '',
            ['stateID' => $cancelled]
        );
    }
}
