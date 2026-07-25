<?php
/**
 * LDAPPluginHook enables our checks as required
 *
 * PHP version 5
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * LDAPPluginHook enables our checks as required
 *
 * @category LDAPPluginHook
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LDAPPluginHook extends Hook
{
    /**
     * Legacy uType sentinels. No longer written -- retained only to read
     * rows created before provenance moved to uAuthSource: setLdapType()
     * and LDAPManager::backfillIdentities().
     */
    const LDAP_ADMIN = '990';
    const LDAP_MOBILE = '991';
    /**
     * Stamped on users.uAuthSource so core knows this account is
     * externally authenticated and must not fall back to implicit
     * administrator or to a local password compare.
     */
    const AUTH_SOURCE = 'ldap';
    /**
     * The ordered access tiers (TIER_NONE/NOMATCH/USER/ADMIN) are gone.
     *
     * They existed because authLDAP() returned a scalar access level, so
     * two servers answering for the same user had to be reconciled by
     * ranking them. Now that real group membership is available the
     * question does not arise: RBAC permissions are additive, so every
     * group the user matched on every server contributes its target and
     * nothing has to outrank anything.
     *
     * The one case that still needs a single configured answer -- a server
     * with group matching switched off, which cannot enumerate groups --
     * is handled inline in checkAddUser() by
     * FOG_PLUGIN_LDAP_NOMATCH_ROLE.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     */
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'LDAPPluginHook';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'LDAP Hook';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact upon.
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
        if (!in_array($this->node, self::$pluginsinstalled)) {
            return;
        }
        // FOG_PLUGIN_LDAP_USER_FILTER is gone. It existed to answer "which
        // existing user rows does this plugin own?" by listing the uType
        // sentinels it wrote (990,991), which made the answer an
        // admin-editable, API-writable list of magic numbers on a column
        // shared with every other consumer of uType. uAuthSource answers
        // the same question directly and cannot be confused with another
        // provider's rows, so the setting has no job left -- see
        // checkAddUser() and LDAPManager::uninstall().
        //
        // USER_TYPES_FILTER, USER_TYPE_VALID and USER_LOGGING_OUT are no
        // longer registered. All three defended the pre-RBAC two-tier model
        // and under roles they combined into "an LDAP user can never hold a
        // role":
        //  - USER_TYPES_FILTER made Role::loadUsers() and
        //    UserGroup::loadUsers() array_diff LDAP users out of their
        //    membership lists, so the next save of any role deleted their
        //    roleUserAssoc rows -- including rows nobody had touched.
        //  - USER_LOGGING_OUT destroyed the user row outright, taking its
        //    roles with it, and only fired on an explicit logout anyway.
        //  - USER_TYPE_VALID (isLdapType) was dead: USER_TYPE_HOOK rewrites
        //    990/991 to 0/1 one block earlier, so it only ever saw the
        //    rewritten value. Core's authsource check replaces it.
        // This plugin is the only registrant of all three, so the core
        // firings simply go inert.
        self::$HookManager->register(
            'USER_LOGGING_IN',
            [$this, 'checkAddUser']
        )->register(
            'USER_TYPE_HOOK',
            [$this, 'setLdapType']
        );
    }
    /**
     * Checks and creates users if they're valid
     *
     * @param mixed $arguments the item to adjust
     *
     * @throws Exception
     * @return void
     */
    public function checkAddUser($arguments)
    {
        $user = trim($arguments['username']);
        $pass = trim($arguments['password']);
        /**
         * An existing row that this plugin does not own is left alone --
         * a local account named the same as a directory account must not
         * be hijacked into an LDAP login, and neither must a row belonging
         * to some other auth provider.
         *
         * Ownership is the provenance stamp, not the uType sentinels this
         * used to read. Rows created before the stamp existed are stamped
         * by LDAPManager::backfillIdentities(), which runs from this
         * plugin's own migration list -- so the two always ship together
         * and a legacy row is never left unrecognised.
         *
         * A row that does not exist yet falls through, which is what
         * creates the account on a directory user's first login.
         */
        $tmpUser = $arguments['user']
            ->set('name', $user)
            ->load('name');
        if ($tmpUser->isValid()
            && self::AUTH_SOURCE !== trim((string)$tmpUser->get('authsource'))
        ) {
            return;
        }
        /**
         * Create our new user (initially at least)
         */
        Route::listem('ldap');
        $items = json_decode(
            Route::getData()
        );
        /**
         * Authenticate against every configured LDAP server and union what
         * each one grants. Every server is asked, because a user can be
         * present in more than one directory and each contributes its own
         * group matches -- there is no "best" answer to stop early on.
         *
         * A server where the user is absent contributes nothing and must
         * not take anything away from a server where they are present,
         * which is why the accumulators only ever grow.
         *
         * A server with group matching switched off cannot enumerate
         * groups, so it can only say "this account binds". That earns the
         * single configured fallback role and nothing more -- it means "we
         * could not check what this account is", not "this account is an
         * administrator".
         */
        $authenticated = false;
        $roleIds = [];
        $groupIds = [];
        $displayName = '';
        $ldapAPI = 0;
        foreach ($items->data as $ldap) {
            $LDAP = self::getClass('LDAP', $ldap->id);
            $matched = $LDAP->authLDAP($user, $pass);
            if (false === $matched) {
                continue;
            }
            /**
             * Display name and API access come from the first server that
             * accepted the credential. With the tier ladder gone there is
             * no "most privileged" server to prefer, and OR-ing allowapi
             * across servers would let a server that grants nothing else
             * still hand out API access.
             */
            if (!$authenticated) {
                $displayName = $LDAP->getDisplayName($user, $pass);
                $ldapAPI = $LDAP->get('allowapi');
            }
            $authenticated = true;
            if (!$LDAP->get('useGroupMatch')) {
                $nomatch = trim(
                    (string)self::getSetting('FOG_PLUGIN_LDAP_NOMATCH_ROLE')
                );
                if ('' !== $nomatch) {
                    $roleIds[] = $nomatch;
                }
                continue;
            }
            $targets = self::_targetsForGroups(
                (int)$LDAP->get('id'),
                (array)$matched
            );
            $roleIds = array_merge($roleIds, $targets['roles']);
            $groupIds = array_merge($groupIds, $targets['usergroups']);
        }
        if (!$authenticated) {
            $arguments['user'] = new User(-1);
            return;
        }
        // Rows this plugin created before it stopped storing directory
        // passwords still hold a bcrypt hash of the user's real password.
        // Overwrite uPass once, on the first login after the upgrade, and
        // leave it alone thereafter -- hashing a fresh token on every login
        // would burn a bcrypt round per sign-in for no benefit.
        $needsPassword = (
            !$tmpUser->isValid()
            || self::AUTH_SOURCE !== $tmpUser->get('authsource')
        );
        // uType is no longer written. It carried two meanings at once --
        // "this plugin owns the row" and "this account is an admin" -- and
        // both have moved: provenance is authsource, authorization is the
        // role assigned below. Writing a sentinel nothing reads would only
        // invite something to start reading it again. Rows created before
        // this change keep their 990/991; nothing consults it.
        $tmpUser
            ->set('name', $user)
            ->set('display', $displayName)
            ->set('api', $ldapAPI)
            ->set('authsource', self::AUTH_SOURCE);
        if ($needsPassword) {
            // Never the directory password. The account is authenticated by
            // the vouch below, so uPass only has to be something no typed
            // password can ever match.
            $tmpUser->set('password', self::getToken(64));
        }
        $this->_syncTargets($tmpUser, $roleIds, $groupIds);
        $tmpUser->save();
        // After the save: a first-time user has no id until then.
        self::_recordGrants($tmpUser, $roleIds, $groupIds);
        $arguments['user'] = $tmpUser;
        // Tell core we have already proven this identity, so it skips the
        // local password compare instead of us having to make one succeed.
        $arguments['authenticated'] = true;
    }
    /**
     * The roles and user groups a set of matched groups grants.
     *
     * One query per target kind, joining the group table to its
     * association table, rather than one query per group.
     *
     * Raw bound SQL rather than Route::getIds() on purpose: _buildSql()
     * turns '*' and '+' in a scalar filter value into a SQL LIKE wildcard,
     * and both are legal in an LDAP group name ('+' separates the parts of
     * a multi-valued RDN), so a group named "Techs+Chicago" would match
     * mappings it must not.
     *
     * @param int   $serverId the LDAP server the names came from
     * @param array $groups   the matched directory group names
     *
     * @return array ['roles' => [...], 'usergroups' => [...]]
     */
    private static function _targetsForGroups($serverId, array $groups)
    {
        $out = [
            'roles' => [],
            'usergroups' => []
        ];
        $groups = array_values(
            array_filter(
                array_map('trim', $groups),
                'strlen'
            )
        );
        if (empty($groups)) {
            return $out;
        }
        $names = [];
        $binds = ['server' => (int)$serverId];
        foreach ($groups as $index => $group) {
            $key = 'g' . $index;
            $names[] = ':' . $key;
            $binds[$key] = $group;
        }
        $in = implode(',', $names);
        $queries = [
            'roles' => 'SELECT `lgraRoleID` AS `target` '
                . 'FROM `ldapGroupRoleAssoc` '
                . 'INNER JOIN `LDAPGroups` ON `lgID` = `lgraGroupID` '
                . 'WHERE `lgServerID` = :server AND `lgName` IN (' . $in . ')',
            'usergroups' => 'SELECT `lgugUserGroupID` AS `target` '
                . 'FROM `ldapGroupUserGroupAssoc` '
                . 'INNER JOIN `LDAPGroups` ON `lgID` = `lgugGroupID` '
                . 'WHERE `lgServerID` = :server AND `lgName` IN (' . $in . ')'
        ];
        foreach ($queries as $kind => $sql) {
            $out[$kind] = self::_targetIds($sql, $binds);
        }
        return $out;
    }
    /**
     * Runs a target-id query and returns the ids it produced.
     *
     * @param string $sql   the query to run
     * @param array  $binds the bound values
     *
     * @return array
     */
    private static function _targetIds($sql, array $binds)
    {
        try {
            $rows = self::$DB
                ->query($sql, [], $binds)
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            error_log(
                sprintf(
                    '%s %s() %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Could not read the group mappings'),
                    $e->getMessage()
                )
            );
            return [];
        }
        /**
         * PDODB reports a failed query as false rather than throwing
         * (throwOnQueryError is off), and (array)false is [false], not [].
         */
        if (!is_array($rows)) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = trim((string)($row['target'] ?? ''));
            if ('' !== $id && '0' !== $id) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }
    /**
     * What this plugin previously granted one user.
     *
     * @param int $userId the user being authenticated
     *
     * @return array ['roles' => [...], 'usergroups' => [...]]
     */
    private static function _priorGrants($userId)
    {
        $out = [
            'roles' => [],
            'usergroups' => []
        ];
        if ((int)$userId < 1) {
            return $out;
        }
        try {
            $rows = self::$DB
                ->query(
                    'SELECT `lugTargetType`, `lugTargetID` '
                    . 'FROM `ldapUserGrant` WHERE `lugUserID` = :user',
                    [],
                    ['user' => (int)$userId]
                )
                ->fetch('', 'fetch_all')
                ->get();
        } catch (Exception $e) {
            error_log(
                sprintf(
                    '%s %s() %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Could not read the recorded grants'),
                    $e->getMessage()
                )
            );
            return $out;
        }
        /**
         * PDODB reports a failed query as false rather than throwing
         * (throwOnQueryError is off), and (array)false is [false], not [].
         */
        if (!is_array($rows)) {
            return $out;
        }
        foreach ($rows as $row) {
            $id = trim((string)($row['lugTargetID'] ?? ''));
            if ('' === $id || '0' === $id) {
                continue;
            }
            $kind = (
                LDAPUserGrant::TARGET_USERGROUP
                === (string)($row['lugTargetType'] ?? '')
                ? 'usergroups'
                : 'roles'
            );
            $out[$kind][] = $id;
        }
        $out['roles'] = array_values(array_unique($out['roles']));
        $out['usergroups'] = array_values(array_unique($out['usergroups']));
        return $out;
    }
    /**
     * Replaces the record of what this plugin granted one user.
     *
     * Written after the save, because a first-time user has no id until
     * then. Delete-then-insert rather than a diff: the set is tiny, and
     * rewriting it wholesale means the record cannot drift out of step
     * with what was actually applied.
     *
     * @param User  $userObj  the user that was just saved
     * @param array $roleIds  the roles this login granted
     * @param array $groupIds the user groups this login granted
     *
     * @return void
     */
    private static function _recordGrants($userObj, array $roleIds, array $groupIds)
    {
        $userId = (int)$userObj->get('id');
        if ($userId < 1) {
            return;
        }
        try {
            self::$DB->query(
                'DELETE FROM `ldapUserGrant` WHERE `lugUserID` = :user',
                [],
                ['user' => $userId]
            );
            $targets = [
                LDAPUserGrant::TARGET_ROLE => $roleIds,
                LDAPUserGrant::TARGET_USERGROUP => $groupIds
            ];
            foreach ($targets as $type => $ids) {
                foreach (array_unique($ids) as $id) {
                    if ((int)$id < 1) {
                        continue;
                    }
                    self::$DB->query(
                        'INSERT IGNORE INTO `ldapUserGrant` '
                        . '(`lugUserID`, `lugTargetType`, `lugTargetID`) '
                        . 'VALUES (:user, :type, :target)',
                        [],
                        [
                            'user' => $userId,
                            'type' => $type,
                            'target' => (int)$id
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            error_log(
                sprintf(
                    '%s %s() %s: %s',
                    _('Plugin'),
                    __METHOD__,
                    _('Could not record the granted targets'),
                    $e->getMessage()
                )
            );
        }
    }
    /**
     * Makes the directory authoritative over this plugin's own grants.
     *
     * What the directory says is recomputed on each login, so removing
     * someone from an LDAP group downgrades them the next time they sign
     * in. Anything an admin attached by hand is left alone -- without that
     * carve-out the sync would silently revoke deliberate grants, and an
     * admin would have no way to give an LDAP user anything extra.
     *
     * The managed set is the union of what this plugin previously recorded
     * for this user and what the directory grants now. Reading it from the
     * record rather than from the mapping tables is what makes removing a
     * mapping actually revoke: a target with no mappings left is still in
     * the record, so it is still this plugin's to take away.
     *
     * A user whose record predates that table simply has nothing recorded,
     * so their first sign in after the upgrade revokes nothing and writes
     * the record; from the next one on, revocation is exact. Schema step 18
     * seeds the record for existing users so even that first sign in
     * behaves.
     *
     * Reading get('roles') and get('usergroups') here is also what arms the
     * sync: assocSetter() no-ops on an association that was never loaded or
     * set, so both reads are load-bearing, not just informational.
     *
     * @param User  $userObj  the user being authenticated
     * @param array $roleIds  the role ids this login earns
     * @param array $groupIds the user group ids this login earns
     *
     * @return void
     */
    private function _syncTargets($userObj, array $roleIds, array $groupIds)
    {
        $prior = self::_priorGrants((int)$userObj->get('id'));
        $managedRoles = array_merge(
            $prior['roles'],
            array_map('strval', $roleIds)
        );
        $managedGroups = array_merge(
            $prior['usergroups'],
            array_map('strval', $groupIds)
        );

        $current = array_map('strval', (array)$userObj->get('roles'));
        $roles = array_diff($current, $managedRoles);
        $roles = array_merge($roles, array_map('strval', $roleIds));
        $userObj->set('roles', array_values(array_unique($roles)));

        $current = array_map('strval', (array)$userObj->get('usergroups'));
        $groups = array_diff($current, $managedGroups);
        $groups = array_merge($groups, array_map('strval', $groupIds));
        $userObj->set('usergroups', array_values(array_unique($groups)));
    }
    /**
     * Maps this plugin's legacy uType sentinels back onto core's 0/1.
     *
     * Retained for exactly one caller: FOGBase::isSchemaAdmin()'s fallback
     * for a database still below the schema step that backfills roles. On
     * such an install a directory administrator's row predates both the
     * role tables and the authsource stamp, and 990 is the only evidence
     * of what the account was -- without this mapping that admin could not
     * reach the schema updater to create the tables in the first place.
     *
     * Nothing writes 990/991 any more, and that fallback retires itself
     * once the backfill runs, so this goes inert on its own.
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function setLdapType($arguments)
    {
        if ($arguments['type'] == self::LDAP_ADMIN) {
            $arguments['type'] = 0;
        } elseif ($arguments['type'] == self::LDAP_MOBILE) {
            $arguments['type'] = 1;
        }
    }
}
