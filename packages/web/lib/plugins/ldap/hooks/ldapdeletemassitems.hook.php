<?php
/**
 * Deletes the LDAP plugin elements en-mass.
 *
 * PHP version 5
 *
 * @category LDAPDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deletes the LDAP plugin elements en-mass.
 *
 * @category LDAPDeleteMassItems
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPDeleteMassItems extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'LDAPDeleteMassItems';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete En-mass Route altering for LDAP';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'ldap';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['DELETEMASS_API', 'deletemassitems'],
        ]);
    }
    /**
     * Prepares to clean up associations
     *
     * The plugin already cascades in the direction it owns outright: an
     * LDAPGroup clears its own associations, and deleting a server clears
     * its groups. This is the other direction -- the TARGET being deleted
     * -- which nothing covered, so a deleted role or user group left its
     * mapping behind and a deleted user left the record of what the sync
     * had granted them.
     *
     * A surviving mapping is the one that matters: it is still live, so if
     * its target id is ever reused (database restore, import, migration)
     * every member of that directory group silently receives whatever now
     * holds the id. That is the same reasoning that already makes deleting
     * a server clear its mappings rather than let a server reusing the id
     * inherit them.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/885
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function deletemassitems($arguments)
    {
        switch ($arguments['classname']) {
            case 'user':
                $arguments['removeItems']['ldapusergrant'] = [
                    'userID' => $arguments['itemIDs']
                ];
                break;
            case 'role':
                $arguments['removeItems']['ldapgrouproleassociation'] = [
                    'roleID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['ldapusergrant'] = [
                    'targetType' => LDAPUserGrant::TARGET_ROLE,
                    'targetID' => $arguments['itemIDs']
                ];
                break;
            case 'usergroup':
                $arguments['removeItems']['ldapgroupusergroupassociation'] = [
                    'usergroupID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['ldapusergrant'] = [
                    'targetType' => LDAPUserGrant::TARGET_USERGROUP,
                    'targetID' => $arguments['itemIDs']
                ];
                break;
        }
    }
}
