<?php
/**
 * File Delete Queue.
 *
 * PHP Version 5
 *
 * @category FileDeleteQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * File Delete Queue.
 *
 * @category FileDeleteQueue
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FileDeleteQueue extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'fileDeleteQueue';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'fdqID',
        'path' => 'fdqPathName',
        'storagegroupID' => 'fdqStorageGroupID',
        'createdTime' => 'fdqCreateDate',
        'createdBy' => 'fdqCreateBy',
        'completedTime' => 'fdqCompletedDate',
        'stateID' => 'fdqState',
        'pathtype' => 'fdqPathType'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'path',
        'pathType',
        'storagegroupID'
    ];
}
