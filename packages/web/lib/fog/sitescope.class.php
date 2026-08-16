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
 *   2. If NO sites exist at all, everything is in scope. Redundant once
 *      schema step 333 has created the catch-all -- and that is exactly why
 *      it is here. Between deploying new code and running the schema
 *      updater a server has this class and an empty (or absent) `sites`
 *      table, and endpoints that do not route through the updater gate
 *      would otherwise deny every non-'*' user everything. A server with a
 *      pending schema deploy must behave like today, not lock its admin
 *      out of the page they need in order to fix it.
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
     * Per-request cache of "does any site exist". Null until asked.
     *
     * @var bool|null
     */
    private static $_anySites = null;
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
        self::$_anySites = null;
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
     * Does this install have any sites at all?
     *
     * @return bool
     */
    public static function anySitesExist()
    {
        if (null === self::$_anySites) {
            self::$_anySites = self::_count(
                'SELECT COUNT(*) AS `cnt` FROM `sites`'
            ) > 0;
        }
        return self::$_anySites;
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
        if (!self::anySitesExist()) {
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
        if (!self::anySitesExist() || self::isUnscoped($userID)) {
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
