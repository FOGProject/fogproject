<?php
class User extends FOGController {
    private $inactivitySessionTimeout;
    private $regenerateSessionTimeout;
    private $alwaysloggedin;
    private $checkedalready;
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
        'authIP',
        'authTime',
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
        $this->set('authIP',$_SERVER['REMOTE_ADDR']);
        if (crypt($password,$this->get('password')) == $this->get('password')) $res = true;
        if (md5($password) == $this->get('password')) {
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
        if (!$this->checkedalready) {
            $this->inactivitySessionTimeout = $this->FOGCore->getSetting('FOG_INACTIVITY_TIMEOUT');
            $this->regenerateSessionTimeout = $this->FOGCore->getSetting('FOG_REGENERATE_TIMEOUT');
            $this->alwaysloggedin = $this->FOGCore->getSetting('FOG_ALWAYS_LOGGED_IN');
            $this->checkedalready = true;
        }
        if ($this->get('authIP') != $_SERVER['REMOTE_ADDR']) return false;
        if (!$this->alwaysloggedin && (time() - $_SESSION['LAST_ACTIVITY'] >= ($this->inactivitySessionTimeout * 60 * 60))) {
            $this->logout();
            $this->setMessage($this->foglang['SessionTimeout']);
        }
        if (!isset($_SESSION['CREATED'])) $_SESSION['CREATED'] = time();
        else if (time() - $_SESSION['CREATED'] > ($this->regenerateSessionTimeout * 60 * 60)) {
            @session_set_cookie_params(0);
            @session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
        $_SESSION['FOG_USER'] = serialize($this);
        $_SESSION['FOG_USERNAME'] = $this->get('name');
        $_SESSION['LAST_ACTIVITY'] = time();
        return $this;
    }
    public function logout() {
        // Destroy session
        $locale = $_SESSION['locale'];
        $messages = $this->getMessages();
        $this->set('authIP',null);
        @session_set_cookie_params(0);
        while(@session_unset());
        while(@session_destroy());
        $_SESSION=array();
        $_SESSION['locale'] = $locale;
        if (isset($messages)) $this->setMessage($messages);
    }
}
