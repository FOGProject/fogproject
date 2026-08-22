<?php
/**
 * UserTracking handles tracking users from client to client
 *
 * PHP version 7.4+
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
     * The person signed out of the host.
     *
     * These three are every value `utAction` has ever held. The column is an
     * int with no lookup table and no constraint, and the codes were written
     * as bare literals in the two places that care -- the client endpoint's
     * action map and the list formatter -- so nothing connected them and
     * nothing said what 99 meant. ADR 0020 Decision 2 requires an event's
     * `type` to be a stable machine code declared as class constants; this is
     * that, for the one event table that had none.
     *
     * The values themselves cannot change: rows carrying them exist on every
     * install and the fog-client posts the names that map to them.
     *
     * @var int
     */
    const ACTION_LOGOUT = 0;
    /**
     * The person signed in to the host.
     *
     * @var int
     */
    const ACTION_LOGIN = 1;
    /**
     * The fog-client service started on the host. Not a person at all, which
     * is why the Login History tabs show it as its own kind of row.
     *
     * @var int
     */
    const ACTION_SERVICE_START = 99;
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
