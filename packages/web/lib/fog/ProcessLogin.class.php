<?php
class ProcessLogin extends FOGBase {
    private $username, $password, $currentUser, $langSet;
    private $mobileMenu, $langMenu;
    private function getLanguages() {
        $translang = $this->transLang();
        foreach($this->foglang['Language'] AS $i => &$lang) $this->langMenu .= '<option value="'.$lang.'" '.($translang == $lang ? 'selected="selected"' : '').'>'.$lang.'</option>';
        unset($lang);
    }
    private function defaultLang() {
        $deflang = $this->FOGCore->getSetting('FOG_DEFAULT_LOCALE');
        foreach($this->foglang['Language'] AS $lang => &$val) {
            if ($deflang == $lang) $data = array($lang,$val);
            else $data = array('en','English');
        }
        unset($val);
        return $data;
    }
    private function transLang() {
        switch($_SESSION['locale']) {
        case 'en_US.UTF-8':
            return $this->foglang['Language']['en'];
            break;
        case 'it_IT.UTF-8':
            return $this->foglang['Language']['it'];
            break;
        case 'es_ES.UTF-8':
            return $this->foglang['Language']['es'];
            break;
        case 'fr_FR.UTF-8':
            return $this->foglang['Language']['fr'];
            break;
        case 'zh_CN.UTF-8':
            return $this->foglang['Language']['zh'];
            break;
        case 'de_DE.UTF-8':
            return $this->foglang['Language']['de'];
            break;
        case 'pt_BR.UTF-8':
            return $this->foglang['Language']['pt'];
            break;
        default :
            $lang = $this->defaultLang();
            return $this->foglang['Language'][$lang[0]];
            break;
        }
    }
    private function specLang() {
        switch ($_REQUEST['ulang']) {
        case $this->foglang['Language']['en']:
            $_REQUEST['ulang'] = 'en_US.UTF-8';
            break;
        case $this->foglang['Language']['fr']:
            $_REQUEST['ulang'] = 'fr_FR.UTF-8';
            break;
        case $this->foglang['Language']['it']:
            $_REQUEST['ulang'] = 'it_IT.UTF-8';
            break;
        case $this->foglang['Language']['zh']:
            $_REQUEST['ulang'] = 'zh_CN.UTF-8';
            break;
        case $this->foglang['Language']['es']:
            $_REQUEST['ulang'] = 'es_ES.UTF-8';
            break;
        case $this->foglang['Language']['de']:
            $_REQUEST['ulang']	= 'de_DE.UTF-8';
            break;
        case $this->foglang['Language']['pt']:
            $_REQUEST['ulang'] = 'pt_BR.UTF-8';
            break;
        default :
            $lang = $this->defaultLang();
            $_REQUEST['ulang'] = $lang[1];
            break;
        }
    }
    public function setLang() {
        if ($_REQUEST['ulang']) {
            $this->specLang();
            $_SESSION['locale'] = $_REQUEST['ulang'];
            putenv("LC_ALL=".$_SESSION['locale']);
            setlocale('LC_ALL',$_SESSION['locale']);
            bindtextdomain('messages','languages');
            textdomain('messages');
        }
    }
    private function setCurUser($tmpUser) {
        $this->setRedirMode();
        $this->currentUser = $tmpUser;
        // Hook
        if (!$this->isMobile) $this->HookManager->processEvent('LoginSuccess',array('user'=>&$this->currentUser,'username'=>$this->username, 'password'=>&$this->password));
    }
    private function setRedirMode() {
        $redirect = array_merge($_GET, $_POST);
        unset($redirect['upass'],$redirect['uname'],$redirect['ulang']);
        if (in_array($redirect['node'],array('login','logout'))) unset($redirect['node']);
        foreach ($redirect AS $key => &$value) $redirectData[] = $key.'='.$value;
        unset($value);
        $this->redirect(($redirectData ? '?' . implode('&',(array)$redirectData) : ''));
    }
    public function processMainLogin() {
        $this->setLang();
        if(isset($_REQUEST['uname']) && isset($_REQUEST['upass'])) {
            $this->username = trim($_REQUEST['uname']);
            $this->password = trim($_REQUEST['upass']);
            $tmpUser = $this->FOGCore->attemptLogin($this->username,$this->password);
            // Hook
            $this->HookManager->processEvent('USER_LOGGING_IN',array('User'=>&$tmpUser,'username'=>&$this->username,'password'=>&$this->password));
            if (!($tmpUser && $tmpUser->isValid())) {
                $this->setMessage($this->foglang['InvalidLogin']);
                $this->getClass('User')->logout();
                $this->redirect('index.php');
            } else if (!$this->isMobile && $tmpUser->get('type') != 0) {
                $this->setMessage($this->foglang['NotAllowedHere']);
                $this->getClass('User')->logout();
                $this->redirect('index.php');
            } else $this->setCurUser($tmpUser);
        }
    }
    public function mainLoginForm() {
        $this->setLang();
        if (in_array($_GET['node'],array('login','logout'))) $this->redirect('index.php');
        echo '<form method="post" action="?node=login" id="login-form">';
        if ($_GET['node'] != 'logout') {
            foreach ($_GET AS $key => &$value) echo '<input type ="hidden" name="'.$key.'" value="'.$value.'" />';
            unset($value);
        }
        echo '<label for="username">'.$this->foglang['Username'].'</label><input type="text" class="input" name="uname" id="username" /><label for="password">'.$this->foglang['Password'].'</label><input type="password" class="input" name="upass" id="password" /><label for="language">'.$this->foglang['LanguagePhrase'].'</label>';
        $this->getLanguages();
        echo '<select name="ulang" id="language">'.$this->langMenu.'</select><label for="login-form-submit">&nbsp;</label><input type="submit" value="'.$this->foglang['Login'].'" id="login-form-submit" /></form><div id="login-form-info"><p>'.$this->foglang['FOGSites'].': <b><i class="icon fa fa-circle-o-notch fa-spin fa-1x"></i></b></p><p>'.$this->foglang['LatestVer'].': <b><i class="icon fa fa-circle-o-notch fa-spin fa-1x"></i></b></p></div>';
    }
    public function mobileLoginForm() {
        $this->setLang();
        echo '<center><div class="login"><p class="loginTitle">'.$this->foglang['FOGMobile'].'</p><form method="post" action="?node=login"><div class="loginElement">'.$this->foglang['Username'].':</div><div class="loginElement"><input type="text" class="login" name="uname" /></div><div class="loginElement">'.$this->foglang['Password'].':</div><div class="loginElement"><input type="password" class="login" name="upass" /></div>'."\n";
        $this->getLanguages();
        echo '<div class="loginElement">'.$this->foglang['LanguagePhrase'].':</div><div class="loginElement"><select class="login" name="ulang">';
        echo $this->langMenu;
        echo '</select></div><p><input type="submit" value="'.$this->foglang['Login'].'" /></p></form></div></center>';
    }
}
