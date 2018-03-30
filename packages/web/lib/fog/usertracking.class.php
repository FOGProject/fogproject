<?php
/**
 * UserTracking handles tracking users from client to client
 *
 * PHP version 5
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * UserTracking handles tracking users from client to client
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserTracking extends FOGController
{
    /**
     * DatabaseTable
     *
     * @var string
     */
    protected $databaseTable = 'userTracking';
    /**
     * DatabaseFields
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'utID',
        'hostID' => 'utHostID',
        'username' => 'utUserName',
        'action' => 'utAction',
        'datetime' => 'utDateTime',
        'description' => 'utDesc',
        'date' => 'utDate',
        'anon3' => 'utAnon3'
    ];
    /**
     * DatabaseFieldsRequired
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'username'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'Host' => [
            'id',
            'hostID',
            'host'
        ]
    ];
}
