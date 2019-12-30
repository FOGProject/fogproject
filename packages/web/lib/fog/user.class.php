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
     *
     * @return bool
     */
    public function passwordValidate(
        $username,
        $password,
        $adminTest = false
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
        if (!$tmpUser->isValid() && $typeIsValid) {
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
        return $passValid;
    }
    /**
     * Gets/creates session id.
     *
     * @return string
     */
    private static function _getSessionID()
    {
        return session_id();
    }
    /**
     * Validates only the user and password
     *
     * @param string $username the username
     * @param string $password the password
     *
     * @return object
     */
    public function validatePw(
        $username,
        $password
    ) {
        if (session_status() == PHP_SESSION_NONE) {
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
        if ($this->passwordValidate($username, $password)
            || self::$FOGUser->isValid()
        ) {
            if (!$test) {
                return new self(0);
            }
            if (self::$FOGUser->isValid()) {
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
            $sessionid = self::_getSessionID();
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
        if (!$this->isValid() || session_status() == PHP_SESSION_NONE) {
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
        $authTime = time() - $_SESSION['lastactivity'];
        $regenTime = $rst * 60 * 60;
        if ($authTime > $regenTime) {
            $sessionid = self::_getSessionID();
            $remember = $_SESSION['rememberme'];
            self::clearAuthCookie();
            session_write_close();
            session_start();
            session_id($sessionid);
            // Only set cookie if we are to be remembered
            if ($remember) {
                $current_time = self::niceDate()->getTimestamp();
                $current_Date = self::niceDate()->format('Y-m-d H:i:s');
                $cookieexp = $current_time + (182 * 24 * 60 * 60);
                $password = self::getToken(16);
                $selector = self::getToken(32);
                $expire = self::niceDate()
                    ->setTimestamp($cookieexp)
                    ->format('Y-m-d H:i:s');
                setcookie('foguserauthpass', $password, $cookieexp);
                setcookie('foguserauthsel', $selector, $cookieexp);

                // Build and create authorization/authentication system.
                Route::ids(
                    'userauth',
                    ['userID' => $this->get('id')]
                );
                $authid = json_decode(Route::getData(), true);
                $userauth = new UserAuth(array_shift($authid));
                $password_hash = self::generateHash($password);
                $selector_hash = self::generateHash($selector);
                $userauth
                    ->set('expire', $expire)
                    ->set('isExpired', '0')
                    ->set('selector', $selector_hash)
                    ->set('password', $password_hash)
                    ->save();
                setcookie('foguserauthid', $userauth->get('id'), $cookieexp);
            }
            $_SESSION['FOG_USER'] = $this->get('id');
        }
        if (!isset($_SESSION['FOG_USER'])) {
            $_SESSION['FOG_USER'] = $this->get('id');
        }
        if (!$ali) {
            $timeout = $ist * 60 * 60;
            if ($authTime > $timeout) {
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
        // Delete any authorizations we may have.
        if ($_COOKIE['foguserauthid']) {
            Route::deletemass(
                'userauth',
                [
                    'id' => $_COOKIE['foguserauthid'],
                    'userID' => $this->get('id')
                ]
            );
        }
        // Clear all the cookies
        self::clearAuthCookie();

        // Unset the user item.
        $this
            ->set('id', 0)
            ->set('name', '')
            ->set('password', '', '');

        // If the session is already gone, return.
        if (session_status() == PHP_SESSION_NONE) {
            return;
        }
        $messages = $_SESSION['FOG_MESSAGES'];
        // Destroy session
        session_unset();
        session_destroy();
        session_write_close();
        session_start();
        $_SESSION=[];
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
        if (isset($displayName)) {
            return $displayName;
        }

        return $this->get('name');
    }
}
