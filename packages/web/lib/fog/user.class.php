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
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class User extends FOGController
{
    /**
     * Stores the timeout value
     *
     * @var int
     */
    private $_inactivitySessionTimeout;
    /**
     * Stores regeneration timeout
     *
     * @var int
     */
    private $_regenerateSessionTimeout;
    /**
     * Stores if we should always be logged in
     *
     * @var bool
     */
    private $_alwaysloggedin;
    /**
     * Was this already checked
     *
     * @var boot
     */
    private $_checkedalready;
    /**
     * The session id
     *
     * @var string
     */
    private $_sessionID;
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
    protected $databaseFields = array(
        'id' => 'uId',
        'name' => 'uName',
        'password' => 'uPass',
        'createdTime' => 'uCreateDate',
        'createdBy' => 'uCreateBy',
        'type' => 'uType',
        'display' => 'uDisplay',
        'api' => 'uAllowAPI',
        'token' => 'uAPIToken'
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'password',
    );
    /**
     * The additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'authID',
        'authIP',
        'authTime',
        'authLastActivity',
        'authUserAgent',
    );
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
            ['cost'=>$cost]
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
            '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
            $username
        );
        $tmpUser = new User();
        self::$HookManager
            ->processEvent(
                'USER_LOGGING_IN',
                array(
                    'username' => $username,
                    'password' => $password,
                    'user' => &$tmpUser
                )
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
        self::$HookManager
            ->processEvent(
                'USER_TYPE_HOOK',
                array(
                    'type' => &$type
                )
            );
        self::$HookManager
            ->processEvent(
                'USER_TYPE_VALID',
                array(
                    'type' => &$type,
                    'typeIsValid' => &$typeIsValid
                )
            );
        if ($typeIsValid && !in_array($type, array(0, 1))) {
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
        /**
         * Test the username for funky characters and return
         * immediately if found.
         */
        $test = preg_match(
            '/(?=^.{3,40}$)^[\w][\w0-9]*[._-]?[\w0-9]*[.]?[\w0-9]+$/i',
            $username
        );
        if ($this->passwordValidate($username, $password)) {
            if (!$test) {
                return new self(0);
            }
            if (!$this->_sessionID) {
                $this->_sessionID = session_id();
            }
            $this
                ->set(
                    'authUserAgent',
                    self::$useragent
                )
                ->set(
                    'authIP',
                    self::$remoteaddr
                )
                ->set('authTime', time())
                ->set('authLastActivity', time())
                ->set('authID', $this->_sessionID);
            if (session_status() != PHP_SESSION_NONE) {
                $_SESSION['FOG_USER'] = $this->get('id');
            }
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
        } else {
            if (!$test) {
                return new self(0);
            }
            if (self::$FOGUser->isValid()) {
                $type = self::$FOGUser->get('type');
                self::$HookManager
                    ->processEvent(
                        'USER_TYPE_HOOK',
                        array('type' => &$type)
                    );
                $this
                    ->set('id', self::$FOGUser->get('id'))
                    ->set('name', self::$FOGUser->get('name'))
                    ->set('password', '', true)
                    ->set('type', $type);
                if (!$this->_sessionID) {
                    $this->_sessionID = session_id();
                }
                $this
                    ->set(
                        'authUserAgent',
                        self::$useragent
                    )
                    ->set(
                        'authIP',
                        self::$remoteaddr
                    )
                    ->set('authTime', time())
                    ->set('authLastActivity', time())
                    ->set('authID', $this->_sessionID);
                if (session_status() != PHP_SESSION_NONE) {
                    $_SESSION['FOG_USER'] = $this->get('id');
                }
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
                array('Failure' => $username)
            );
            self::$HookManager->processEvent(
                'LoginFail',
                array(
                    'username' => &$username,
                    'password' => &$password
                )
            );
            self::setMessage(self::$foglang['InvalidLogin']);
            if (session_status() != PHP_SESSION_NONE) {
                $_SESSION['OBSOLETE'] = true;
            }
        }
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
     * Tests if user is logged in
     *
     * @return bool
     */
    private function _isLoggedIn()
    {
        if (!$this->isValid()) {
            return false;
        }
        if (!$this->_checkedalready) {
            list(
                $this->_alwaysloggedin,
                $this->_inactivitySessionTimeout,
                $this->_regenerateSessionTimeout,
            ) = self::getSubObjectIDs(
                'Service',
                array(
                    'name' => array(
                        'FOG_ALWAYS_LOGGED_IN',
                        'FOG_INACTIVITY_TIMEOUT',
                        'FOG_REGENERATE_TIMEOUT',
                    )
                ),
                'value',
                false,
                'AND',
                'name',
                false,
                ''
            );
            $this->_checkedalready = true;
        }
        if (session_status() != PHP_SESSION_NONE) {
            $_SESSION['OBSOLETE'] = false;
            if (!$this->get('authIP')
                || !$this->get('authUserAgent')
            ) {
                return false;
            } elseif ($this->get('authIP')
                && $this->get('authIP') != self::$remoteaddr
            ) {
                if (!$_SESSION['FOG_MESSAGES']) {
                    self::setMessage(_('IP Address Changed'));
                }
                if (isset($_SESSION['OBSOLETE'])) {
                    $_SESSION['OBSOLETE'] = true;
                }
            } elseif ($this->get('authUserAgent')
                && $this->get('authUserAgent') != self::$useragent
            ) {
                if (!$_SESSION['FOG_MESSAGES']) {
                    self::setMessage(_('User Agent Changed'));
                }
                if (isset($_SESSION['OBSOLETE'])) {
                    $_SESSION['OBSOLETE'] = true;
                }
            } elseif ($this->get('authID')
                && $this->_sessionID != $this->get('authID')
            ) {
                if (!$_SESSION['FOG_MESSAGES']) {
                    self::setMessage(_('Session altered improperly'));
                }
                if (isset($_SESSION['OBSOLETE'])) {
                    $_SESSION['OBSOLETE'] = true;
                }
            } elseif ($this->get('authLastActivity')
                && !$this->_alwaysloggedin
            ) {
                $active = time() - $this->get('authLastActivity');
                $timeout = $this->_inactivitySessionTimeout * 60 * 60;
                if ($active >= $timeout) {
                    self::setMessage(self::$foglang['SessionTimeout']);
                    if (isset($_SESSION['OBSOLETE'])) {
                        $_SESSION['OBSOLETE'] = true;
                    }
                }
            }
            if (isset($_SESSION['OBSOLETE'])
                && $_SESSION['OBSOLETE']
            ) {
                $_SESSION['OBSOLETE'] = false;
                self::redirect('index.php?node=logout');
            }
        }
        $authTime = time() - $this->get('authTime');
        $regenTime = $this->_regenerateSessionTimeout * 60 * 60;
        if ($authTime > $regenTime) {
            $this->_sessionID = session_id();
            session_write_close();
            session_start();
            session_id($this->_sessionID);
            $this
                ->set('authID', $this->_sessionID)
                ->set('authTime', time());
        }
        $this->set('authLastActivity', time());
        if (session_status() != PHP_SESSION_NONE) {
            if (!isset($_SESSION['FOG_USER'])) {
                $_SESSION['FOG_USER'] = $this->get('id');
            }
        }
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
        $this
            ->set('id', 0)
            ->set('name', '')
            ->set('password', '', '');
        if (session_status() == PHP_SESSION_NONE) {
            return;
        }
        $messages = $_SESSION['FOG_MESSAGES'];
        // Destroy session
        unset($this->_sessionID);
        $this
            ->set('authID', null)
            ->set('authIP', null)
            ->set('authTime', null)
            ->set('authLastActivity', null);
        session_unset();
        session_destroy();
        session_write_close();
        session_start();
        $_SESSION=array();
        if (isset($messages)) {
            self::setMessage($messages);
        }
    }
}
