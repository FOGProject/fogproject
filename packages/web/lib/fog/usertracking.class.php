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
        'anon3' => 'utAnon3',
        // ADR 0020 phase 3. The frame's actor, origin and subject label,
        // added to the table by schema 349 and filled from here on.
        //
        // createdBy is deliberately NOT set by any writer: save() fills it
        // with the signed-in operator or the literal 'fog', and 'fog' is
        // the right answer for every row in this table. The fog-client is
        // what causes a userTracking row; the person named in utUserName
        // logged into the ENDPOINT and is not a FOG identity at all, which
        // is ADR 0020 decision 3 and the reason the two are separate
        // columns rather than one. Mapping the key is the whole change.
        'createdBy' => 'utCreatedBy',
        'ip' => 'utIP',
        // The subject is the host -- utHostID is subjectID and always has
        // been. This is schema 341's denormalized label generalized to this
        // table: Route::deletemass('host') leaves userTracking rows in
        // place, and until now they rendered a blank host name forever
        // afterwards because the grid resolved the name live from an id
        // that no longer pointed anywhere.
        'subjectLabel' => 'utHostName'
    ];
    /**
     * The grid list query, with the host joined in.
     *
     * Same reason as TaskLog's, which carries the full explanation: the
     * group page's Login History tab groups by host, RowGroup needs the
     * grouped column to be the primary sort, and sorting on `utHostID` puts
     * the hosts in id order rather than alphabetically.
     *
     * `utHostName` cannot stand in for the join, for the same reason
     * TaskLog's `logHostName` cannot: it is a copy taken at write time, so
     * a host renamed midway through its history has two values against one
     * id and would be split into two groups. It is also empty on every row
     * written before ADR 0020 phase 3.
     *
     * LEFT OUTER so a row whose host has since been deleted is still listed.
     *
     * @var string
     */
    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `userTracking`.`utHostID` = `hosts`.`hostID`
        %s
        %s
        %s";
    /**
     * The sql filter string, carrying the same join as the query.
     *
     * @var string
     */
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `userTracking`.`utHostID` = `hosts`.`hostID`
        %s";
    /**
     * The sql total string, carrying the same join as the query.
     *
     * @var string
     */
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `hosts`
        ON `userTracking`.`utHostID` = `hosts`.`hostID`";
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
