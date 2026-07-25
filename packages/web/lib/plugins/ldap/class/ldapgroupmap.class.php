<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPGroupMap
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Maps one directory group to one FOG role or user group.
 *
 * Replaces the two fixed buckets a server used to have (lsAdminGroup and
 * lsUserGroup, each pointing at one globally configured role). Those could
 * only ever express "admin" and "not admin", which was the right shape when
 * FOG itself had two tiers and is a dead end under roles: an organisation
 * with Helpdesk, Imaging Techs and per-site admins cannot say so.
 *
 * Rows are scoped to a server on purpose -- a group name only means
 * something relative to the directory it came from.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPGroupMap
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPGroupMap extends FOGController
{
    /**
     * Target kinds. A role is the shortcut for the common case; a user
     * group is the better answer, because the group holds the roles and
     * core RBAC stays the single place policy is expressed.
     */
    const TARGET_ROLE = 'role';
    const TARGET_USERGROUP = 'usergroup';
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'LDAPGroupMap';
    /**
     * The table fields.
     *
     * 'name' is the directory group name rather than a label of its own:
     * it is what identifies the row to a human, and it keeps the generic
     * list/render helpers that expect a 'name' working without inventing a
     * second field nobody would fill in.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'lgmID',
        'serverID' => 'lgmServerID',
        'name' => 'lgmGroup',
        'targetType' => 'lgmTargetType',
        'targetID' => 'lgmTargetID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'serverID',
        'name',
        'targetType',
        'targetID'
    ];
    /**
     * The additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'ldapserver'
    ];
    /**
     * Database -> Class field relationships
     *
     * Only the server is related here. The target cannot be, because which
     * class it points at depends on targetType, and the relationship map
     * is static.
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'LDAP' => [
            'id',
            'serverID',
            'ldapserver'
        ]
    ];
}
