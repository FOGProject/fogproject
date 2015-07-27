<?php
class User extends FOGController {
    // Variables
    public $inactivitySessionTimeout,$regenerateSessionTimeout,$alwaysloggedin,$checkedalready;
    // Table
    public $databaseTable = 'users';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'uId',
        'name' => 'uName',
        'password' => 'uPass',
        'createdTime' => 'uCreateDate',
        'createdBy' => 'uCreateBy',
        'type' => 'uType'
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'authIP',
        'authTime',
    );
    private function generate_hash($password, $cost = 11) {
        $salt = substr(base64_encode(openssl_random_pseudo_bytes(255)),0,22);
        $salt = str_replace("+",".",$salt);
        $param = '$'.implode('$',array(
            '2a',
            str_pad($cost,2,"0",STR_PAD_LEFT),
            $salt
        ));
        return crypt($password,$param);
    }
    public function validate_pw($password) {
        $res = false;
        $this->set(authIP,$_SERVER[REMOTE_ADDR]);
        if (crypt($password,$this->get(password)) == $this->get(password)) $res = true;
        if (md5($password) == $this->get(password)) {
            $this->set(password,$password)->save();
            return $this->validate_pw($password);
        }
        return $res;
    }
    public function set($key, $value) {
        if ($this->key($key) == 'password') $value = $this->generate_hash($value);
        // Set
        return parent::set($key, $value);
    }
    public function isLoggedIn() {
        if (!$this->checkedalready) {
            $this->inactivitySessionTimeout = $this->FOGCore->getSetting(FOG_INACTIVITY_TIMEOUT);
            $this->regenerateSessionTimeout = $this->FOGCore->getSetting(FOG_REGENERATE_TIMEOUT);
            $this->alwaysloggedin = $this->FOGCore->getSetting(FOG_ALWAYS_LOGGED_IN);
            $this->checkedalready = true;
        }
        // Has IP Address has changed
        if ($this->get(authIP) != $_SERVER[REMOTE_ADDR]) return false;
        // Has session expired due to inactivity
        if (!$this->alwaysloggedin && isset($_SESSION[LAST_ACTIVITY]) && (time() - $_SESSION[LAST_ACTIVITY] >= ($this->inactivitySessionTimeout * 60 * 60))) {
            // Logout
            $this->logout();
            // Set Message -> Redirect to invoke login page
            $this->FOGCore->setMessage($this->foglang[SessionTimeout]);//->redirect();
            // Logged out
            return false;
        }
        // Update last activity
        $_SESSION[LAST_ACTIVITY] = time();
        // Regenerate session ID every 30minutes to aviod session fixation - https://www.owasp.org/index.php/Session_fixation
        if (!isset($_SESSION[CREATED])) $_SESSION[CREATED] = time();
        else if (!headers_sent() && time() - $_SESSION[CREATED] > ($this->regenerateSessionTimeout * 60 * 60)) {
            // reset session
            @session_set_cookie_params(0);
            @session_regenerate_id(true);
            $_SESSION[CREATED] = time();
        }
        // Logged in
        $_SESSION[FOG_USER] = serialize($this);
        $_SESSION[FOG_USERNAME] = $this->get(name);
        return $this;
    }
    public function logout() {
        // Destroy session
        $locale = $_SESSION['locale'];
        $this->set('authIP',null);
        @session_write_close();
        @session_set_cookie_params(0);
        @session_start();
        @session_regenerate_id(true);
        @session_unset();
        @session_destroy();
        $_SESSION=array();
        $_SESSION['locale'] = $locale;
        $this->FOGCore->redirect('index.php');
    }
}
