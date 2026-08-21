<?php
/**
 * Handler of the user as authenticated
 *
 * PHP version 7.4+
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
     * A session established by presenting a local password.
     *
     * The default, and the one that must never stop working: break-glass
     * depends on password login remaining reachable when an identity
     * provider is not.
     *
     * @var string
     */
    const AUTH_SOURCE_PASSWORD = 'password';
    /**
     * A session whose provenance was supplied but unusable.
     *
     * Recorded rather than guessed. A provider handing over something that
     * is not a plain slug is a bug in that provider, and "unknown" is the
     * honest entry in an audit trail.
     *
     * @var string
     */
    const AUTH_SOURCE_UNKNOWN = 'unknown';
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
    /**
     * Records a refused credential.
     *
     * Here rather than in validatePw() or loginPost() because this is the
     * one funnel every password check reaches: the web login form goes
     * through validatePw(), the iPXE menu and service/checkcredentials.php
     * call this directly, and authenticateOnly() does too. Auditing at any
     * of those would cover a quarter of them, which is exactly the gap ADR
     * 0021 records for the existing fog_login_failed.log.
     *
     * NO REASON IS RECORDED, and that is a decision rather than an
     * omission. The reasons available here are "no such account", "wrong
     * password", "account is external and nothing vouched for it" and
     * "wrong user type" -- and writing which one it was into a readable log
     * is user enumeration with an audit badge. The username, the address,
     * the time and the fact of refusal are the facts worth keeping.
     *
     * The subject is the ATTEMPTED name, and subjectID stays 0 unless the
     * account resolved: an id here would assert that the attempt named a
     * real account, which is the same disclosure.
     *
     * @param string $username the name that was attempted
     * @param object $tmpUser  the account it resolved to, if any
     *
     * @return void
     */
    private static function _auditLoginFailure($username, $tmpUser = null)
    {
        // No name presented is not a failed attempt, it is no attempt.
        // Route::_testAuth() calls passwordValidate() with whatever basic
        // auth produced, which is a pair of empty strings on every API
        // request that carries no Authorization header at all -- and those
        // are ordinary traffic, not credential guesses. Recording them would
        // bury the real ones.
        if ('' === trim((string)$username)) {
            return;
        }
        Audit::record(
            [
                'type' => Audit::LOGIN_FAILED,
                'outcome' => Audit::DENIED,
                'subjectType' => 'user',
                'subjectID' => 0,
                'subjectLabel' => (string)$username,
                // The actor IS the attempted name. Falling back to the
                // machine actor here would record every failed login as
                // something FOG did to itself.
                'createdBy' => (string)$username,
                'authSource' => $tmpUser instanceof User
                    ? (string)$tmpUser->get('authsource')
                    : '',
                'renderable' => 1
            ]
        );
    }
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
            self::_auditLoginFailure($username, $tmpUser);
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
            self::_auditLoginFailure($username, $tmpUser);
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
     * Proves an identity, and does nothing else.
     *
     * Split out of validatePw() because proving who someone is and giving
     * them a browser session are two different things, and only one of them
     * belongs to a caller that has no browser. The iPXE boot menu and
     * service/ipxe/advanced.php both want a yes/no about a credential; they
     * went through validatePw(), which unconditionally started a PHP session
     * and stamped $_SESSION['FOG_USER'] -- so a PXE menu login minted a
     * server-side authenticated session that nothing would ever present a
     * cookie for. Harmless-looking, but it is an authenticated session
     * created by a request that cannot hold one.
     *
     * No session, no cookies, no login history entry: passwordValidate()
     * touches none of those with $remember false, which is what makes this
     * split clean rather than a reimplementation.
     *
     * @param string $username the username
     * @param string $password the password
     *
     * @return self populated on success, an invalid User otherwise
     */
    public function authenticate($username, $password)
    {
        if (!$this->passwordValidate($username, $password, false)) {
            return new self(0);
        }
        return $this;
    }
    /**
     * How this session was established, or '' when there is no session.
     *
     * Read this rather than users.uAuthSource when the question is about the
     * REQUEST. uAuthSource is a property of the ACCOUNT -- where its identity
     * lives -- and the two genuinely differ: an account homed in LDAP or an
     * IdP can still be carrying a local password, and the point of asking is
     * usually to find out which of the two got someone in just now.
     *
     * @return string
     */
    public static function sessionAuthSource()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        return (string)($_SESSION['FOG_AUTH_SOURCE'] ?? '');
    }
    /**
     * Reduce a caller-supplied auth source to something safe to record.
     *
     * Normalised, not trusted: the value reaches the history table and a
     * session key, and a provider plugin supplies it. Anything that is not a
     * plain slug is recorded as unknown rather than passed through, because
     * "we do not know how this session was made" is a fact worth keeping and
     * is safer than storing whatever was handed over.
     *
     * Public so it can be exercised without a database; nothing else calls it.
     *
     * @param string $source The value as supplied.
     *
     * @return string
     */
    public static function normalizeAuthSource($source)
    {
        $source = strtolower(trim((string)$source));
        if (!preg_match('#^[a-z0-9][a-z0-9_-]{0,31}$#', $source)) {
            return self::AUTH_SOURCE_UNKNOWN;
        }
        return $source;
    }
    /**
     * Turns an already-proven identity into a logged-in browser session.
     *
     * The other half of the authenticate() split. Everything here needs a
     * session to exist and a browser to carry it, so nothing here may run
     * for the iPXE callers.
     *
     * $source records WHICH mechanism proved the identity, and it exists for
     * two later jobs rather than for its own sake. Audit: "logged in" in the
     * history table cannot presently distinguish a password from an identity
     * provider, so an install adopting SSO loses the ability to answer how
     * someone got in. Break-glass: an IdP outage must leave local password
     * login working, and the checks that guarantee that need to be able to
     * count sessions by how they were made, not by what the account looks
     * like.
     *
     * Defaults to 'password' so every existing caller -- validatePw() and
     * whatever third-party code calls it -- keeps the meaning it already had.
     *
     * @param string $source Slug naming the mechanism, e.g. 'password', 'oidc'.
     *
     * @return self
     */
    public function establishSession($source = self::AUTH_SOURCE_PASSWORD)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $source = self::normalizeAuthSource($source);
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
        $_SESSION['FOG_AUTH_SOURCE'] = $source;
        // Every successful session is made here -- the password form through
        // validatePw(), OIDC through the plugin, any future provider the
        // same way -- so this is the one call that records a login for all
        // of them. AFTER the stamp above, so Audit reads the source this
        // session was actually made with rather than the one it replaced.
        Audit::record(
            [
                'type' => Audit::LOGIN,
                'outcome' => Audit::ALLOWED,
                'subjectType' => 'user',
                'subjectID' => (int)$this->get('id'),
                'subjectLabel' => (string)$this->get('name'),
                'createdBy' => (string)$this->get('name'),
                'authSource' => $source,
                'renderable' => 1
            ]
        );
        self::log(
            sprintf(
                '%s %s (%s).',
                $this->get('name'),
                _('user successfully logged in'),
                $source
            ),
            0,
            0,
            $this,
            0
        );
        $this->_isLoggedIn();
        return $this;
    }
    /**
     * Validates only the user and password
     *
     * Authenticates AND establishes a session, which is what every existing
     * caller expects. Kept whole so third-party callers keep working; the
     * two halves are available separately above.
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
            return $this->establishSession();
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
     * Returns where the browser should go next, which is normally nothing --
     * the caller then uses its own default. A USER_LOGGING_OUT listener may
     * set it to send the browser somewhere else instead, and the one case
     * that needs this is an external identity provider: destroying FOG's
     * session leaves the provider's SSO session untouched, so clicking the
     * provider button again signs straight back into the same account and
     * there is no way to become somebody else (fog-plugins#15). Ending that
     * session is a redirect to the provider's end_session_endpoint, and
     * only the plugin that started the session knows the URL.
     *
     * The hook runs BEFORE the session is destroyed on purpose: whatever a
     * listener needs to build that URL -- an id_token_hint, the endpoint it
     * cached at sign-in -- is in $_SESSION, and a moment later it is not.
     *
     * A listener that sets nothing changes nothing, so this is inert on an
     * install with no such plugin.
     *
     * @return string somewhere to redirect, or '' for the caller's default
     */
    public function logout()
    {
        $redirect = '';
        self::$HookManager
            ->processEvent(
                'USER_LOGGING_OUT',
                ['redirect' => &$redirect]
            );
        // BEFORE the identity is cleared below. set('id', 0) three lines
        // down empties the object, so a record written after it says a
        // nameless nobody logged out.
        Audit::record(
            [
                'type' => Audit::LOGOUT,
                'outcome' => Audit::ALLOWED,
                'subjectType' => 'user',
                'subjectID' => (int)$this->get('id'),
                'subjectLabel' => (string)$this->get('name'),
                'createdBy' => (string)$this->get('name'),
                'renderable' => 1
            ]
        );
        // Clear all the cookies
        self::clearAuthCookie();

        // Unset the user item.
        $this
            ->set('id', 0)
            ->set('name', '')
            ->set('password', '', true);

        // If the session is already gone, return. Still hands back whatever
        // the hook asked for: a listener that recorded its provider's
        // end-session URL from a cookie rather than the session is still
        // owed the redirect, and silently dropping it here would make single
        // logout work or not depending on session state.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $redirect;
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
        return $redirect;
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
        $this->_assertAuthSourceKeepsBreakGlass();
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
     * Refuses to hand the last local administrator to a directory.
     *
     * Writing users.uAuthSource takes local password login away from the
     * account it is written to (see passwordValidate() above). Doing that to
     * the last administrator who still has one leaves the install reachable
     * only while its identity provider is: an outage, an expired client
     * secret or a mistyped issuer then locks everybody out of their own
     * server, with no way back in that does not involve the database.
     *
     * It sits on save() rather than on each caller because there are three
     * ways to get here and they have nothing in common: a REST
     * PUT /fog/user/{id} (uAuthSource is an ordinary field and is not in
     * Route::$serverOwnedFields), a plugin's own set()/save(), and the CSV
     * import. Guarding the write is what makes it a standing property
     * instead of something each new caller has to remember. Delete is
     * covered separately, by Authorization::assertAdminRemainsAfterDelete().
     *
     * Neither bundled auth plugin can reach this: LDAP returns early for an
     * account that exists and is not already its own, and OIDC stamps only
     * accounts it created itself. It is the deliberate administrative paths
     * that were open.
     *
     * @throws Exception when the last local administrator would be lost
     * @return void
     */
    private function _assertAuthSourceKeepsBreakGlass()
    {
        $id = (int)$this->get('id');
        /*
         * A row with no id yet is a new account, which cannot take a login
         * away from anybody. It cannot quietly become an update either:
         * users.uName carries a plain KEY and not a UNIQUE one, so there is
         * no INSERT ... ON DUPLICATE KEY UPDATE by name to ride in on, and
         * the CSV import refuses a name that already exists before it gets
         * this far.
         */
        if ($id < 1 || !$this->isDirty('authsource')) {
            return;
        }
        $pending = trim((string)$this->get('authsource'));
        // Clearing it only ever gives an account its password back.
        if ('' === $pending) {
            return;
        }
        /*
         * No need to read the stored value to spot a no-op: an account that
         * is already external stays external under the simulated change, so
         * the answer is the same with and without it and the assertion
         * passes. That covers a round-tripping PUT and a re-stamp from one
         * provider to another without a second query.
         */
        Authorization::assertLocalAdminRemains(
            ['authSources' => [$id => $pending]]
        );
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
