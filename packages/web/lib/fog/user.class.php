<?php
/**
 * Handler of the user as authenticated
 *
 * PHP version 5
 *
 * @category User
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Handler of the user as authenticated
 *
 * @category User
 * @package  FOGProject
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class User extends FOGController
{
    const PATTERN = '/(?=^.{3,50}$)^(?!.*[_\s\-\.]{2,})[\w0-9][\w0-9\s\-\.]*[\w0-9]$/i';
    /**
     * The users table
     *
     * @var string
     */
    protected $databaseTable = 'users';
    /**
     * The user table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'uId',
        'name' => 'uName',
        'password' => 'uPass',
        'createdTime' => 'uCreateDate',
        'createdBy' => 'uCreateBy',
        'type' => 'uType',
        'display' => 'uDisplay',
        'api' => 'uAllowAPI',
        'token' => 'uAPIToken',
        // '' = local account. Non-empty names the external provider that
        // authenticates this user (e.g. 'ldap'); see schema step 314.
        'authsource' => 'uAuthSource'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'password'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'roles',
        'usergroups'
    ];
    /**
     * Generates an encrypted hash
     *
     * @param string $password the password
     * @param int    $cost     cost of hash
     *
     * @return string
     */
    public static function generateHash(
        $password,
        $cost = 11
    ) {
        return password_hash(
            $password,
            PASSWORD_BCRYPT,
            ['cost' => $cost]
        );
    }
    /**
     * Validates the users password and user
     *
     * The $adminTest parameter is gone. It made the credential typed into
     * the re-authentication prompt (FOG_REAUTH_ON_DELETE) have to belong to
     * a uType 0 account -- pre-RBAC shorthand for "not a mobile user", back
     * when FOG had exactly two tiers and the mobile one could not perform a
     * destructive action anyway. Under roles the tiering is the role's job:
     * the acting user has already had to pass the node's delete permission
     * before checkauth() is reached, so re-testing an account type here
     * decided nothing and would, if translated literally to "must hold '*'",
     * have newly blocked every scoped role from deleting anything.
     *
     * @param string $username the username to test
     * @param string $password the password to test
     * @param bool   $remember Are we remembering user?
     *
     * @return bool
     */
    public function passwordValidate(
        $username,
        $password,
        $remember = false
    ) {
        /**
         * Test the username for funky characters and return
         * immediately if found.
         */
        $test = preg_match(
            self::PATTERN,
            $username
        );
        $tmpUser = new User();
        // An external provider (LDAP today, any auth plugin tomorrow) sets
        // this to true to state that it has already proven the identity
        // against its own directory. Without it a plugin had no way to say
        // "this person is who they claim" and its only option was to write
        // the directory password into uPass so the compare below would pass
        // -- which is why FOG has been storing bcrypt hashes of live AD
        // passwords. It is honoured ONLY for accounts carrying an
        // authsource stamp, so it can never be used to bypass the password
        // of a local account.
        $authenticated = false;
        self::$HookManager->processEvent(
            'USER_LOGGING_IN',
            [
                'username' => $username,
                'password' => $password,
                'user' => &$tmpUser,
                'authenticated' => &$authenticated
            ]
        );
        $typeIsValid = true;
        $ident = (int)$tmpUser->get('id');
        if (!$tmpUser->isValid()) {
            $tmpUser = self::getClass('User')
                ->set('name', $username)
                ->load('name');
        }
        $isExternal = ('' !== trim((string)$tmpUser->get('authsource')));
        // An externally sourced account has no usable local password, so a
        // local credential must never authenticate it. This is the check
        // that makes a leftover shadow row harmless: with the auth plugin
        // uninstalled or its server unreachable nothing vouches for the
        // account, and the row stops being a login. It also replaces the
        // LDAP plugin's own isLdapType() guard, which never fired because
        // USER_TYPE_HOOK rewrote the type it tested one block earlier.
        if ($isExternal && true !== $authenticated) {
            return false;
        }
        if (!$isExternal
            && $tmpUser->isValid()
            && preg_match('#^[a-f0-9]{32}$#i', $tmpUser->get('password'))
            && md5($password) === $tmpUser->get('password')
        ) {
            $tmpUser
                ->set('password', $password)
                ->save();
        }
        // $isExternal implies $authenticated by the guard above, which
        // returned for every other external case.
        $passValid = $isExternal ? true : (bool)password_verify(
            $password,
            $tmpUser->get('password')
        );
        $type = $tmpUser->get('type');
        self::$HookManager->processEvent(
            'USER_TYPE_HOOK',
            ['type' => &$type]
        );
        self::$HookManager->processEvent(
            'USER_TYPE_VALID',
            [
                'type' => &$type,
                'typeIsValid' => &$typeIsValid
            ]
        );
        if ($typeIsValid && !in_array($type, [0, 1])) {
            $typeIsValid = false;
        }
        if (!$test
            || $ident < 0
            || !$tmpUser->isValid()
            || !$typeIsValid
            || !$passValid
        ) {
            return false;
        }
        $this
            ->set('id', $tmpUser->get('id'))
            ->set('name', $username)
            ->set('password', '', true)
            ->set('type', $type);
        unset($tmpUser);
        if ($remember && $passValid) {
            // Remember-me is per-user, carried by the foguserauth* cookies
            // and UserAuth token below. It must NOT touch the shared
            // FOG_ALWAYS_LOGGED_IN setting -- doing so disabled the
            // inactivity timeout for every user, install-wide.
            // Setup Cookie stuff.
            $current_time = self::nicedate()->getTimestamp();
            $cookieexp = $current_time + (2 * 24 * 60 * 60);
            $password = self::getToken(16);
            $selector = self::getToken(32);
            $expire = self::niceDate()
                ->setTimestamp($cookieexp)
                ->format('Y-m-d H:i:s');
            self::setAuthCookie('foguserauthpass', $password, $cookieexp);
            self::setAuthCookie('foguserauthsel', $selector, $cookieexp);

            // Trim expired tokens before adding a new one; this is the
            // only point at which the userAuths table grows.
            UserAuth::reapExpired();

            // Build and create authorization/authentication system.
            $password_hash = UserAuth::generateHash($password);
            $selector_hash = UserAuth::generateHash($selector);
            $auth = self::getClass('UserAuth')
                ->set('userID', $this->get('id'))
                ->set('expire', $expire)
                ->set('selector', $selector_hash)
                ->set('password', $password_hash)
                ->save();

            // Set the id in the cookie for this particular auth item.
            self::setAuthCookie('foguserauthid', $auth->get('id'), $cookieexp);
        }
        return $passValid;
    }
    /**
     * Validates only the user and password
     *
     * @param string $username the username
     * @param string $password the password
     * @param bool   $remember Are we remembering user?
     *
     * @return object
     */
    public function validatePw(
        $username,
        $password,
        $remember = false
    ) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        /**
         * Test the username for funky characters and return
         * immediately if found.
         */
        $test = preg_match(
            self::PATTERN,
            $username
        );
        if ($this->passwordValidate($username, $password, $remember)) {
            if (!$test) {
                return new self(0);
            }
            if (self::$FOGUser->isValid()) {
                $type = self::$FOGUser->get('type');
                self::$HookManager->processEvent(
                    'USER_TYPE_HOOK',
                    ['type' => &$type]
                );
                $this
                    ->set('id', self::$FOGUser->get('id'))
                    ->set('name', self::$FOGUser->get('name'))
                    ->set('password', '', true)
                    ->set('type', $type);
            }
            $_SESSION['FOG_USER'] = $this->get('id');
            self::log(
                sprintf(
                    '%s %s.',
                    $this->get('name'),
                    _('user successfully logged in')
                ),
                0,
                0,
                $this,
                0
            );
            $this->_isLoggedIn();
            return $this;
        }
        self::log(
            sprintf(
                '%s %s.',
                $this->get('name'),
                _('user failed to login'),
                $this->get('name')
            ),
            0,
            0,
            $this,
            0
        );
        self::$EventManager->notify(
            'LoginFail',
            ['Failure' => $username]
        );
        self::$HookManager->processEvent(
            'LoginFail',
            [
                'username' => &$username,
                'password' => &$password
            ]
        );
        return $this;
    }
    /**
     * Sets the passed value
     *
     * @param string $key      the key to set
     * @param mixed  $value    the value to set
     * @param bool   $override to override the setter
     *
     * @return object
     */
    public function set(
        $key,
        $value,
        $override = false
    ) {
        if ($this->key($key) == 'password'
            && !$override
        ) {
            $value = self::generateHash($value);
        }
        return parent::set($key, $value);
    }
    /**
     * Tests if an object is valid
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->get('id') < 1) {
            return false;
        }
        if (!$this->get('name')) {
            return false;
        }
        return true;
    }
    /**
     * Returns if the user is logged in or not
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->_isLoggedIn() ? $this : new User(0);
    }
    /**
     * Tests if user is logged in
     *
     * @return bool
     */
    private function _isLoggedIn()
    {
        if (!$this->isValid() || session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $keys = [
            'FOG_ALWAYS_LOGGED_IN',
            'FOG_INACTIVITY_TIMEOUT',
            'FOG_REGENERATE_TIMEOUT'
        ];
        list(
            $ali,
            $ist,
            $rst
        ) = self::getSetting($keys);
        if (!isset($_SESSION['sessioncreated'])) {
            // First authenticated request in this session (fresh login or
            // remember-me auto-login). Stamp the creation time now so the
            // regenerate cadence is measured from here. Without this the
            // unset case fell through to time()-0, which dwarfs regenTime
            // and forced a regeneration on every brand-new session.
            $_SESSION['sessioncreated'] = time();
        }
        $authTime = time() - $_SESSION['sessioncreated'];
        $regenTime = $rst * 60 * 60;
        if ($authTime > $regenTime) {
            // Rotate the session ID to prevent fixation. Pass false so the
            // old session is NOT deleted immediately: FOG pages fire many
            // AJAX requests in parallel, and any sibling still carrying the
            // previous cookie would otherwise land on a deleted ID and --
            // with session.use_strict_mode -- be handed a new empty session,
            // silently logging the user out. The old session expires via gc.
            // Note: session_regenerate_id() returns bool, not the new ID.
            session_regenerate_id(false);
            $_SESSION['sessioncreated'] = time();

            $id = filter_input(INPUT_COOKIE, 'foguserauthid');
            $userauth = new UserAuth($id);
            if ($userauth->isValid()) {
                // Do NOT rotate the remember-me secret here. FOG fires many
                // AJAX requests in parallel; when several cross this 30-minute
                // regenerate boundary at once, each one used to mint a fresh
                // selector/password and overwrite the single userAuths row --
                // desyncing the browser cookie from the stored hash, so the
                // next remember-me auto-login failed password_verify() and
                // silently logged the user out. The secret is now rotated only
                // at a real login. Here we merely slide the 2-day expiry
                // forward, re-using the existing cookie values so concurrent
                // requests stay idempotent (same secret, only the expiry moves).
                $password = filter_input(INPUT_COOKIE, 'foguserauthpass');
                $selector = filter_input(INPUT_COOKIE, 'foguserauthsel');
                if ($password && $selector) {
                    $cookieexp = self::niceDate()->getTimestamp()
                        + (2 * 24 * 60 * 60);
                    $expire = self::niceDate()
                        ->setTimestamp($cookieexp)
                        ->format('Y-m-d H:i:s');
                    self::setAuthCookie('foguserauthpass', $password, $cookieexp);
                    self::setAuthCookie('foguserauthsel', $selector, $cookieexp);
                    self::setAuthCookie('foguserauthid', $userauth->get('id'), $cookieexp);
                    $userauth
                        ->set('expire', $expire)
                        ->save();
                }
            }
        }
        if (!isset($_SESSION['FOG_USER'])) {
            $_SESSION['FOG_USER'] = $this->get('id');
        }
        if (!$ali) {
            $timeout = $ist * 60 * 60;
            if (!isset($lastactivity)) {
                $lastactivity = 0;
            }
            if (isset($_SESSION['lastactivity'])) {
                $lastactivity = time() - $_SESSION['lastactivity'];
            }
            // Never re-trip the inactivity redirect on the logout/login
            // requests themselves. Page is constructed (and calls back into
            // isLoggedIn) before index.php runs the logout handler, so firing
            // here would redirect to node=logout again before logout() ever
            // runs -- an infinite node=logout loop that also prevents the
            // preserved "Session Expired" toast from ever reaching login.
            $node = filter_input(INPUT_GET, 'node');
            if ($lastactivity > $timeout
                && !in_array($node, ['logout', 'login'], true)
            ) {
                self::setMessage(
                    _('You were logged out due to inactivity.'),
                    _('Session Expired'),
                    'warning'
                );
                self::redirect('../management/index.php?node=logout');
            }
        }
        $_SESSION['lastactivity'] = time();
        return true;
    }
    /**
     * Perform logout cleanup
     *
     * @return void
     */
    public function logout()
    {
        self::$HookManager
            ->processEvent('USER_LOGGING_OUT');
        // Clear all the cookies
        self::clearAuthCookie();

        // Unset the user item.
        $this
            ->set('id', 0)
            ->set('name', '')
            ->set('password', '', true);

        // If the session is already gone, return.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        // Preserve any queued flash messages across the session rebuild so
        // they can toast on the login page after we redirect (e.g. the
        // "logged out due to inactivity" notice).
        $messages = self::getMessage();
        // Destroy session
        session_unset();
        session_destroy();
        session_write_close();
        session_start();
        $_SESSION = [];
        if ($messages) {
            $_SESSION['FOG_MESSAGES'] = $messages;
        }
    }

    /**
     * Emit a remember-me auth cookie with hardened attributes.
     *
     * HttpOnly keeps it out of JS, SameSite=Lax blocks CSRF-style
     * cross-site sends while still allowing top-level navigation
     * (so auto-login on a fresh page load still works), and Secure is
     * only set when the request is HTTPS so plain-HTTP LAN installs are
     * not broken. Path/domain are left at the default so the cookie
     * scope matches the prior positional-setcookie behavior.
     *
     * @param string $name     the cookie name
     * @param string $value    the cookie value
     * @param int    $cookieexp the expiry timestamp
     *
     * @return void
     */
    private static function setAuthCookie($name, $value, $cookieexp)
    {
        $secure = !empty($_SERVER['HTTPS'])
            && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        setcookie(
            $name,
            (string)$value,
            [
                'expires' => $cookieexp,
                'httponly' => true,
                'secure' => $secure,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * If the user has a friendly name, this will return the friendly name of the user.
     * Otherwise, it will return their username.
     *
     * @return string
     */
    public function getDisplayName()
    {
        $displayName = $this->get('display');
        if (!empty($displayName) && isset($displayName)) {
            return $displayName;
        }

        return $this->get('name');
    }
    /**
     * Does this user hold the given permission?
     *
     * @param string|null $perm permission string (e.g. 'host.edit')
     *
     * @return bool
     */
    public function can($perm)
    {
        return Authorization::can($perm, (int)$this->get('id'));
    }
    /**
     * Stores the user, syncing role associations when loaded.
     * assocSetter no-ops unless 'roles' has been loaded/set, so save
     * paths that never touch roles (e.g. password migration) are safe.
     *
     * A brand new user is put in the catch-all site, but only while no real
     * site exists. Schema step 333 enrolled every account that was on the
     * server the day it upgraded; without this the next account created
     * would be the only one on that server in no site at all, and site
     * scope is deny-all, so it would see nothing. That asymmetry -- every
     * old account works, every new one is blank -- is the bug. Once an
     * admin has created a real site, which sites a new user belongs to is
     * their decision and defaulting it to "all of them" would be an
     * access-control call made by accident, so the gate stays.
     *
     * It lives here rather than in the creation pages because two of the
     * four creation paths involve nobody clicking anything: a REST POST and
     * the ldap plugin auto-provisioning on first login. FOGController::save
     * fires no event, so a hook could not have covered them either.
     *
     * @return bool
     */
    public function save()
    {
        // Captured before the save, because that is what stamps the id on.
        $isNew = (int)$this->get('id') < 1;
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
        if ($isNew && !SiteScope::sitesInUse()) {
            SiteScope::joinCatchAll((int)$this->get('id'));
        }
        return $this
            ->assocSetter('RoleUser', 'role')
            ->assocSetter('UserGroupMember', 'usergroup', true)
            ->load();
    }
    /**
     * Adds roles to the user.
     *
     * @param array $addArray the roles to add
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
     * Removes roles from the user.
     *
     * @param array $removeArray the roles to remove
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
     * Loads the user's roles.
     *
     * @return void
     */
    protected function loadRoles()
    {
        $this->set(
            'roles',
            (array)Route::getIds(
                'roleuserassociation',
                ['userID' => $this->get('id')],
                'roleID'
            )
        );
    }
    /**
     * Adds the user to the given groups.
     *
     * @param array $addArray the group ids to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes the user from the given groups.
     *
     * @param array $removeArray the group ids to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->addRemItem(
            'usergroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads the groups this user belongs to.
     *
     * @return void
     */
    protected function loadUsergroups()
    {
        $this->set(
            'usergroups',
            (array)Route::getIds(
                'usergroupmember',
                ['userID' => $this->get('id')],
                'usergroupID'
            )
        );
    }
    /**
     * Removes the item from the database.
     *
     * @param string $key the key to remove
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        // Funnel through the cascade authority so user-keyed associations
        // (native roleUserAssoc, site plugin siteUserAssoc)
        // are cleared via DELETEMASS_API on every delete path. deletemass also
        // deletes the user row; the trailing parent::destroy() is a harmless
        // no-op that preserves the audit-log/history entry.
        Route::deletemass('user', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\User', 'User');
