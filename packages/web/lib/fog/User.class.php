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
    private function generate_hash($password, $cost = 11) {
        $salt = substr(base64_encode(openssl_random_pseudo_bytes(255)),0,22);
        $salt = str_replace("+",".",$salt);
        $param = '$'.implode('$',array(
            '2a',
            str_pad($cost,2,'0',STR_PAD_LEFT),
            $salt
        ));
        return crypt($password,$param);
    }
    public function validate_pw($password) {
        $res = false;
        if (crypt($password,$this->get('password')) == $this->get('password')) $res = true;
        else if (md5($password) == $this->get('password')) {
            $this->set('password',$password)->save();
            $res = $this->validate_pw($password);
        }
        if ($res) {
            if (!$this->sessionID) $this->sessionID = session_id();
            $this
                ->set('authUserAgent',$_SERVER['HTTP_USER_AGENT'])
                ->set('authIP',$_SERVER['REMOTE_ADDR'])
                ->set('authTime',time())
                ->set('authLastActivity',time())
                ->set('authID',$this->sessionID);
            $_SESSION['FOG_USER'] = serialize($this);
            $_SESSION['FOG_USERNAME'] = $this->get('name');
            return $this;
        } else {
            $this->EventManager->notify('LoginFail',array('Failure'=>$this->get('name')));
            $this->HookManager->processEvent('LoginFail',array('username'=>$this->get('name'),'password'=>&$password));
            $this->setMessage($this->foglang['InvalidLogin']);
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        }
    }
    public function set($key, $value, $override = false) {
        if ($this->key($key) == 'password' && !$override) $value = $this->generate_hash($value);
        return parent::set($key, $value);
    }
    public function isLoggedIn() {
        if (!$this->checkedalready) {
            $this->inactivitySessionTimeout = $this->getSetting('FOG_INACTIVITY_TIMEOUT');
            $this->regenerateSessionTimeout = $this->getSetting('FOG_REGENERATE_TIMEOUT');
            $this->alwaysloggedin = (int)$this->getSetting('FOG_ALWAYS_LOGGED_IN');
            $this->checkedalready = true;
        }
        if (!$this->get('authIP') || !$this->get('authUserAgent')) return false;
        else if ($this->get('authIP') && $this->get('authIP') != $_SERVER['REMOTE_ADDR']) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('IP Address Changed'));
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authUserAgent') && $this->get('authUserAgent') != $_SERVER['HTTP_USER_AGENT']) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('User Agent Changed'));
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authID') && $this->sessionID != $this->get('authID')) {
            if (!$_SESSION['FOG_MESSAGES']) $this->setMessage(_('Session altered improperly'));
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        } else if ($this->get('authLastActivity') && !$this->alwaysloggedin && ((time() - $this->get('authLastActivity')) >= ($this->inactivitySessionTimeout*60*60))) {
            $this->setMessage($this->foglang['SessionTimeout']);
            if (!isset($_SESSION['OBSOLETE'])) $_SESSION['OBSOLETE'] = true;
        }
        if ($_SESSION['OBSOLETE']) {
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
            $_SESSION['FOG_USER'] = serialize($this);
            $_SESSION['FOG_USERNAME'] = $this->get('name');
        }
        return true;
    }
    public function logout() {
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
