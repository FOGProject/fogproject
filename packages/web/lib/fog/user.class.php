<?php
class User extends FOGController {
    private $inactivitySessionTimeout;
    private $regenerateSessionTimeout;
    private $alwaysloggedin;
    private $checkedalready;
    private $sessionID;
    protected $databaseTable = 'users';
    protected $databaseFields = array(
        'id' => 'uId',
        'name' => 'uName',
        'password' => 'uPass',
        'createdTime' => 'uCreateDate',
        'createdBy' => 'uCreateBy',
        'type' => 'uType'
    );
    protected $databaseFieldsRequired = array(
        'name',
        'password',
    );
    protected $additionalFields = array(
        'authID',
        'authIP',
        'authTime',
        'authLastActivity',
        'authUserAgent',
    );
    public static function generate_hash($password, $cost = 11) {
        return password_hash($password,PASSWORD_BCRYPT,['cost'=>$cost]);
    }
    public function password_validate($username,$password,$adminTest = false) {
        $tmpUser = self::getClass('User')->set('name',$username)->load('name');
        if (preg_match('#^[a-f0-9]{32}$#',$tmpUser->get('password')) && md5($password) === $tmpUser->get('password')) $tmpUser->set('password',$password)->save();
        $passValid = (bool)password_verify($password,$tmpUser->get('password'));
        if ($adminTest === true) $tmpUser->get('type') > 0 ? $passValid = false : null;
        return $passValid;
    }
    public function validate_pw($username,$password) {
        if ($this->password_validate($username,$password)) {
            $tmpUser = self::getClass('User')->set('name',$username)->load('name');
            if (preg_match('#^[a-f0-9]{32}$#',$tmpUser->get('password')) && md5($password) === $tmpUser->get('password')) $tmpUser->set('password',$password)->save();
            $this
                ->set('id',$tmpUser->get('id'))
                ->set('name',$tmpUser->get('name'))
                ->set('password','',true)
                ->set('type',$tmpUser->get('type'));
            unset($tmpUser);
            if (!$this->sessionID) $this->sessionID = session_id();
            $this
                ->set('authUserAgent',$_SERVER['HTTP_USER_AGENT'])
                ->set('authIP',$_SERVER['REMOTE_ADDR'])
                ->set('authTime',time())
                ->set('authLastActivity',time())
                ->set('authID',$this->sessionID);
            $_SESSION['FOG_USER'] = $this->get('id');
            $_SESSION['FOG_USERNAME'] = $this->get('name');
            $this->log(sprintf('%s %s.',$this->get('name'),_('user successfully logged in')));
            $this->isLoggedIn();
        } else {
            $this->log(sprintf('%s %s.',$this->get('name'),_('user failed to login'),$this->get('name')));
            self::$EventManager->notify('LoginFail',array('Failure'=>$username));
            self::$HookManager->processEvent('LoginFail',array('username'=>$username,'password'=>&$password));
            $this->setMessage(self::$foglang['InvalidLogin']);
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        }
        return $this;
    }
    public function set($key, $value, $override = false) {
        if ($this->key($key) == 'password' && !$override) $value = self::generate_hash($value);
        return parent::set($key, $value);
    }
    private function isLoggedIn() {
        if (!$this->isValid()) return false;
        if (!$this->checkedalready) {
            $this->inactivitySessionTimeout = self::getSetting('FOG_INACTIVITY_TIMEOUT');
            $this->regenerateSessionTimeout = self::getSetting('FOG_REGENERATE_TIMEOUT');
            $this->alwaysloggedin = (int)self::getSetting('FOG_ALWAYS_LOGGED_IN');
            $this->checkedalready = true;
        }
        $_SESSION['OBSOLETE'] = false;
        if (!$this->get('authIP') || !$this->get('authUserAgent')) return false;
        else if ($this->get('authIP') && $this->get('authIP') != $_SERVER['REMOTE_ADDR']) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('IP Address Changed'));
            if (isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authUserAgent') && $this->get('authUserAgent') != $_SERVER['HTTP_USER_AGENT']) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('User Agent Changed'));
            if (isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authID') && $this->sessionID != $this->get('authID')) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('Session altered improperly'));
            if (isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authLastActivity') && !$this->alwaysloggedin && ((time() - $this->get('authLastActivity')) >= ($this->inactivitySessionTimeout*60*60))) {
            $this->setMessage(self::$foglang['SessionTimeout']);
            if (isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        }
        if (isset($_SESSION['OBSOLETE']) && $_SESSION['OBSOLETE']) {
            $_SESSION['OBSOLETE'] = false;
            $this->redirect('index.php?node=logout');
        }
        if ((time() - $this->get('authTime')) > ($this->regenerateSessionTimeout * 60 * 60)) {
            session_regenerate_id(false);
            $this->sessionID = session_id();
            session_write_close();
            session_id($this->sessionID);
            session_start();
            $this
                ->set('authID',$this->sessionID)
                ->set('authTime',time());
        }
        $this->set('authLastActivity',time());
        if (!isset($_SESSION['FOG_USER'])) {
            $_SESSION['FOG_USER'] = $this->get('id');
            $_SESSION['FOG_USERNAME'] = $this->get('name');
        }
        return true;
    }
    public function logout() {
        $this->set('id',0)
            ->set('name','')
            ->set('password','','');
        if (session_status() == PHP_SESSION_NONE) return;
        $locale = $_SESSION['locale'];
        $messages = $_SESSION['FOG_MESSAGES'];
        // Destroy session
        unset($this->sessionID);
        $this
            ->set('authID',null)
            ->set('authIP',null)
            ->set('authTime',null)
            ->set('authLastActivity',null);
        session_unset();
        session_destroy();
        session_write_close();
        session_start();
        $_SESSION=array();
        $_SESSION['locale'] = $locale;
        if (isset($messages)) $this->setMessage($messages);
    }
}
