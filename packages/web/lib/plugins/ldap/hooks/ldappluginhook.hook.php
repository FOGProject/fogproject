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
     * Access tiers, ordered: a higher tier wins when several LDAP servers
     * answer for the same user. TIER_NOMATCH sits below a verified user
     * match because it means "we could not check", not "we checked".
     */
    const TIER_NONE = 0;
    const TIER_NOMATCH = 1;
    const TIER_USER = 2;
    const TIER_ADMIN = 3;
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
         * Authenticate against every configured LDAP server and keep the
         * most privileged result (admin beats user beats unverified beats
         * none). A server where the user is absent must not downgrade a
         * match found on another server, so we accumulate the best tier and
         * act on it once after the loop rather than per-server.
         *
         * A server with group matching disabled is its own tier rather than
         * an admin match: authLDAP() returns 2 there, but it means "this
         * account can bind and we have no way to check what it is", not
         * "this account is an administrator". Ranking it below a verified
         * user-group match keeps an unverifiable server from outranking a
         * server that actually answered the question.
         */
        $bestTier = self::TIER_NONE;
        $displayName = '';
        $ldapAPI = 0;
        foreach ($items->data as $ldap) {
            $LDAP = self::getClass('LDAP', $ldap->id);
            $access = (int)$LDAP->authLDAP($user, $pass);
            if ($access < 1) {
                continue;
            }
            if (!$LDAP->get('useGroupMatch')) {
                $tier = self::TIER_NOMATCH;
            } elseif ($access >= 2) {
                $tier = self::TIER_ADMIN;
            } else {
                $tier = self::TIER_USER;
            }
            if ($tier > $bestTier) {
                $bestTier = $tier;
                $displayName = $LDAP->getDisplayName($user, $pass);
                $ldapAPI = $LDAP->get('allowapi');
                /**
                 * Admin is the highest tier; no need to keep looking.
                 */
                if ($bestTier >= self::TIER_ADMIN) {
                    break;
                }
            }
        }
        if (self::TIER_NONE === $bestTier) {
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
        $this->_syncRoles($tmpUser, self::_roleForTier($bestTier));
        $tmpUser->save();
        $arguments['user'] = $tmpUser;
        // Tell core we have already proven this identity, so it skips the
        // local password compare instead of us having to make one succeed.
        $arguments['authenticated'] = true;
    }
    /**
     * The configured role id for an access tier, '' when unset.
     *
     * @param int $tier one of the TIER_* constants
     *
     * @return string
     */
    private static function _roleForTier($tier)
    {
        switch ($tier) {
            case self::TIER_ADMIN:
                $key = 'FOG_PLUGIN_LDAP_ADMIN_ROLE';
                break;
            case self::TIER_USER:
                $key = 'FOG_PLUGIN_LDAP_USER_ROLE';
                break;
            case self::TIER_NOMATCH:
                $key = 'FOG_PLUGIN_LDAP_NOMATCH_ROLE';
                break;
            default:
                return '';
        }
        return trim((string)self::getSetting($key));
    }
    /**
     * Makes the directory authoritative over the mapped roles only.
     *
     * The three mapped roles are recomputed from the directory on every
     * login, so revoking someone's LDAP group membership downgrades them
     * the next time they sign in. Any other role an admin attached by hand
     * is left alone -- without that carve-out the sync would silently
     * revoke deliberate grants, and an admin would have no way to give an
     * LDAP user anything extra.
     *
     * Reading get('roles') here is also what arms the sync: assocSetter()
     * no-ops on an association that was never loaded or set, so the read
     * below is load-bearing, not just informational.
     *
     * @param User   $userObj the user being authenticated
     * @param string $roleId  the role this login earns, '' for none
     *
     * @return void
     */
    private function _syncRoles($userObj, $roleId)
    {
        $managed = array_map(
            'strval',
            array_filter(
                [
                    self::getSetting('FOG_PLUGIN_LDAP_ADMIN_ROLE'),
                    self::getSetting('FOG_PLUGIN_LDAP_USER_ROLE'),
                    self::getSetting('FOG_PLUGIN_LDAP_NOMATCH_ROLE')
                ]
            )
        );
        $current = array_map('strval', (array)$userObj->get('roles'));
        $roles = array_values(array_diff($current, $managed));
        if ('' !== (string)$roleId) {
            $roles[] = (string)$roleId;
        }
        $userObj->set('roles', array_values(array_unique($roles)));
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
