<?php
/**
 * Resolves whether an object is inside a user's site scope.
 *
 * PHP version 5
 *
 * @category SiteScope
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Resolves whether an object is inside a user's site scope.
 *
 * The decision half of sites, kept apart from the `sites` row itself. A
 * model is a row with accessors; this is policy, and policy is what
 * Authorization asks for. Splitting them also means this can land and be
 * tested while the site plugin still declares its own Site class -- the
 * autoloader maps a lowercased basename, so core cannot own the name Site
 * until the plugin release that gives it up ships alongside.
 *
 * DENY-ALL is the posture. A user in one or more sites sees only what those
 * sites contain. Two short circuits sit in front of that, and both are
 * load-bearing rather than convenience:
 *
 *   1. A member of a CATCH-ALL site is in scope for everything. That is what
 *      "no restriction" is, and it is a flag on the site rather than a
 *      membership list precisely so that a host registered tomorrow is
 *      covered by it too. An enumerated list would look identical on
 *      upgrade day and then quietly stop containing new objects.
 *
 *   2. If no site is IN USE -- meaning no site exists other than the
 *      catch-all -- everything is in scope. Two states share that answer
 *      and both need it:
 *
 *        - the window between deploying this code and running the schema
 *          updater, where `sites` is empty or absent. Endpoints that do
 *          not route through the updater gate would otherwise deny every
 *          non-'*' user everything, and a server with a pending schema
 *          deploy must behave like today rather than lock its admin out of
 *          the page that fixes it.
 *        - a server that migrated and then never created a site. Step 333
 *          always creates the catch-all, so counting rows would answer
 *          "sites exist" forever, on every install in the world, including
 *          ones that never had the plugin. Scoping would be switched on
 *          for a feature nobody turned on -- and the first account created
 *          after the upgrade, which nothing adds to the catch-all, would
 *          be denied everything while every account that existed the day
 *          before kept working.
 *
 *      So the question this asks is "is anybody actually using sites",
 *      not "does the table have rows in it".
 *
 * A node this does not scope is always in scope. Images, snapins and
 * printers are deliberately not scoped: each needs its own answer to what a
 * catch-all means for a shared image, and its own table. Tasks ARE scoped,
 * because a task is not a new axis -- it is derived from the host it runs
 * against, so it needs no table and no migration.
 *
 * Queries are written here rather than going through Route::getIds(). Same
 * reasoning as Authorization::rolesHolding(): this is the query whose wrong
 * answer hands a user another site's hosts, so it owns its own SQL rather
 * than depending on the filter builder's wildcard handling continuing to
 * treat these values as scalars. It also keeps the membership tables out of
 * Route::$validClasses, which would publish four REST endpoints nothing
 * asks for.
 *
 * @category SiteScope
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SiteScope extends FOGBase
{
    /**
     * Scoped node => [membership table, site column, object column].
     *
     * A node absent from this map is not scoped and is always allowed.
     *
     * @var array
     */
    private static $_nodes = [
        'host' => ['siteHostMembers', 'shmSiteID', 'shmHostID'],
        'user' => ['siteUserMembers', 'sumSiteID', 'sumUserID'],
        'group' => ['siteGroupMembers', 'sgmSiteID', 'sgmGroupID'],
        'usergroup' => [
            'siteUserGroupMembers',
            'sugmSiteID',
            'sugmUserGroupID'
        ],
    ];
    /**
     * Per-request cache of site ids by user id.
     *
     * @var array
     */
    private static $_userSites = [];
    /**
     * Per-request cache of catch-all membership by user id.
     *
     * @var array
     */
    private static $_catchAll = [];
    /**
     * Per-request cache of "is any site in use". Null until asked.
     *
     * @var bool|null
     */
    private static $_sitesInUse = null;
    /**
     * Per-request cache of the catch-all site id. Null until asked, 0 when
     * there is not one.
     *
     * @var int|null
     */
    private static $_catchAllID = null;
    /**
     * Drops the per-request caches.
     *
     * Web requests are short enough that the caches never go stale, but the
     * CLI daemons are not: FOGPluginRunner and the schedulers run for days,
     * and a site created in the UI would otherwise never be seen by them.
     * Also what the tests call between fixtures.
     *
     * @return void
     */
    public static function forgetCaches()
    {
        self::$_userSites = [];
        self::$_catchAll = [];
        self::$_sitesInUse = null;
        self::$_catchAllID = null;
    }
    /**
     * Runs a scalar count, treating an unusable table as zero.
     *
     * A missing `sites` table is not an error here, it is the pre-migration
     * state described in the class docblock, and it has to answer "none"
     * rather than throw -- a fatal in a scope check is a bodyless 500 on
     * every page at once.
     *
     * @param string $sql    the query, returning a column aliased cnt
     * @param array  $params bound parameters
     *
     * @return int
     */
    private static function _count($sql, $params = [])
    {
        try {
            $res = self::$DB->query($sql, [], $params);
            if (false !== $res->error) {
                return 0;
            }
            $row = $res->fetch(\PDO::FETCH_ASSOC)->get();
        } catch (\Exception $e) {
            return 0;
        }
        return is_array($row) && isset($row['cnt']) ? (int)$row['cnt'] : 0;
    }
    /**
     * Is this install actually using sites?
     *
     * The catch-all is excluded deliberately and is the whole point of the
     * method: it is created by the schema updater on every server, so a
     * plain row count answers yes on installs that have never seen a site
     * in their lives. See the class docblock.
     *
     * @return bool
     */
    public static function sitesInUse()
    {
        if (null === self::$_sitesInUse) {
            self::$_sitesInUse = self::_count(
                'SELECT COUNT(*) AS `cnt` FROM `sites` '
                . 'WHERE `siteCatchAll` IS NULL'
            ) > 0;
        }
        return self::$_sitesInUse;
    }
    /**
     * The catch-all site's id, or 0 if this install has not got one.
     *
     * `siteCatchAll` is UNIQUE and CHECK-constrained to 1, so at most one
     * row can ever answer this -- the LIMIT is belt and braces, not a
     * choice between candidates.
     *
     * @return int
     */
    public static function catchAllID()
    {
        if (null === self::$_catchAllID) {
            self::$_catchAllID = self::_count(
                'SELECT `siteID` AS `cnt` FROM `sites` '
                . 'WHERE `siteCatchAll` IS NOT NULL LIMIT 1'
            );
        }
        return self::$_catchAllID;
    }
    /**
     * Puts a user in the catch-all site, if there is one.
     *
     * Exists because a user created after the migration would otherwise be
     * the only account on the server in no site at all. Step 333 enrolled
     * every account that existed on upgrade day; this is the same rule
     * applied to the ones created afterwards, and it lives here rather than
     * in each of the four creation paths (the add page, the add modal, a
     * REST POST, and the ldap plugin's auto-provision on first login)
     * because only the last of those involves anybody clicking anything.
     *
     * Callers gate on sitesInUse(): once real sites exist, which site a new
     * user belongs to is an administrative decision and defaulting it to
     * "all of them" would be an access-control decision made by accident.
     *
     * INSERT IGNORE rather than a check-then-insert: the pair is UNIQUE, so
     * the database already answers "already a member" without a round trip.
     *
     * @param int $userID the user id
     *
     * @return bool whether the user is now in the catch-all
     */
    public static function joinCatchAll($userID)
    {
        $userID = (int)$userID;
        $siteID = self::catchAllID();
        if ($userID < 1 || $siteID < 1) {
            return false;
        }
        try {
            $res = self::$DB->query(
                'INSERT IGNORE INTO `siteUserMembers` '
                . '(`sumSiteID`, `sumUserID`) VALUES (:sid, :uid)',
                [],
                ['sid' => $siteID, 'uid' => $userID]
            );
            if (false !== $res->error) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        // The membership caches now describe a world one row out of date,
        // and this user's own request continues after the save.
        self::forgetCaches();
        return true;
    }
    /**
     * Is this user a member of a catch-all site?
     *
     * @param int $userID the acting user id
     *
     * @return bool
     */
    public static function isUnscoped($userID)
    {
        $userID = (int)$userID;
        if (!array_key_exists($userID, self::$_catchAll)) {
            self::$_catchAll[$userID] = self::_count(
                'SELECT COUNT(*) AS `cnt` FROM `siteUserMembers` `m` '
                . 'INNER JOIN `sites` `s` ON `s`.`siteID` = `m`.`sumSiteID` '
                . 'WHERE `m`.`sumUserID` = :uid '
                . 'AND `s`.`siteCatchAll` IS NOT NULL',
                ['uid' => $userID]
            ) > 0;
        }
        return self::$_catchAll[$userID];
    }
    /**
     * The site ids a user belongs to. Empty means deny-all, not allow-all.
     *
     * Direct membership only, matching what the plugin enforced. This is
     * deliberately the ONLY place that answers "which sites is this user
     * in", so that inherited membership -- via a user group, or via a role
     * -- is one more UNION arm here and not a second rule somewhere else.
     * Both joins already exist (`userGroupMembers`, `roleUserAssoc`); what
     * is missing is the site-side edge, and adding one is a schema step
     * plus an arm in this query. Nothing else in this class changes,
     * because everything downstream consumes the list this returns.
     *
     * Note that inherited membership only ever ADDS sites. Union semantics
     * are what make "most open wins" true by construction rather than by a
     * precedence rule somebody has to remember.
     *
     * @param int $userID the acting user id
     *
     * @return array int site ids
     */
    public static function userSiteIDs($userID)
    {
        $userID = (int)$userID;
        if (array_key_exists($userID, self::$_userSites)) {
            return self::$_userSites[$userID];
        }
        $ids = [];
        try {
            $res = self::$DB->query(
                'SELECT `sumSiteID` FROM `siteUserMembers` '
                . 'WHERE `sumUserID` = :uid',
                [],
                ['uid' => $userID]
            );
            if (false === $res->error) {
                $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
                foreach ((array)$rows as $row) {
                    $ids[] = (int)$row['sumSiteID'];
                }
            }
        } catch (\Exception $e) {
            $ids = [];
        }
        return self::$_userSites[$userID] = $ids;
    }
    /**
     * The host a task runs against, or 0 if there is not one.
     *
     * A task carries no site of its own -- it belongs to whichever site its
     * host is in. Deriving it here means task views are scoped with no
     * extra table, no migration and no second place for the rule to drift.
     *
     * @param int $id the task id
     *
     * @return int the host id, or 0
     */
    private static function _taskHostID($id)
    {
        return self::_count(
            'SELECT `taskHostID` AS `cnt` FROM `tasks` WHERE `taskID` = :tid',
            ['tid' => (int)$id]
        );
    }
    /**
     * Is this node subject to a site boundary at all?
     *
     * Public so the single-object and list paths can agree on the answer
     * rather than each keeping a copy of the node list.
     *
     * @param string $node the node
     *
     * @return bool
     */
    public static function isScopedNode($node)
    {
        $node = strtolower(trim((string)$node));
        return isset(self::$_nodes[$node]) || 'task' === $node;
    }
    /**
     * Every object id of a node that lies in the user's sites.
     *
     * The list counterpart of inScope(). Callers use it to push the
     * boundary into the query that builds a list, rather than fetching a
     * page and discarding rows afterwards -- discarding rows leaves the
     * row COUNTS describing objects the user cannot see, which both looks
     * broken and tells them how much exists outside their scope.
     *
     * Returns [] for a user with no sites, which is deny-all and must be
     * passed on as such rather than read as "no filter".
     *
     * @param string $node   the node (host|user|group|usergroup)
     * @param int    $userID the acting user id
     *
     * @return array int object ids
     */
    public static function allInScopeIDs($node, $userID)
    {
        $node = strtolower(trim((string)$node));
        if (!self::isScopedNode($node)) {
            return [];
        }
        $siteIDs = self::userSiteIDs($userID);
        if (empty($siteIDs)) {
            return [];
        }
        if ('task' === $node) {
            // Tasks have no membership table of their own; they inherit
            // the site of the host they run against. Done as one join
            // rather than by resolving each task, because this feeds a
            // list and a per-row lookup here is the grid query storm.
            $sql = sprintf(
                'SELECT DISTINCT `taskID` AS `oid` FROM `tasks` '
                . 'WHERE `taskHostID` IN ('
                . 'SELECT `shmHostID` FROM `siteHostMembers` '
                . 'WHERE `shmSiteID` IN (%s))',
                implode(',', $siteIDs)
            );
        } else {
            list($table, $siteCol, $objCol) = self::$_nodes[$node];
            $sql = sprintf(
                'SELECT DISTINCT `%s` AS `oid` FROM `%s` WHERE `%s` IN (%s)',
                $objCol,
                $table,
                $siteCol,
                implode(',', $siteIDs)
            );
        }
        $ids = [];
        try {
            $res = self::$DB->query($sql);
            if (false === $res->error) {
                $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
                foreach ((array)$rows as $row) {
                    $ids[] = (int)$row['oid'];
                }
            }
        } catch (\Exception $e) {
            // Unreadable membership cannot be read as permission.
            return [];
        }
        return $ids;
    }
    /**
     * Is a single object within a user's site scope?
     *
     * Order matters here and is not arbitrary. The two allow-everything
     * short circuits are checked BEFORE the task lookup, so that a server
     * mid-upgrade and a catch-all user both answer without touching the
     * database more than once -- and so that "no sites yet means behave
     * like today" holds for tasks as well as hosts.
     *
     * @param string $node   the node (host|user|group|usergroup|task)
     * @param int    $id     the object id
     * @param int    $userID the acting user id
     *
     * @return bool
     */
    public static function inScope($node, $id, $userID)
    {
        $node = strtolower(trim((string)$node));
        $id = (int)$id;
        if (!isset(self::$_nodes[$node]) && 'task' !== $node) {
            return true;
        }
        if (!self::sitesInUse()) {
            return true;
        }
        if (self::isUnscoped($userID)) {
            return true;
        }
        if ('task' === $node) {
            $id = self::_taskHostID($id);
            // A task that does not exist, or whose host is gone, cannot be
            // SHOWN to be in scope, and unprovable is denied. Allowing it
            // would leave a deleted host's tasks visible to everyone.
            if ($id < 1) {
                return false;
            }
            $node = 'host';
        }
        $siteIDs = self::userSiteIDs($userID);
        if (empty($siteIDs)) {
            return false;
        }
        list($table, $siteCol, $objCol) = self::$_nodes[$node];
        return self::_count(
            sprintf(
                'SELECT COUNT(*) AS `cnt` FROM `%s` '
                . 'WHERE `%s` = :oid AND `%s` IN (%s)',
                $table,
                $objCol,
                $siteCol,
                implode(',', $siteIDs)
            ),
            ['oid' => $id]
        ) > 0;
    }
    /**
     * Narrows a list of object ids to those within a user's site scope.
     *
     * One query rather than one per id, because the callers are list pages
     * and a per-row check is how the grid query-storm bugs happened.
     *
     * @param string $node   the node (host|user|group|usergroup|task)
     * @param array  $ids    candidate object ids
     * @param int    $userID the acting user id
     *
     * @return array int ids in scope, in the order given
     */
    public static function filterInScope($node, $ids, $userID)
    {
        $ids = array_values(
            array_unique(array_map('intval', (array)$ids))
        );
        $node = strtolower(trim((string)$node));
        if (!isset(self::$_nodes[$node]) && 'task' !== $node) {
            return $ids;
        }
        if (!self::sitesInUse() || self::isUnscoped($userID)) {
            return $ids;
        }
        if (empty($ids)) {
            return [];
        }
        $siteIDs = self::userSiteIDs($userID);
        if (empty($siteIDs)) {
            return [];
        }
        // Tasks are filtered one at a time through inScope(), which
        // resolves each to its host. A list of tasks is a list of tasks
        // being ACTED on -- cancel, restart -- and is bounded by what the
        // user selected, unlike a host grid page.
        if ('task' === $node) {
            $kept = [];
            foreach ($ids as $id) {
                if (self::inScope('task', $id, $userID)) {
                    $kept[] = $id;
                }
            }
            return $kept;
        }
        list($table, $siteCol, $objCol) = self::$_nodes[$node];
        $inScope = [];
        try {
            $res = self::$DB->query(
                sprintf(
                    'SELECT DISTINCT `%s` AS `oid` FROM `%s` '
                    . 'WHERE `%s` IN (%s) AND `%s` IN (%s)',
                    $objCol,
                    $table,
                    $objCol,
                    implode(',', $ids),
                    $siteCol,
                    implode(',', $siteIDs)
                )
            );
            if (false === $res->error) {
                $rows = $res->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
                foreach ((array)$rows as $row) {
                    $inScope[] = (int)$row['oid'];
                }
            }
        } catch (\Exception $e) {
            // Unreadable membership cannot be read as permission.
            return [];
        }
        return array_values(array_intersect($ids, $inScope));
    }
}
