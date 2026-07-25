<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPUserGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * A record of one role or user group this plugin granted to one user.
 *
 * The sync needs to know which of a user's grants are its own, so it can
 * recompute those and leave anything an admin attached by hand alone.
 *
 * That used to be inferred: "managed" meant "appears as a mapping target".
 * The inference has a hole, and it is the one an admin is most likely to
 * hit -- remove the LAST mapping to a role and the role stops being a
 * mapping target, so it drops out of the managed set and everyone who
 * already holds it keeps it forever. Removing a mapping reads as "revoke
 * this", and it silently did not.
 *
 * Recording the grant closes it: the record survives the mapping being
 * deleted, so the next sign in still knows the grant was the plugin's to
 * take away. It is also per-user rather than global, which is a more
 * honest answer to "did we give this person this?" than any set derived
 * from the mapping tables can be.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPUserGrant
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPUserGrant extends FOGController
{
    /**
     * Target kinds, matching the association tables they mirror.
     */
    const TARGET_ROLE = 'role';
    const TARGET_USERGROUP = 'usergroup';
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'ldapUserGrant';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'lugID',
        'name' => 'lugName',
        'userID' => 'lugUserID',
        'targetType' => 'lugTargetType',
        'targetID' => 'lugTargetID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'userID',
        'targetType',
        'targetID'
    ];
}
