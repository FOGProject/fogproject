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
        'token' => 'uAPIToken'
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
     * @param string $username  the username to test
     * @param string $password  the password to test
     * @param string $adminTest the admin test
     * @param bool   $remember  Are we remembering user?
     *
     * @return bool
     */
    public function passwordValidate(
        $username,
        $password,
        $adminTest = false,
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
        self::$HookManager->processEvent(
            'USER_LOGGING_IN',
            [
                'username' => $username,
                'password' => $password,
                'user' => &$tmpUser
            ]
        );
        $typeIsValid = true;
        $ident = (int)$tmpUser->get('id');
        if (!$tmpUser->isValid()) {
            $tmpUser = self::getClass('User')
                ->set('name', $username)
                ->load('name');
        }
        if ($tmpUser->isValid()
            && preg_match('#^[a-f0-9]{32}$#i', $tmpUser->get('password'))
            && md5($password) === $tmpUser->get('password')
        ) {
            $tmpUser
                ->set('password', $password)
                ->save();
        }
        $passValid = (bool)password_verify(
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
        if ($adminTest === true) {
            if ($this->get('type') > 0) {
                $passValid = false;
            }
        }
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
        if ($this->passwordValidate($username, $password, false, $remember)) {
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
        $authTime = 0;
        if (isset($_SESSION['sessioncreated'])) {
            $authTime = time() - $_SESSION['sessioncreated'];
        }
        if (!$authTime) {
            $authTime = time();
        }
        $regenTime = $rst * 60 * 60;
        if ($authTime > $regenTime) {
            // Rotate the session ID and delete the old session to prevent
            // fixation. Note: session_regenerate_id() returns bool, not
            // the new ID.
            session_regenerate_id(true);
            $_SESSION['sessioncreated'] = time();

            $id = filter_input(INPUT_COOKIE, 'foguserauthid');
            $userauth = new UserAuth($id);
            if ($userauth->isValid()) {
                $current_time = self::niceDate()->getTimestamp();
                $cookieexp = $current_time + (2 * 24 * 60 * 60);
                $password = self::getToken(16);
                $selector = self::getToken(32);
                $expire = self::niceDate()
                    ->setTimestamp($cookieexp)
                    ->format('Y-m-d H:i:s');
                self::setAuthCookie('foguserauthpass', $password, $cookieexp);
                self::setAuthCookie('foguserauthsel', $selector, $cookieexp);
                self::setAuthCookie('foguserauthid', $userauth->get('id'), $cookieexp);

                $password_hash = $userauth->generateHash($password);
                $selector_hash = $userauth->generateHash($selector);

                $userauth
                    ->set('expire', $expire)
                    ->set('selector', $selector_hash)
                    ->set('password', $password_hash)
                    ->save();
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
     * Removes the item from the database.
     *
     * @param string $key the key to remove
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        // Funnel through the cascade authority so user-keyed plugin associations
        // (accesscontrol roleUserAssoc, site siteUserAssoc/siteUserRestriction)
        // are cleared via DELETEMASS_API on every delete path. deletemass also
        // deletes the user row; the trailing parent::destroy() is a harmless
        // no-op that preserves the audit-log/history entry.
        Route::deletemass('user', ['id' => $this->get('id')]);
        return parent::destroy($key);
    }
}
