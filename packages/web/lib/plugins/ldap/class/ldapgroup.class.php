<?php
/**
 * LDAP Authentication plugin
 *
 * PHP version 5
 *
 * @category LDAPGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * A directory group this server is willing to recognise.
 *
 * The group is a first-class row rather than a string on a mapping so that
 * granting it a role or a user group is an ordinary FOG association: the
 * standard association tab enumerates roles (or user groups) and checks the
 * ones this group grants, exactly as Host/Group or Role/User do.
 *
 * The earlier LDAPGroupMap stored one (group name, target) pair per row.
 * That could not use the shared association tab at all -- renderAssocTab()
 * needs an enumerable entity and an owner id, and a free-text group name is
 * neither -- and it made the name a per-mapping string, so granting one
 * directory group both a role and a user group meant typing the name twice
 * with nothing tying the two rows together.
 *
 * Rows are scoped to a server on purpose: a group name only means something
 * relative to the directory it came from.
 *
 * Refs https://github.com/FOGProject/fogproject/issues/882
 *
 * @category LDAPGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPGroup extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'LDAPGroups';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'lgID',
        'serverID' => 'lgServerID',
        'name' => 'lgName'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'serverID',
        'name'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'roles',
        'usergroups',
        'ldapserver'
    ];
    /**
     * Database -> Class field relationships.
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
    /**
     * Stores the group, syncing both association sets.
     *
     * assocSetter() no-ops on an association that was never loaded or set,
     * so a save path that never touches roles or user groups is safe.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('LDAPGroupRoleAssociation', 'role', true)
            ->assocSetter('LDAPGroupUserGroupAssociation', 'usergroup', true)
            ->load();
    }
    /**
     * Grants this group the given roles.
     *
     * @param array $addArray the role ids to add
     *
     * @return object
     */
    public function addRole($addArray)
    {
        return $this->addRemItem(
            'roles',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Stops this group granting the given roles.
     *
     * @param array $removeArray the role ids to remove
     *
     * @return object
     */
    public function removeRole($removeArray)
    {
        return $this->addRemItem(
            'roles',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Grants this group the given user groups.
     *
     * @param array $addArray the user group ids to add
     *
     * @return object
     */
    public function addUserGroup($addArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Stops this group granting the given user groups.
     *
     * @param array $removeArray the user group ids to remove
     *
     * @return object
     */
    public function removeUserGroup($removeArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads the roles this group grants.
     *
     * @return void
     */
    protected function loadRoles()
    {
        $this->set(
            'roles',
            (array)Route::getIds(
                'ldapgrouproleassociation',
                ['ldapgroupID' => $this->get('id')],
                'roleID'
            )
        );
    }
    /**
     * Loads the user groups this group grants.
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $this->set(
            'usergroups',
            (array)Route::getIds(
                'ldapgroupusergroupassociation',
                ['ldapgroupID' => $this->get('id')],
                'usergroupID'
            )
        );
    }
    /**
     * Removes the group and the associations that point at it.
     *
     * Nothing else cleans these up, and an orphaned association row would
     * be inherited by whichever group next reuses this auto-increment id.
     *
     * @param string $key the key to destroy on
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        $id = (int)$this->get('id');
        if ($id > 0) {
            Route::deletemass(
                'ldapgrouproleassociation',
                ['ldapgroupID' => $id]
            );
            Route::deletemass(
                'ldapgroupusergroupassociation',
                ['ldapgroupID' => $id]
            );
        }
        return parent::destroy($key);
    }
}
