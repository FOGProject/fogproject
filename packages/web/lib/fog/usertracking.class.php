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

namespace FOG;

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
        'createdTime' => 'utDateTime',
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
        'host',
        'hostname'
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\UserTracking', 'UserTracking');
