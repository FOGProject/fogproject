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
            $this
                ->set('authUserAgent',$_SERVER['HTTP_USER_AGENT'])
                ->set('authIP',$_SERVER['REMOTE_ADDR'])
                ->set('authTime',time())
                ->set('authLastActivity',time());
            $_SESSION['FOG_USER'] = serialize($this);
            $_SESSION['FOG_USERNAME'] = $this->get('name');
            return $this;
        } else {
            $this->EventManager->notify('LoginFail',array('Failure'=>$this->get('name')));
            $this->HookManager->processEvent('LoginFail',array('username'=>$this->get('name'),'password'=>&$password));
            $this->setMessage($this->foglang['InvalidLogin']);
            $this->redirect($index);
            return false;
        }
    }
    public function set($key, $value, $override = false) {
        if ($this->key($key) == 'password' && !$override) $value = $this->generate_hash($value);
        return parent::set($key, $value);
    }
    public function isLoggedIn() {
        if (!$this->checkedalready && $this->FOGCore instanceof FOGCore) {
            $this->inactivitySessionTimeout = $this->FOGCore->getSetting('FOG_INACTIVITY_TIMEOUT');
            $this->regenerateSessionTimeout = $this->FOGCore->getSetting('FOG_REGENERATE_TIMEOUT');
            $this->alwaysloggedin = (int)$this->FOGCore->getSetting('FOG_ALWAYS_LOGGED_IN');
            $this->checkedalready = true;
        }
        if (!session_id()) session_start();
        if (!isset($this->sessionID)) $this->sessionID = session_id();
        if (!$this->isValid()) return false;
        else if ($this->get('authIP') && $this->get('authIP') != $_SERVER['REMOTE_ADDR']) {
            $this->setMessage(_('IP Address Changed'));
            return false;
        } else if ($this->get('authUserAgent') && $this->get('authUserAgent') != $_SERVER['HTTP_USER_AGENT']) {
            $this->setMessage(_('User Agent Changed'));
            return false;
        } else if ($this->get('authID') && $this->sessionID != $this->get('authID')) {
            $this->setMessage(_('Session ID has changed'));
            return false;
        } else if ($this->get('authLastActivity') && !$this->alwaysloggedin && ((time() - $this->get('authLastActivity')) >= ($this->inactivitySessionTimeout*60*60))) {
            $this->setMessage($this->foglang['SessionTimeout']);
            return false;
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
        return $this;
    }
    public function logout() {
        $locale = $_SESSION['locale'];
        if ($_SESSION['FOG_MESSAGES']) $messages = $_SESSION['FOG_MESSAGES'];
        // Destroy session
        unset($this->sessionID);
        $this->set('authID',null);
        $this->set('authTime',null);
        $this->set('authLastActivity',null);
        if (!session_id()) session_start();
        session_regenerate_id(true);
        session_unset();
        session_destroy();
        session_write_close();
        session_start();
        $_SESSION=array();
        $_SESSION['locale'] = $locale;
        if (isset($messages)) $this->setMessage($messages);
    }
}
