<?php
class ProcessLogin extends FOGBase {
    private $username, $password, $rangSet;
    private $mobileMenu, $langMenu;
    private $lang;
    private function getLanguages() {
        $translang = $this->transLang();
        ob_start();
        foreach ((array)self::$foglang['Language'] AS $i => &$lang) {
            echo $lang;
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
        $deflang = self::getSetting('FOG_DEFAULT_LOCALE');
        return array($deflang,self::$foglang['Language'][$deflang]);
    }
    private function transLang() {
        switch($_SESSION['locale']) {
        case 'en_US':
            return self::$foglang['Language']['en'];
        case 'it_IT':
            return self::$foglang['Language']['it'];
        case 'es_ES':
            return self::$foglang['Language']['es'];
        case 'fr_FR':
            return self::$foglang['Language']['fr'];
        case 'zh_CN':
            return self::$foglang['Language']['zh'];
        case 'de_DE':
            return self::$foglang['Language']['de'];
        case 'pt_BR':
            return self::$foglang['Language']['pt'];
        default :
            $lang = $this->defaultLang();
            return self::$foglang['Language'][$lang[0]];
        }
    }
    private function specLang() {
        switch ($this->lang[1]) {
        case self::$foglang['Language']['en']:
            $this->lang = 'en_US';
            break;
        case self::$foglang['Language']['fr']:
            $this->lang = 'fr_FR';
            break;
        case self::$foglang['Language']['it']:
            $this->lang = 'it_IT';
            break;
        case self::$foglang['Language']['zh']:
            $this->lang = 'zh_CN';
            break;
        case self::$foglang['Language']['es']:
            $this->lang = 'es_ES';
            break;
        case self::$foglang['Language']['de']:
            $this->lang = 'de_DE';
            break;
        case self::$foglang['Language']['pt']:
            $this->lang = 'pt_BR';
            break;
        default :
            $this->lang = $this->defaultLang();
            $this->specLang();
            break;
        }
    }
    public function setLang() {
        $langs = array(
            'en_US' => true,
            'fr_FR' => true,
            'it_IT' => true,
            'zh_CN' => true,
            'es_ES' => true,
            'de_DE' => true,
            'pt_BR' => true,
        );
        $this->lang = !isset($_REQUEST['ulang']) ? $this->defaultLang() : array('',$_REQUEST['ulang']);
        $this->specLang();
        $_SESSION['locale'] = $this->lang;
        setlocale(LC_MESSAGES,$_SESSION['locale'].'.UTF-8');
        $domain = 'messages';
        bindtextdomain($domain,'./languages');
        bind_textdomain_codeset($domain,'UTF-8');
        textdomain($domain);
    }
    private function setRedirMode() {
        foreach ($_REQUEST AS $key => &$value) $redirect[$key] = $value;
        unset($redirect['upass'],$redirect['uname'],$redirect['ulang']);
        if (in_array($redirect['node'],array('login','logout'))) unset($redirect['node']);
        foreach ((array)$redirect AS $key => $value) {
            if (!$value) continue;
            $http_query[$key] = $value;
            unset($value);
        }
        if (count($http_query) < 1) $this->redirect('index.php');
        $this->redirect(sprintf('%s?%s','index.php',http_build_query($http_query)));
    }
    public function processMainLogin() {
        $this->setLang();
        if (!(isset($_REQUEST['uname']) && isset($_REQUEST['upass']))) return;
        $this->username = trim($_REQUEST['uname']);
        $this->password = trim($_REQUEST['upass']);
        if (!self::$FOGUser->isValid()) self::$FOGUser = self::$FOGCore->attemptLogin($this->username,$this->password);
        if (!self::$FOGUser->isValid()) {
            self::$HookManager->processEvent('USER_LOGGING_IN',array('User'=>self::$FOGUser,'username'=>$this->username));
            return;
        }
        if (!self::$isMobile) {
            if (self::$FOGUser->get('type')) {
                $this->setMessage(self::$foglang['NotAllowedHere']);
                $this->redirect('index.php?node=logout');
            }
            self::$HookManager->processEvent('LoginSuccess',array('user'=>self::$FOGUser,'username'=>$this->username));
        }
    }
    public function mainLoginForm() {
        $this->setLang();
        if (in_array($_REQUEST['node'],array('login','logout'))) {
            $this->setMessage($_SESSION['FOG_MESSAGES']);
            $this->redirect('index.php');
        }
        echo '<form method="post" action="" id="login-form">';
        if ($_REQUEST['node'] != 'logout') {
            foreach ($_REQUEST AS $key => &$value) {
                printf('<input type="hidden" name="%s" value="%s"/>',$key,$value);
                unset($value);
            }
        }
        $this->getLanguages();
        printf('<form method="post" action="" id="login-form"><label for="username">%s</label><input type="text" class="input" name="uname" id="username"/><label for="password">%s</label><input type="password" class="input" name="upass" id="password"/><label for="language">%s</label><select name="ulang" id="language">%s</select><label for="login-form-submit"> </label><input type="submit" value="%s" id="login-form-submit"/></form><div id="login-form-info"><p>%s: <b><i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i></b></p><p>%s: <b><i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i></b></p></div>',self::$foglang['Username'],self::$foglang['Password'],self::$foglang['LanguagePhrase'],$this->langMenu,self::$foglang['Login'],self::$foglang['FOGSites'],self::$foglang['LatestVer']);
    }
    public function mobileLoginForm() {
        if (!$_SESSION['locale']) $this->setLang();
        if (in_array($_REQUEST['node'],array('login','logout'))) $this->redirect('index.php');
        $this->getLanguages();
        printf('<div class="c"><p>%s</p><form method="post" action=""><br/><br/><label for="username">%s: </label><input type="text" name="uname" id="username"/><br/><br/><label for="password">%s: </label><input type="password" name="upass" id="password"/><br/><br/><label for="language">%s: </label><select name="ulang" id="language">%s</select><br/><br/><label for="login-form-submit"> </label><input type="submit" value="%s" id="login-form-submit"/></form></div>',self::$foglang['FOGMobile'],self::$foglang['Username'],self::$foglang['Password'],self::$foglang['LanguagePhrase'],$this->langMenu,self::$foglang['Login']);
    }
}
