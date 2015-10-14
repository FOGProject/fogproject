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
        if (crypt($password,$this->get('password')) == $this->get('password')) $res = $this;
        if ($res) {
            $this
                ->set('authUserAgent',$_SERVER['HTTP_USER_AGENT'])
                ->set('authIP',$_SERVER['REMOTE_ADDR'])
                ->set('authTime',time())
                ->set('authLastActivity',time());
        } else if (md5($password) == $this->get('password')) {
            $this->set('password',$password)->save();
            return $this->validate_pw($password);
        }
        return $res;
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
        session_name('FOG_GUI_Session');
        $domain = isset($_SERVER['SERVER_NAME']);
        $https = isset($_SERVER['HTTPS']);
        session_set_cookie_params(0,'/',$domain,$secure,true);
        session_start();
        if (!isset($this->sessionID)) $this->sessionID = session_id();
        if ($this->get('authIP') && $this->get('authIP') != $_SERVER['REMOTE_ADDR']) {
            $this->setMessage(_('IP Address Changed'));
            $this->logout();
            $this->redirect('index.php');
            return false;
        }
        if ($this->get('authUserAgent') && $this->get('authUserAgent') != $_SERVER['HTTP_USER_AGENT']) {
            $this->setMessage(_('User Agent Changed'));
            $this->logout();
            $this->redirect('index.php');
            return false;
        }
        if ($this->get('authID') && $this->sessionID != $this->get('authID')) {
            $this->setMessage(_('Session ID has changed'));
            $this->logout();
            $this->redirect('index.php');
            return false;
        }
        if (!$this->alwaysloggedin && ((time() - $this->get('authLastActivity')) >= ($this->inactivitySessionTimeout*60*60))) {
            $this->setMessage($this->foglang['SessionTimeout']);
            $this->logout();
            $this->redirect('index.php');
            return false;
        }
        if ((time() - $this->get('authTime')) > ($this->regenerateSessionTimeout * 60 * 60)) {
            session_regenerate_id(false);
            $this->sessionID = session_id();
            session_write_close();
            session_id($this->sessionID);
            if (!session_id()) session_start();
            $this
                ->set('authID',$this->sessionID)
                ->set('authTime',time());
        }
        if (!$this->get('authIP') || !$this->get('authUserAgent')) return false;
        $_SESSION['FOG_USER'] = serialize($this);
        $_SESSION['FOG_USERNAME'] = $this->get('name');
        $this->set('authLastActivity',time());
        return $this;
    }
    public function logout() {
        // Destroy session
        unset($this->sessionID);
        $this->set('authID',null);
        $this->set('authIP',null);
        $this->set('authTime',null);
        $this->set('authLastActivity',null);
        $this->set('authUserAgent',null);
        $messages = $this->getMessages();
        $locale = $_SESSION['locale'];
        session_regenerate_id(true);
        session_unset();
        session_destroy();
        session_start();
        $_SESSION=array();
        $_SESSION['locale'] = $locale;
        if (isset($messages)) $this->setMessage($messages);
    }
}
