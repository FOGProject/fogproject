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
        // A LEFT JOIN keeps a row for a role that grants zero permissions
        // (rpName NULL) so having-a-role is distinguishable from having
        // no role at all.
        $sql = 'SELECT `rpName` '
            . 'FROM `roleUserAssoc` '
            . 'LEFT JOIN `rolePermissions` ON `rpRoleID` = `ruaRoleID` '
            . 'WHERE `ruaUserID` = :userid';
        $rows = self::$DB
            ->query($sql, [], ['userid' => $userID])
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        if (!is_array($rows) || count($rows) < 1) {
            // Zero role assignments = implicit administrator.
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
        $special = array_map(
            'intval',
            (array)Route::getIds('user', ['types' => $types])
        );
        $exclude = array_map(
            'intval',
            (array)($changes['excludeUsers'] ?? [])
        );
        $users = array_diff($allUsers, $special, $exclude);
        // Current membership as roleID => [userIDs].
        $membership = [];
        $rows = self::$DB
            ->query('SELECT `ruaRoleID`, `ruaUserID` FROM `roleUserAssoc`')
            ->fetch(PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        foreach ((array)$rows as $row) {
            $membership[(int)$row['ruaRoleID']][] = (int)$row['ruaUserID'];
        }
        $starRoles = array_map(
            'intval',
            (array)Route::getIds('rolepermission', ['name' => '*'], 'roleID')
        );
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
        foreach ((array)($changes['rolePermissions'] ?? []) as $rid => $perms) {
            $rid = (int)$rid;
            $starRoles = array_values(array_diff($starRoles, [$rid]));
            if (in_array('*', (array)$perms, true)) {
                $starRoles[] = $rid;
            }
        }
        foreach ((array)($changes['removeRoles'] ?? []) as $rid) {
            unset($membership[(int)$rid]);
            $starRoles = array_values(array_diff($starRoles, [(int)$rid]));
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
}
