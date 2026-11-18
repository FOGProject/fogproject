<?php
/**
 * Role — native role-based access control.
 *
 * PHP version 7.4+
 *
 * @category Role
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Auth\Authorization;
use FOG\Base\FOGController;
use FOG\Managers\RolePermissionManager;
use FOG\Router\Route;

/**
 * Role — native role-based access control.
 *
 * A role is a named set of permissions (see RolePermission) assigned to
 * users (see RoleUserAssociation) or to whole user groups (see
 * RoleUserGroupAssociation). Access is deny-by-default: a user with no
 * role can do nothing — see Authorization::getPermissions().
 *
 * The roles/roleUserAssoc tables are shared with (and adopted from) the
 * retired accesscontrol plugin, so plugin-era roles and user assignments
 * carry over natively on upgrade.
 *
 * @category Role
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Role extends FOGController
{
    /**
     * The longest allowed-networks list the column can hold. Must equal the
     * width of roles.rAllowedNetworks, varchar(2048), set by schema step 461.
     *
     * @var int
     */
    const NETWORKS_MAX_LENGTH = 2048;
    /**
     * The role table.
     *
     * @var string
     */
    protected $databaseTable = 'roles';
    /**
     * The database fields and commonized names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'rID',
        'name' => 'rName',
        'description' => 'rDesc',
        'createdBy' => 'rCreatedBy',
        'createdTime' => 'rCreatedTime',
        'allowednetworks' => 'rAllowedNetworks'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'users',
        'usergroups',
        'permissions'
    ];
    /**
     * Add users to this role.
     *
     * @param array $addArray The user ids to add.
     *
     * @return object
     */
    public function addUser($addArray)
    {
        return $this->addRemItem(
            'users',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove users from this role.
     *
     * @param array $removeArray The user ids to remove.
     *
     * @return object
     */
    public function removeUser($removeArray)
    {
        return $this->addRemItem(
            'users',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Add user groups to this role.
     *
     * The role/user group link was previously editable only from the user
     * group side, which made roleUserGroupAssoc the one asymmetric
     * association in the product -- a role could hold user groups that its
     * own edit page could neither show nor change.
     *
     * @param array $addArray The user group ids to add.
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
     * Remove user groups from this role.
     *
     * @param array $removeArray The user group ids to remove.
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
     * Add permissions to this role.
     *
     * @param array $addArray The permission names to add.
     *
     * @return object
     */
    public function addPermission($addArray)
    {
        return $this->addRemItem(
            'permissions',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove permissions from this role.
     *
     * @param array $removeArray The permission names to remove.
     *
     * @return object
     */
    public function removePermission($removeArray)
    {
        return $this->addRemItem(
            'permissions',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Splits a stored or typed allowed-networks list into its entries.
     *
     * The one tokenizer for the list: normalizeNetworks() uses it to store
     * the value and User::allowedNetworks() uses it to enforce it, so the
     * two can never read the same text differently.
     *
     * @param mixed $value The list.
     *
     * @return array The non-empty entries, in order.
     */
    public static function splitNetworks($value)
    {
        return (array)preg_split(
            '/[\s,]+/',
            (string)$value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }
    /**
     * Validates and canonicalizes an allowed-networks list.
     *
     * Accepts entries separated by any mix of commas, spaces and newlines,
     * and returns one entry per line with duplicates removed. An entry is a
     * bare IPv4 or IPv6 address, or an address plus "/" plus a prefix length
     * (0..32 for IPv4, 0..128 for IPv6). Empty input is the valid "no
     * restriction" value and returns ''.
     *
     * Throws on the first bad entry rather than dropping it: a typo that is
     * silently ignored would leave the role less restricted than its author
     * believes. The message is not escaped here; the page layer escapes it.
     *
     * @param mixed $raw The list as typed or as sent to the API.
     *
     * @throws \InvalidArgumentException On an invalid entry or a list too long.
     *
     * @return string The canonical list, one entry per line.
     */
    public static function normalizeNetworks($raw)
    {
        $entries = self::splitNetworks($raw);
        $clean = [];
        foreach ($entries as $entry) {
            $parts = explode('/', $entry, 2);
            $bin = @inet_pton($parts[0]);
            $valid = false !== $bin;
            if ($valid && isset($parts[1])) {
                $valid = 1 === preg_match('/^\d{1,3}$/', $parts[1])
                    && (int)$parts[1] <= strlen($bin) * 8;
            }
            if (!$valid) {
                throw new \InvalidArgumentException(
                    sprintf(
                        _('%s is not a valid IP address or CIDR range.'),
                        $entry
                    )
                );
            }
            $clean[$entry] = $entry;
        }
        $list = implode("\n", array_values($clean));
        if (strlen($list) > self::NETWORKS_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    _('The allowed networks list is longer than %d characters.'),
                    self::NETWORKS_MAX_LENGTH
                )
            );
        }
        return $list;
    }
    /**
     * Whether an address matches any entry of an allowed-networks list.
     *
     * Only the match. The "empty list means no restriction" rule belongs to
     * User::networkAllows(), which knows the whole union of a user's roles.
     *
     * @param string $ip      The address to test.
     * @param array  $entries Bare addresses and CIDR ranges.
     *
     * @return bool
     */
    public static function networkListAllows($ip, array $entries)
    {
        foreach ($entries as $entry) {
            if (self::ipInCidr($ip, $entry)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Stores/updates the role.
     *
     * @return object
     */
    public function save()
    {
        // Validate and canonicalize before anything is written. Doing it
        // here rather than on the page means the UI, PUT /fog/role/<id> and
        // a plugin's own save() all hit the same check.
        $this->set(
            'allowednetworks',
            self::normalizeNetworks($this->get('allowednetworks'))
        );
        $this->_assertNetworksKeepActingUser();
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        return $this
            ->assocSetter('RoleUser', 'user')
            ->assocSetter('RoleUserGroup', 'usergroup')
            ->_syncPermissions()
            ->load();
    }
    /**
     * Refuses a list that would lock the acting user out of this very request.
     *
     * Applies only when the acting user holds this role and the new list
     * would refuse the address the change came from. Skipped when there is
     * no acting user or no request address (CLI, installer, schema updater),
     * and for a role that has no id yet, which nobody holds.
     *
     * @throws \Exception When the change would lock the acting user out.
     *
     * @return void
     */
    private function _assertNetworksKeepActingUser()
    {
        $roleID = (int)$this->get('id');
        if ($roleID < 1 || !self::$FOGUser->isValid()) {
            return;
        }
        $ip = User::requestAddress();
        if ('' === $ip) {
            return;
        }
        $allowed = self::$FOGUser->networkAllows(
            $ip,
            [$roleID => (string)$this->get('allowednetworks')]
        );
        if (!$allowed) {
            throw new \Exception(
                // One literal, so xgettext can extract it.
                _('This change would lock you out: your current address is not in the allowed networks of a role you hold.')
            );
        }
    }
    /**
     * Load users assigned to this role.
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['roleID' => $this->get('id')];
        $userids = Route::getIds(
            'roleuserassociation',
            $find,
            'userID'
        );
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        $filtered = (count($types)
            ? (array)Route::getIds('user', ['type' => $types])
            : []);
        $associds = array_diff(
            $userids,
            $filtered
        );
        unset($filtered);
        $this->set('users', (array)$associds);
    }
    /**
     * Load user groups assigned to this role.
     *
     * The mirror of UserGroup::loadRoles(), reading the same join table
     * from the other side.
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $this->set(
            'usergroups',
            (array)Route::getIds(
                'roleusergroupassociation',
                ['roleID' => $this->get('id')],
                'usergroupID'
            )
        );
    }
    /**
     * Load this role's permission names.
     *
     * @return void
     */
    protected function loadPermissions()
    {
        $find = ['roleID' => $this->get('id')];
        $permissions = Route::getIds(
            'rolepermission',
            $find,
            'name'
        );
        $this->set('permissions', (array)$permissions);
    }
    /**
     * Sync the rolePermissions rows to the in-memory permission list.
     *
     * Permission rows carry payload strings ('host.edit', 'image.*', '*'),
     * not entity ids, so assocSetter (which diffs on id columns) cannot be
     * used here; the rows are diffed and written directly instead.
     *
     * @return object
     */
    private function _syncPermissions()
    {
        // Gated on isDirty(), not isPopulated(): isPopulated() is also
        // true when something merely read get('permissions') this request
        // (e.g. building a status response) without changing it, which
        // would make every such save() run this diff for a no-op result.
        // isDirty() only reports true when the caller actually wrote
        // 'permissions', so an untouched role's permissions cost nothing
        // here and are never at risk of the stale-empty-list bug this
        // guard exists to prevent in the first place.
        if (!$this->isDirty('permissions')) {
            return $this;
        }
        $permissions = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'trim',
                        (array)$this->get('permissions')
                    )
                )
            )
        );
        $cur = Route::getIds(
            'rolepermission',
            ['roleID' => $this->get('id')],
            'name'
        );
        $rem = array_diff((array)$cur, $permissions);
        if (count($rem ?: [])) {
            Route::deletemass(
                'rolepermission',
                [
                    'name' => array_values($rem),
                    'roleID' => $this->get('id')
                ]
            );
        }
        $add = array_diff($permissions, (array)$cur);
        if (count($add ?: [])) {
            $insert_values = [];
            foreach ($add as $permission) {
                // Rows are written with insertBatch rather than through
                // RolePermission::save(), so the guard there does not see
                // them -- this is the path PUT /fog/role/<id> takes. Only
                // newly added strings are checked: a role may legitimately
                // still hold a permission whose plugin has since been
                // uninstalled, and re-saving it should not fail.
                Authorization::assertCanGrant($permission);
                $insert_values[] = [
                    $this->get('id'),
                    $permission
                ];
            }
            (new RolePermissionManager())->insertBatch(
                ['roleID', 'name'],
                $insert_values
            );
        }
        return $this;
    }
    /**
     * Destroy this role.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        // Funnel cleanup through the cascade authority (the role case in
        // Route::deletemass removes the rolePermissions and roleUserAssoc
        // rows). Members lose whatever this role granted them; the
        // last-administrator guard in Route::deletemass is what keeps that
        // from locking the install out.
        Route::deletemass('role', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
