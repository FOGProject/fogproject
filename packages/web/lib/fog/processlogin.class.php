<?php
class ProcessLogin extends FOGBase {
    private $username, $password, $currentUser, $langSet;
    private $mobileMenu, $langMenu;
    private $lang;
    private function getLanguages() {
        $translang = $this->transLang();
        ob_start();
        foreach ((array)$this->foglang['Language'] AS $i => &$lang) {
            printf('<option value="%s"%s>%s</option>',
                $lang,
                ($translang == $lang ? ' selected' : ''),
                $lang
            );
            unset($lang);
        }
        $this->langMenu = ob_get_clean();
    }
    private function defaultLang() {
        $deflang = $this->getSetting('FOG_DEFAULT_LOCALE');
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
        switch ($this->lang[1]) {
        case $this->foglang['Language']['en']:
            $this->lang = 'en_US.UTF-8';
            break;
        case $this->foglang['Language']['fr']:
            $this->lang = 'fr_FR.UTF-8';
            break;
        case $this->foglang['Language']['it']:
            $this->lang = 'it_IT.UTF-8';
            break;
        case $this->foglang['Language']['zh']:
            $this->lang = 'zh_CN.UTF-8';
            break;
        case $this->foglang['Language']['es']:
            $this->lang = 'es_ES.UTF-8';
            break;
        case $this->foglang['Language']['de']:
            $this->lang = 'de_DE.UTF-8';
            break;
        case $this->foglang['Language']['pt']:
            $this->lang = 'pt_BR.UTF-8';
            break;
        default :
            $this->lang = $this->defaultLang();
            $this->specLang();
            break;
        }
    }
    public function setLang() {
        $langs = array(
            'en_US.UTF-8' => true,
            'fr_FR.UTF-8' => true,
            'it_IT.UTF-8' => true,
            'zh_CN.UTF-8' => true,
            'es_ES.UTF-8' => true,
            'de_DE.UTF-8' => true,
            'pt_BR.UTF-8' => true,
        );
        if (!isset($this->lang)) {
            $this->lang = $this->defaultLang();
            $this->specLang();
        }
        if (!isset($langs[$this->lang])) die('Invalid language specification');
        $this->specLang();
        $_SESSION['locale'] = $this->lang;
        putenv(sprintf('LC_ALL=%s',$_SESSION['locale']));
        setlocale('LC_ALL',$_SESSION['locale']);
        bindtextdomain('messages','languages');
        textdomain('messages');
    }
    private function setCurUser($tmpUser) {
        $this->setRedirMode();
        $this->currentUser = $tmpUser;
        if (!$this->isMobile) $this->HookManager->processEvent('LoginSuccess',array('user'=>&$this->currentUser,'username'=>$this->username, 'password'=>&$this->password));
    }
    private function setRedirMode() {
        $redirect = $_REQUEST;
        unset($redirect['upass'],$redirect['uname'],$redirect['ulang']);
        if (in_array($redirect['node'],array('login','logout'))) unset($redirect['node']);
        ob_start();
        $first = true;
        foreach ((array)$redirect AS $key => &$value) {
            if ($first) {
                printf('?%s=%s',$key,$value);
                $first = false;
                continue;
            }
            if (!$value) continue;
            printf('&%s=%s',$key,$value);
            unset($value);
        }
        if (ob_get_contents()) $this->redirect(ob_get_clean());
        ob_end_clean();
        $this->redirect('index.php');
    }
    public function processMainLogin() {
        if (!$_SESSION['locale']) $this->setLang();
        if (!(isset($_REQUEST['uname']) && isset($_REQUEST['upass']))) return;
        $this->username = trim($_REQUEST['uname']);
        $this->password = trim($_REQUEST['upass']);
        $tmpUser = $this->FOGCore->attemptLogin($this->username,$this->password);
        if (!$tmpUser || !$tmpUser->isValid()) return;
        $this->HookManager->processEvent('USER_LOGGING_IN',array('User'=>&$tmpUser,'username'=>&$this->username,'password'=>&$this->password));
        if (!$this->isMobile && $tmpUser->get('type') == 1) {
            $this->setMessage($this->foglang['NotAllowedHere']);
            $this->redirect('index.php?node=logout');
        }
        $this->setCurUser($tmpUser);
    }
    public function mainLoginForm() {
        if (!$_SESSION['locale']) $this->setLang();
        if (in_array($_REQUEST['node'],array('login','logout'))) {
            $this->setMessage($_SESSION['FOG_MESSAGES']);
            $this->redirect('index.php');
        }
        echo '<form method="post" action="?node=login" id="login-form">';
        if (mb_convert_encoding($_REQUEST['node'],'UTF-8') != 'logout') {
            foreach ($_REQUEST AS $key => &$value) {
                printf('<input type="hidden" name="%s" value="%s"/>',mb_convert_encoding($key,'UTF-8'),mb_convert_encoding($value,'UTF-8'));
                unset($value);
            }
        }
        $this->getLanguages();
        printf('<form method="post" action="?node=login" id="login-form"><label for="username">%s</label><input type="text" class="input" name="uname" id="username"/><label for="password">%s</label><input type="password" class="input" name="upass" id="password"/><label for="language">%s</label><select name="ulang" id="language">%s</select><label for="login-form-submit"> </label><input type="submit" value="%s" id="login-form-submit"/></form><div id="login-form-info"><p>%s: <b><i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i></b></p><p>%s: <b><i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i></b></p></div>',$this->foglang['Username'],$this->foglang['Password'],$this->foglang['LanguagePhrase'],$this->langMenu,$this->foglang['Login'],$this->foglang['FOGSites'],$this->foglang['LatestVer']);
    }
    public function mobileLoginForm() {
        if (!$_SESSION['locale']) $this->setLang();
        if (in_array($_REQUEST['node'],array('login','logout'))) $this->redirect('index.php');
        $this->getLanguages();
        printf('<div class="c"><p>%s</p><form method="post" action="?node=login"><br/><br/><label for="username">%s: </label><input type="text" name="uname" id="username"/><br/><br/><label for="password">%s: </label><input type="password" name="upass" id="password"/><br/><br/><label for="language">%s: </label><select name="ulang" id="language">%s</select><br/><br/><label for="login-form-submit"> </label><input type="submit" value="%s" id="login-form-submit"/></form></div>',$this->foglang['FOGMobile'],$this->foglang['Username'],$this->foglang['Password'],$this->foglang['LanguagePhrase'],$this->langMenu,$this->foglang['Login']);
    }
}
