<?php
/**
 * Authorization service — native role-based permission checks.
 *
 * PHP version 5
 *
 * @category Authorization
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Authorization service — native role-based permission checks.
 *
 * Permissions are strings of the form '<node>.<action>' (e.g. 'host.edit'),
 * a node wildcard ('host.*'), or the global wildcard '*'. A user's
 * permissions are the union across all roles assigned to them.
 *
 * Upgrade stance: a user with NO role at all is an implicit administrator
 * (full access). Deny only ever applies once a user has at least one role.
 * getPermissions() therefore distinguishes null (no roles = implicit
 * admin) from an empty array (roles granting nothing = deny).
 *
 * @category Authorization
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Authorization extends FOGBase
{
    /**
     * Page nodes that are never permission-checked (pre-auth surfaces,
     * client/service endpoints, the dashboard, and the schema updater
     * which carries its own install-token gate).
     *
     * @var array
     */
    const EXEMPT_NODES = [
        'home',
        'client',
        'schema',
        'hwinfo',
        'logout',
        'login'
    ];
    /**
     * Page node => registry node aliases.
     *
     * @var array
     */
    const NODE_ALIASES = [
        'about' => 'settings'
    ];
    /**
     * Exact sub overrides that the naming conventions would misresolve.
     * Shape: node => [sub => permission]. A permission is either a full
     * permission string or ['GET' => perm, 'POST' => perm] when the same
     * sub reads on GET but mutates (task cancel) on POST.
     *
     * @var array
     */
    const SUB_OVERRIDES = [
        'task' => [
            'active' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activemulticast' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activesnapins' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activescheduled' => ['GET' => 'task.view', 'POST' => 'task.task'],
            'activescheduleddels' => ['GET' => 'task.view', 'POST' => 'task.task']
        ],
        'host' => [
            'savegroup' => 'group.create'
        ],
        'image' => [
            'sessioncreate' => 'image.task',
            'sessioncancel' => 'image.task',
            'sessioncreatemodal' => 'image.task'
        ]
    ];
    /**
     * Node-independent sub overrides, checked before the exempt-node
     * bail-out: kernelfetch/initrdfetch live on the FOGPage base class and
     * the download JS requests them with no node at all, so they dispatch
     * under the exempt 'home' node and node-scoped overrides never fire.
     *
     * @var array
     */
    const GLOBAL_SUB_OVERRIDES = [
        'kernelfetch' => 'settings.edit',
        'initrdfetch' => 'settings.edit'
    ];
    /**
     * API route name => permission action, for the class-parameterized
     * routes (Route::$validClasses expansions). The route names already
     * encode the HTTP method, so no method split is needed here.
     *
     * @var array
     */
    const API_ROUTE_ACTIONS = [
        'list' => 'view',
        'indiv' => 'view',
        'search' => 'view',
        'count' => 'view',
        'names' => 'view',
        'ids' => 'view',
        'active' => 'view',
        'create' => 'create',
        'join' => 'create',
        'update' => 'edit',
        'delete' => 'delete',
        'task' => 'task',
        'cancel' => 'task'
    ];
    /**
     * Fixed (non class-parameterized) API route name => full permission.
     * null = no check beyond the authentication the Route constructor
     * already enforces (or none at all for the unauthenticated routes:
     * status, bandwidth). whoami only echoes .fogsettings server facts.
     *
     * @var array
     */
    const API_ROUTE_PERMISSIONS = [
        'status' => null,
        'bandwidth' => null,
        'whoami' => null,
        'unisearch' => null,
        'export' => 'settings.edit',
        'kernelUpdate' => 'settings.view',
        'initrdUpdate' => 'settings.view',
        'logfiles' => 'settings.view',
        'pendingmacs' => 'host.view',
        'snapinCreateWithFile' => 'snapin.create',
        'uploadSnapinFiles' => 'snapin.create',
        'settingsCacheView' => 'settings.view',
        'settingsCacheFlush' => 'settings.edit',
        'settingsCacheRefresh' => 'settings.edit'
    ];
    /**
     * API model class => registry node. Sub-entities check their parent
     * entity's permission (a host's power management schedule is host
     * data, an imaging log is report data, ...). An action absent from
     * the registry node (e.g. task.delete for lookup-table writes) is
     * still a valid permission string but only the global '*' grants it,
     * which deliberately reserves those writes for full administrators.
     *
     * @var array
     */
    const API_CLASS_ENTITIES = [
        'filedeletequeue' => 'task',
        'group' => 'group',
        'groupassociation' => 'group',
        'history' => 'report',
        'hookevent' => 'settings',
        'host' => 'host',
        'hostautologout' => 'host',
        'hostscreensetting' => 'host',
        'image' => 'image',
        'imageassociation' => 'image',
        'imagepartitiontype' => 'image',
        'imagetype' => 'image',
        'imaginglog' => 'report',
        'inventory' => 'host',
        'ipxe' => 'ipxe',
        'keysequence' => 'ipxe',
        'macaddressassociation' => 'host',
        'module' => 'module',
        'moduleassociation' => 'module',
        'multicastsession' => 'task',
        'multicastsessionassociation' => 'task',
        'nodefailure' => 'task',
        'notifyevent' => 'settings',
        'os' => 'image',
        'oui' => 'settings',
        'plugin' => 'plugin',
        'powermanagement' => 'host',
        'printer' => 'printer',
        'printerassociation' => 'printer',
        'pxemenuoptions' => 'ipxe',
        'role' => 'role',
        'rolepermission' => 'role',
        'roleuserassociation' => 'role',
        'scheduledtask' => 'task',
        'setting' => 'settings',
        'snapin' => 'snapin',
        'snapinassociation' => 'snapin',
        'snapingroupassociation' => 'snapin',
        'snapinjob' => 'task',
        'snapintask' => 'task',
        'storagegroup' => 'storagegroup',
        'storagenode' => 'storagenode',
        'task' => 'task',
        'tasklog' => 'task',
        'taskstate' => 'task',
        'tasktype' => 'task',
        'user' => 'user',
        'usergroup' => 'usergroup',
        'usergroupmember' => 'usergroup',
        'roleusergroupassociation' => 'usergroup',
        'usertracking' => 'report'
    ];
    /**
     * Per-user permission cache for this request.
     * userID => array of permission strings, or null = implicit admin.
     *
     * @var array
     */
    private static $_permCache = [];
    /**
     * Memoized permission registry.
     *
     * @var array|null
     */
    private static $_registry = null;
    /**
     * The permission registry: registry node => list of valid actions.
     *
     * Plugins can add their own nodes/actions through the
     * PERMISSION_REGISTRY_DATA hook event; registered entries appear in
     * the role edit permission matrix. A page node absent from the
     * registry is not permission-checked at all (compatibility stance for
     * plugin pages that predate this system).
     *
     * @return array
     */
    public static function registry()
    {
        if (null !== self::$_registry) {
            return self::$_registry;
        }
        $registry = [
            'host' => ['view', 'create', 'edit', 'delete', 'task'],
            'group' => ['view', 'create', 'edit', 'delete', 'task'],
            'image' => ['view', 'create', 'edit', 'delete', 'task'],
            'snapin' => ['view', 'create', 'edit', 'delete'],
            'printer' => ['view', 'create', 'edit', 'delete'],
            'module' => ['view', 'create', 'edit', 'delete'],
            'user' => ['view', 'create', 'edit', 'delete'],
            'usergroup' => ['view', 'create', 'edit', 'delete'],
            'role' => ['view', 'create', 'edit', 'delete'],
            'storagenode' => ['view', 'create', 'edit', 'delete'],
            'storagegroup' => ['view', 'create', 'edit', 'delete'],
            'ipxe' => ['view', 'create', 'edit', 'delete'],
            'task' => ['view', 'task'],
            'service' => ['view', 'edit'],
            'settings' => ['view', 'edit'],
            'report' => ['view', 'create'],
            'plugin' => ['view', 'edit']
        ];
        self::$HookManager->processEvent(
            'PERMISSION_REGISTRY_DATA',
            ['registry' => &$registry]
        );
        return self::$_registry = $registry;
    }
    /**
     * Get the union of a user's permissions across all assigned roles.
     *
     * @param int $userID the user id (defaults to the current user)
     *
     * @return array|null permission strings; null = user has no role at
     *                    all and is an implicit administrator.
     */
    public static function getPermissions($userID = null)
    {
        if (null === $userID) {
            $userID = (
                self::$FOGUser && self::$FOGUser->isValid() ?
                (int)self::$FOGUser->get('id') :
                0
            );
        }
        $userID = (int)$userID;
        if ($userID < 1) {
            // No identity: grant nothing. Authentication gates should have
            // already rejected the request before any permission check.
            return [];
        }
        if (array_key_exists($userID, self::$_permCache)) {
            return self::$_permCache[$userID];
        }
        // Effective permissions are the union of roles assigned directly to
        // the user and roles assigned to any group the user belongs to. A
        // LEFT JOIN keeps a row for a role that grants zero permissions
        // (rpName NULL) so having-a-role is distinguishable from having no
        // role at all; the group arm's inner JOIN yields a row only once a
        // role actually reaches the user through a group (a role-less group
        // confers nothing and leaves the user unmanaged).
        $sql = 'SELECT `rpName` '
            . 'FROM `roleUserAssoc` '
            . 'LEFT JOIN `rolePermissions` ON `rpRoleID` = `ruaRoleID` '
            . 'WHERE `ruaUserID` = :userid '
            . 'UNION '
            . 'SELECT `rpName` '
            . 'FROM `userGroupMembers` '
            . 'JOIN `roleUserGroupAssoc` ON `rugGroupID` = `ugmGroupID` '
            . 'LEFT JOIN `rolePermissions` ON `rpRoleID` = `rugRoleID` '
            . 'WHERE `ugmUserID` = :usergroupid';
        $rows = self::$DB
            ->query(
                $sql,
                [],
                ['userid' => $userID, 'usergroupid' => $userID]
            )
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        if (!is_array($rows) || count($rows) < 1) {
            // Zero role assignments, direct or group-sourced.
            //
            // For a LOCAL account this stays "implicit administrator", which
            // is what keeps an upgrade from locking every existing user out
            // before any role has been assigned.
            //
            // For an EXTERNALLY authenticated account it must not. An auth
            // plugin creates its users on the fly at login, so a role-less
            // externally sourced user is the normal case rather than the
            // upgrade edge case the fallback was written for -- which made
            // every LDAP-authenticated user a full administrator once RBAC
            // landed. Deny instead: an external identity gets exactly the
            // roles its provider mapped to it, and nothing by default.
            //
            // This is checked here, in the resolver, rather than at account
            // creation, so it holds for every account with a provider stamp
            // no matter how or when that account came to exist.
            if ('' !== self::_authSource($userID)) {
                return self::$_permCache[$userID] = [];
            }
            return self::$_permCache[$userID] = null;
        }
        $perms = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['rpName'] ?? ''));
            if ('' !== $name) {
                $perms[] = $name;
            }
        }
        return self::$_permCache[$userID] = array_values(
            array_unique($perms)
        );
    }
    /**
     * The external provider that authenticates a user, '' when local.
     *
     * Read straight from the table rather than through the User model so a
     * permission lookup cannot recurse back into a controller that may
     * itself consult permissions. Only ever called on the zero-role path,
     * so this costs nothing for the common case.
     *
     * @param int $userID the user id
     *
     * @return string
     */
    private static function _authSource($userID)
    {
        // The whole row, not the single column: this runs on every request
        // that reaches the zero-role branch, including during the window
        // between deploying this code and running the schema update, when
        // `uAuthSource` does not exist yet. Naming the column in the SELECT
        // would make that query fail and deny every user until the update
        // ran -- and the update itself is reached through an authenticated
        // page. Selecting the row degrades to "no such column, therefore
        // local", which is exactly the pre-upgrade behaviour.
        $row = self::$DB
            ->query(
                'SELECT * FROM `users` WHERE `uId` = :userid',
                [],
                ['userid' => (int)$userID]
            )
            ->fetch()
            ->get();
        // No row at all is a different matter: the id does not name a real
        // user, so there is no provenance to trust and nothing to grant.
        if (!is_array($row) || count($row) < 1) {
            return 'unknown';
        }
        return trim((string)($row['uAuthSource'] ?? ''));
    }
    /**
     * Does the user hold the given permission?
     *
     * @param string|null $perm   permission string ('host.edit'); null or
     *                            empty = nothing required (allowed)
     * @param int|null    $userID the user id (defaults to current user)
     *
     * @return bool
     */
    public static function can($perm, $userID = null)
    {
        if (null === $perm || '' === $perm) {
            return true;
        }
        $perms = self::getPermissions($userID);
        if (null === $perms) {
            return true;
        }
        if (in_array('*', $perms, true)
            || in_array($perm, $perms, true)
        ) {
            return true;
        }
        $node = strstr($perm, '.', true);
        return false !== $node
            && in_array("{$node}.*", $perms, true);
    }
    /**
     * Resolve a management page request to a required permission.
     *
     * Resolution order: node-independent sub override; exempt node ->
     * null; node alias; unregistered node -> null (allow, plugin
     * compatibility); explicit sub override; naming convention on the
     * base sub; fallback GET -> view, POST -> edit (a convention miss can
     * never let a view-only user write).
     *
     * @param string    $node   the page node (base value, e.g. 'host')
     * @param string    $sub    the base sub (without Ajax/Post suffix)
     * @param bool|null $isPost is this a state-changing POST request
     *                          (defaults to the actual request method)
     *
     * @return string|null the required permission, or null = no check
     */
    public static function resolvePagePermission($node, $sub, $isPost = null)
    {
        if (null === $isPost) {
            $isPost = 'POST' === ($_SERVER['REQUEST_METHOD'] ?? '');
        }
        $node = strtolower(trim((string)$node));
        $sub = strtolower(trim((string)$sub));
        $globals = self::GLOBAL_SUB_OVERRIDES;
        if (isset($globals[$sub])) {
            return $globals[$sub];
        }
        if ('' === $node || in_array($node, self::EXEMPT_NODES, true)) {
            return null;
        }
        $aliases = self::NODE_ALIASES;
        if (isset($aliases[$node])) {
            $node = $aliases[$node];
        }
        $registry = self::registry();
        if (!isset($registry[$node])) {
            return null;
        }
        $overrides = self::SUB_OVERRIDES;
        if (isset($overrides[$node][$sub])) {
            $override = $overrides[$node][$sub];
            if (is_array($override)) {
                return $override[$isPost ? 'POST' : 'GET'];
            }
            return $override;
        }
        return "{$node}." . self::_subToAction($sub, $isPost);
    }
    /**
     * Map a base sub name to an action by naming convention.
     *
     * @param string $sub    the lowercased base sub
     * @param bool   $isPost is this a state-changing POST request
     *
     * @return string one of view|create|edit|delete|task
     */
    private static function _subToAction($sub, $isPost)
    {
        foreach (['add', 'import', 'create', 'upload'] as $prefix) {
            if (0 === strpos($sub, $prefix)) {
                return 'create';
            }
        }
        if (0 === strpos($sub, 'delete')) {
            return 'delete';
        }
        foreach (['deploy', 'multicast', 'task'] as $prefix) {
            if (0 === strpos($sub, $prefix)) {
                return 'task';
            }
        }
        if (in_array($sub, ['wakeemup', 'clearpmtasks'], true)) {
            return 'task';
        }
        if ('' === $sub
            || in_array($sub, ['list', 'search', 'membership'], true)
            || 0 === strpos($sub, 'export')
            || 0 === strpos($sub, 'get')
        ) {
            return 'view';
        }
        return $isPost ? 'edit' : 'view';
    }
    /**
     * Enforce the permission for a management page request. Returns
     * silently when allowed; otherwise responds 403 JSON (AJAX) or
     * queues a flash message and redirects home (full page), and exits.
     *
     * @param string $node the page node (base value)
     * @param string $sub  the base sub (without Ajax/Post suffix)
     *
     * @return void
     */
    public static function requirePagePermission($node, $sub)
    {
        $perm = self::resolvePagePermission($node, $sub);
        if (self::can($perm)) {
            return;
        }
        if (self::$ajax) {
            http_response_code(HTTPResponseCodes::HTTP_FORBIDDEN);
            header('Content-Type: application/json');
            echo json_encode(
                ['error' => _('You do not have permission to perform this action.')]
            );
            exit;
        }
        self::setMessage(
            sprintf(
                _('You do not have permission to access this page (%s required).'),
                $perm
            ),
            _('Permission denied'),
            'warning'
        );
        self::redirect('?node=home');
    }
    /**
     * Resolve an API request to a required permission string.
     *
     * @param string $routeName the matched AltoRouter route name
     * @param string $class     the model class url parameter, if any
     *
     * @return string|null the required permission, null = no check
     */
    public static function resolveApiPermission($routeName, $class = '')
    {
        $routeName = (string)$routeName;
        if (array_key_exists($routeName, self::API_ROUTE_PERMISSIONS)) {
            return self::API_ROUTE_PERMISSIONS[$routeName];
        }
        if (!isset(self::API_ROUTE_ACTIONS[$routeName])) {
            // Unknown route (plugin-added): allow, matching the
            // unregistered-page compatibility stance.
            return null;
        }
        $class = strtolower(trim((string)$class));
        if (!isset(self::API_CLASS_ENTITIES[$class])) {
            // Unknown model class (plugin-added): allow.
            return null;
        }
        return self::API_CLASS_ENTITIES[$class]
            . '.'
            . self::API_ROUTE_ACTIONS[$routeName];
    }
    /**
     * Enforce a permission for an API request. Returns silently when
     * allowed; otherwise sends 403 and exits.
     *
     * @param string|null $perm the required permission (null = allowed)
     *
     * @return void
     */
    public static function requireApiPermission($perm)
    {
        if (self::can($perm)) {
            return;
        }
        Route::sendResponse(
            HTTPResponseCodes::HTTP_FORBIDDEN,
            _('You do not have permission to perform this action.')
        );
    }
    /**
     * Is the user unrestricted by object scope? Implicit administrators
     * (no role) and holders of the global '*' see every object; object
     * boundaries never apply to them.
     *
     * @param int|null $userID the user id (defaults to current user)
     *
     * @return bool
     */
    private static function _isUnrestricted($userID = null)
    {
        $perms = self::getPermissions($userID);
        if (null === $perms) {
            return true;
        }
        return in_array('*', $perms, true);
    }
    /**
     * Public view of _isUnrestricted for scope-enforcing plugins (Site) that
     * must let implicit admins / global '*' holders bypass list filtering.
     *
     * @param int|null $userID the user id (defaults to current user)
     *
     * @return bool
     */
    public static function isUnrestricted($userID = null)
    {
        return self::_isUnrestricted($userID);
    }
    /**
     * Is a specific object within the acting user's object scope?
     *
     * Object scope is an OPTIONAL boundary layered on top of the verb
     * permission. It has no built-in meaning: a plugin (currently Site)
     * enforces it by listening for OBJECT_SCOPE_CHECK and flipping
     * 'allowed' to false for objects outside the user's scope. With no
     * listener registered the boundary does not exist and every object is
     * in scope, so this is inert on a stock install.
     *
     * Unrestricted users (implicit admin / global '*') and requests with
     * no concrete single-object id (id < 1 — list, create, mass op) always
     * pass; scope is only meaningful for one existing object.
     *
     * @param string   $node   the page/api node (e.g. 'host')
     * @param int      $id     the target object id
     * @param int|null $userID acting user (defaults to current user)
     *
     * @return bool
     */
    public static function objectInScope($node, $id, $userID = null)
    {
        $id = (int)$id;
        if ($id < 1) {
            return true;
        }
        if (self::_isUnrestricted($userID)) {
            return true;
        }
        if (null === $userID) {
            $userID = (
                self::$FOGUser && self::$FOGUser->isValid() ?
                (int)self::$FOGUser->get('id') :
                0
            );
        }
        $allowed = true;
        self::$HookManager->processEvent(
            'OBJECT_SCOPE_CHECK',
            [
                'node' => strtolower(trim((string)$node)),
                'id' => $id,
                'userID' => (int)$userID,
                'allowed' => &$allowed
            ]
        );
        return (bool)$allowed;
    }
    /**
     * Enforce object scope for a management page request. Allowed →
     * returns silently. Denied → 403 JSON (AJAX) or a flash message plus
     * redirect home (full page), then exits. Mirrors the denial UX of
     * requirePagePermission.
     *
     * @param string $node the page node
     * @param int    $id   the target object id (0/none = skipped)
     *
     * @return void
     */
    public static function requirePageObjectScope($node, $id)
    {
        if (self::objectInScope($node, $id)) {
            return;
        }
        if (self::$ajax) {
            http_response_code(HTTPResponseCodes::HTTP_FORBIDDEN);
            header('Content-Type: application/json');
            echo json_encode(
                ['error' => _('You do not have permission to perform this action.')]
            );
            exit;
        }
        self::setMessage(
            _('You do not have permission to access this item.'),
            _('Permission denied'),
            'warning'
        );
        self::redirect('?node=home');
    }
    /**
     * Enforce object scope across a batch of ids (mass op). Airtight
     * stance: if ANY id is out of scope the whole request is denied — no
     * silent drop of the offending ids. The first out-of-scope id denies
     * and exits via requirePageObjectScope.
     *
     * @param string $node the page node
     * @param array  $ids  the target object ids
     *
     * @return void
     */
    public static function requirePageObjectScopeMass($node, $ids)
    {
        foreach ((array)$ids as $id) {
            self::requirePageObjectScope($node, $id);
        }
    }
    /**
     * Enforce object scope for an API request. Allowed → returns
     * silently. Denied → 403 and exit.
     *
     * @param string $class the model class url parameter
     * @param int    $id    the target object id (0/none = skipped)
     *
     * @return void
     */
    public static function requireApiObjectScope($class, $id)
    {
        if (self::objectInScope($class, $id)) {
            return;
        }
        Route::sendResponse(
            HTTPResponseCodes::HTTP_FORBIDDEN,
            _('You do not have permission to perform this action.')
        );
    }
    /**
     * Is there at least one effective administrator besides the excluded
     * users? An effective administrator is a user with no role at all
     * (implicit admin) or one holding the global '*' permission through
     * any role. Used by the lockout guards in role/user management.
     *
     * @param array $excludeUserIDs user ids to pretend do not exist
     *
     * @return bool
     */
    public static function effectiveAdminExists($excludeUserIDs = [])
    {
        return self::adminExistsGiven(['excludeUsers' => $excludeUserIDs]);
    }
    /**
     * Would at least one effective administrator remain after applying the
     * proposed changes? Simulates the change against current membership
     * and star-role state without touching the database, so every lockout
     * guard (role delete, permission edit, membership edit, user delete)
     * asks the same question the same way.
     *
     * Recognized keys in $changes (all optional):
     * - 'excludeUsers'     => [userID, ...] users to pretend do not exist
     *                         (user deletion).
     * - 'roleUsers'        => [roleID => [userID, ...]] proposed FULL
     *                         membership of specific roles.
     * - 'userRoles'        => [userID => [roleID, ...]] proposed FULL role
     *                         list of specific users.
     * - 'rolePermissions'  => [roleID => [perm, ...]] proposed FULL
     *                         permission list of specific roles
     *                         (memberships unchanged).
     * - 'removeRoles'      => [roleID, ...] roles about to be deleted —
     *                         their assocs vanish, so members may fall
     *                         back to implicit admin.
     * - 'groupUsers'       => [groupID => [userID, ...]] proposed FULL
     *                         membership of specific groups.
     * - 'userGroups'       => [userID => [groupID, ...]] proposed FULL
     *                         group list of specific users.
     * - 'groupRoles'       => [groupID => [roleID, ...]] proposed FULL role
     *                         list of specific groups.
     * - 'removeGroups'     => [groupID, ...] groups about to be deleted —
     *                         their memberships and role assignments vanish.
     *
     * A user is an effective administrator when they hold '*' — or no role
     * at all — counting roles reached both directly and through any group
     * they belong to.
     *
     * @param array $changes the proposed changes (see above)
     *
     * @return bool
     */
    public static function adminExistsGiven($changes = [])
    {
        $types = [];
        self::$HookManager->processEvent(
            'USER_TYPES_FILTER',
            ['types' => &$types]
        );
        $allUsers = array_map('intval', (array)Route::getIds('user'));
        $special = (count($types)
            ? array_map(
                'intval',
                (array)Route::getIds('user', ['type' => $types])
            )
            : []);
        $exclude = array_map(
            'intval',
            (array)($changes['excludeUsers'] ?? [])
        );
        $users = array_diff($allUsers, $special, $exclude);
        // Direct membership as roleID => [userIDs].
        $membership = [];
        $rows = self::$DB
            ->query('SELECT `ruaRoleID`, `ruaUserID` FROM `roleUserAssoc`')
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $membership[(int)$row['ruaRoleID']][] = (int)$row['ruaUserID'];
        }
        // Group membership as groupID => [userIDs].
        $groupMembers = [];
        $rows = self::$DB
            ->query('SELECT `ugmGroupID`, `ugmUserID` FROM `userGroupMembers`')
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $groupMembers[(int)$row['ugmGroupID']][] = (int)$row['ugmUserID'];
        }
        // Group role assignments as groupID => [roleIDs].
        $groupRoles = [];
        $rows = self::$DB
            ->query('SELECT `rugGroupID`, `rugRoleID` FROM `roleUserGroupAssoc`')
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $groupRoles[(int)$row['rugGroupID']][] = (int)$row['rugRoleID'];
        }
        $starRoles = array_map(
            'intval',
            (array)Route::getIds('rolepermission', ['name' => '*'], 'roleID')
        );
        // Apply direct role<->user deltas.
        foreach ((array)($changes['roleUsers'] ?? []) as $rid => $uids) {
            $membership[(int)$rid] = array_map('intval', (array)$uids);
        }
        foreach ((array)($changes['userRoles'] ?? []) as $uid => $rids) {
            $uid = (int)$uid;
            foreach ($membership as $rid => &$uids2) {
                $uids2 = array_values(array_diff($uids2, [$uid]));
            }
            unset($uids2);
            foreach ((array)$rids as $rid) {
                $membership[(int)$rid][] = $uid;
            }
        }
        // Apply group<->user membership deltas.
        foreach ((array)($changes['groupUsers'] ?? []) as $gid => $uids) {
            $groupMembers[(int)$gid] = array_map('intval', (array)$uids);
        }
        foreach ((array)($changes['userGroups'] ?? []) as $uid => $gids) {
            $uid = (int)$uid;
            foreach ($groupMembers as $gid => &$uids2) {
                $uids2 = array_values(array_diff($uids2, [$uid]));
            }
            unset($uids2);
            foreach ((array)$gids as $gid) {
                $groupMembers[(int)$gid][] = $uid;
            }
        }
        // Apply group<->role deltas.
        foreach ((array)($changes['groupRoles'] ?? []) as $gid => $rids) {
            $groupRoles[(int)$gid] = array_map('intval', (array)$rids);
        }
        foreach ((array)($changes['rolePermissions'] ?? []) as $rid => $perms) {
            $rid = (int)$rid;
            $starRoles = array_values(array_diff($starRoles, [$rid]));
            if (in_array('*', (array)$perms, true)) {
                $starRoles[] = $rid;
            }
        }
        foreach ((array)($changes['removeRoles'] ?? []) as $rid) {
            $rid = (int)$rid;
            unset($membership[$rid]);
            $starRoles = array_values(array_diff($starRoles, [$rid]));
            // A deleted role also vanishes from every group that held it.
            foreach ($groupRoles as $gid => &$rids2) {
                $rids2 = array_values(array_diff($rids2, [$rid]));
            }
            unset($rids2);
        }
        foreach ((array)($changes['removeGroups'] ?? []) as $gid) {
            $gid = (int)$gid;
            unset($groupMembers[$gid], $groupRoles[$gid]);
        }
        // Fold group-sourced roles into effective membership: every member
        // of a group inherits that group's roles.
        foreach ($groupRoles as $gid => $rids) {
            $members = $groupMembers[$gid] ?? [];
            if (!count($members) || !count($rids)) {
                continue;
            }
            foreach ($rids as $rid) {
                $membership[$rid] = array_merge(
                    $membership[$rid] ?? [],
                    $members
                );
            }
        }
        $rolled = [];
        foreach ($membership as $uids2) {
            $rolled = array_merge($rolled, $uids2);
        }
        if (count(array_diff($users, $rolled))) {
            // A role-less user remains: implicit administrator.
            return true;
        }
        foreach ($starRoles as $rid) {
            if (count(array_intersect($membership[$rid] ?? [], $users))) {
                return true;
            }
        }
        return false;
    }
    /**
     * Reset the per-request caches (used after role/permission writes so
     * subsequent checks in the same request see the new state).
     *
     * @return void
     */
    public static function resetCache()
    {
        self::$_permCache = [];
        self::$_registry = null;
    }
    /**
     * Remove every rolePermissions row belonging to a node namespace.
     *
     * Called when a plugin is uninstalled so its permission strings do
     * not linger in roles (and reappear as orphaned entries in the role
     * permission matrix) after the node is gone from the registry.
     * Matches the bare node, its wildcard and every scoped action:
     * 'site', 'site.*', 'site.view', ... but never a sibling like
     * 'siteother' (the dot boundary is required).
     *
     * @param string $nodePrefix the registry node (e.g. 'site')
     *
     * @return void
     */
    public static function purgePermissions($nodePrefix)
    {
        $nodePrefix = strtolower(trim((string)$nodePrefix));
        if ('' === $nodePrefix) {
            return;
        }
        $names = array_values(
            array_unique(
                array_filter(
                    (array)Route::getIds('rolepermission', [], 'name')
                )
            )
        );
        $match = [];
        foreach ($names as $name) {
            if ($name === $nodePrefix
                || 0 === strpos($name, $nodePrefix . '.')
            ) {
                $match[] = $name;
            }
        }
        if (count($match)) {
            Route::deletemass('rolepermission', ['name' => $match]);
        }
        self::resetCache();
    }
}
