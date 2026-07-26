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
     * Both directions, because the entity classes cannot cover their own.
     *
     * The TARGET being deleted (role, user group, user) was never covered
     * anywhere, so a deleted role left its mapping behind and a deleted
     * user left the record of what the sync had granted them.
     *
     * The SOURCE being deleted (an LDAP group, or a server and with it its
     * groups) IS handled by LDAPGroup::destroy() and LDAP::destroy() -- but
     * only when something actually calls destroy(). Route::delete(), which
     * is the REST single-delete, funnels straight into deletemass() and
     * never constructs the object, so on that path the overrides do not
     * run at all and the mappings were orphaned. Deleting an LDAP group
     * over the API demonstrably left its ldapGroupRoleAssoc rows behind.
     * Every sibling plugin already carries its own case here for exactly
     * this reason (location, site, ou, windowskey); ldap was the only one
     * relying on destroy() alone. The destroy() overrides stay: they are
     * what the UI path uses, and this makes the two agree.
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
            case 'ldap':
                // The server's groups. deletemass() runs each removeItems
                // entry back through itself, so this re-enters as
                // classname 'ldapgroup' below and each group still takes
                // its own mappings with it -- the same one-at-a-time effect
                // LDAP::destroy() gets by looping.
                $arguments['removeItems']['ldapgroup'] = [
                    'serverID' => $arguments['itemIDs']
                ];
                break;
            case 'ldapgroup':
                $arguments['removeItems']['ldapgrouproleassociation'] = [
                    'ldapgroupID' => $arguments['itemIDs']
                ];
                $arguments['removeItems']['ldapgroupusergroupassociation'] = [
                    'ldapgroupID' => $arguments['itemIDs']
                ];
                break;
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
