<?php
/**
 * Processes the current login.
 *
 * PHP version 5
 *
 * @category ProcessLogin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Processes the current login.
 *
 * @category ProcessLogin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ProcessLogin extends FOGPage
{
    /**
     * The username to process.
     *
     * @var string
     */
    private $_username;
    /**
     * The password to process.
     *
     * @var string
     */
    private $_password;
    /**
     * The language menu.
     *
     * @var string
     */
    private $_langMenu;
    /**
     * The locale set.
     *
     * @var string
     */
    private $_lang;
    /**
     * Initialize the class.
     *
     * @param string $name The name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        parent::__construct($name);
        $this->_lang = self::getSetting('FOG_DEFAULT_LOCALE');
    }
    /**
     * Gets the languages into a string.
     *
     * @return void
     */
    private function _getLanguages()
    {
        $translang = $this->_transLang();
        ob_start();
        foreach ((array)self::$foglang['Language'] as &$lang) {
            printf(
                '<option value="%s"%s>%s</option>',
                $lang,
                ($translang == $lang ? ' selected' : ''),
                $lang
            );
            unset($lang);
        }
        $this->_langMenu = ob_get_clean();
    }
    /**
     * The default lang.
     *
     * @return string
     */
    private function _defaultLang()
    {
        return $this->_lang;
    }
    /**
     * The translation.
     *
     * @return string
     */
    private function _transLang()
    {
        switch ($_SESSION['locale']) {
        case 'de_DE':
            return self::$foglang['Language']['de'];
        case 'en_US':
            return self::$foglang['Language']['en'];
        case 'es_ES':
            return self::$foglang['Language']['es'];
        case 'fr_FR':
            return self::$foglang['Language']['fr'];
        case 'it_IT':
            return self::$foglang['Language']['it'];
        case 'pt_BR':
            return self::$foglang['Language']['pt'];
        case 'zh_CN':
            return self::$foglang['Language']['zh'];
        default:
            return self::$foglang['Language'][$this->_defaultLang()];
        }
    }
    /**
     * Set the session language.
     *
     * @return void
     */
    private function _specLang()
    {
        if (isset($_REQUEST['ulang'])) {
            $_SESSION['locale'] = $_REQUEST['ulang'];
        } else {
            $_SESSION['locale'] = $this->_transLang();
        }
        switch ($_SESSION['locale']) {
        case self::$foglang['Language']['de']:
            $_SESSION['locale'] = 'de_DE';
            break;
        case self::$foglang['Language']['en']:
            $_SESSION['locale'] = 'en_US';
            break;
        case self::$foglang['Language']['es']:
            $_SESSION['locale'] = 'es_ES';
            break;
        case self::$foglang['Language']['fr']:
            $_SESSION['locale'] = 'fr_FR';
            break;
        case self::$foglang['Language']['it']:
            $_SESSION['locale'] = 'it_IT';
            break;
        case self::$foglang['Language']['pt']:
            $_SESSION['locale'] = 'pt_BR';
            break;
        case self::$foglang['Language']['zh']:
            $_SESSION['locale'] = 'zh_CN';
            break;
        default:
            $_SESSION['locale'] = $this->_transLang();
        }
    }
    /**
     * Sets the language we need.
     *
     * @return void
     */
    public function setLang()
    {
        $langs = array(
            'de_DE' => true,
            'en_US' => true,
            'es_ES' => true,
            'fr_FR' => true,
            'it_IT' => true,
            'pt_BR' => true,
            'zh_CN' => true,
        );
        $this->_specLang();
        setlocale(
            LC_MESSAGES,
            sprintf(
                '%s.UTF-8',
                $_SESSION['locale']
            )
        );
        $domain = 'messages';
        bindtextdomain(
            $domain,
            './languages'
        );
        bind_textdomain_codeset(
            $domain,
            'UTF-8'
        );
        textdomain($domain);
    }
    /**
     * Sets the redirection we need.
     *
     * @return void
     */
    private function _setRedirMode()
    {
        foreach ($_GET as $key => &$value) {
            $redirect[$key] = $value;
            unset($value);
        }
        unset($redirect['upass'], $redirect['uname'], $redirect['ulang']);
        if (in_array($redirect['node'], array('login', 'logout'))) {
            unset($redirect['node']);
        }
        foreach ((array)$redirect as $key => &$value) {
            if (!$value) {
                continue;
            }
            $http_query[$key] = $value;
            unset($value);
        }
        if (count($http_query) < 1) {
            unset($_REQUEST['login']);
            $this->redirect('index.php');
        }
        $query = trim(http_build_query($http_query));
        $redir = 'index.php';
        if ($query) {
            $redir .= "?$query";
        }
        $this->redirect($redir);
    }
    /**
     * Processes the login.
     *
     * @return void
     */
    public function processMainLogin()
    {
        global $currentUser;
        $this->setLang();
        $this->_username = trim($_REQUEST['uname']);
        $this->_password = trim($_REQUEST['upass']);
        $type = self::$FOGUser->get('type');
        self::$HookManager
            ->processEvent(
                'USER_TYPE_HOOK',
                array('type' => &$type)
            );
        if (!self::$isMobile) {
            if ($type) {
                $this->setMessage(self::$foglang['NotAllowedHere']);
                unset($_REQUEST['login']);
                self::$FOGUser->logout();
            }
        }
        if (!isset($_REQUEST['login'])) {
            return;
        }
        if (!$this->_username) {
            $this->setMessage(self::$foglang['InvalidLogin']);
            $this->redirect('index.php?node=logout');
        }
        self::$FOGUser = self::$FOGCore->attemptLogin(
            $this->_username,
            $this->_password
        );
        if (!self::$FOGUser->isValid()) {
            $this->_setRedirMode();
        }
        self::$HookManager
            ->processEvent(
                'LoginSuccess',
                array(
                    'username' => $this->_username,
                    'password' => $this->_password
                )
            );
        $this->_setRedirMode();
    }
    /**
     * Displays the main login form (non-mobile).
     *
     * @return void
     */
    public function mainLoginForm()
    {
        $this->setLang();
        if (in_array($_REQUEST['node'], array('login', 'logout'))) {
            $this->setMessage($_SESSION['FOG_MESSAGES']);
            unset($_REQUEST['login']);
            $this->redirect('index.php');
        }
        if ($_REQUEST['node'] != 'logout') {
            foreach ($_REQUEST as $key => &$value) {
                printf(
                    '<input type="hidden" name="%s" value="%s"/>',
                    $key,
                    $value
                );
                unset($value);
            }
        }
        $this->_getLanguages();
        $logininfo = self::getSetting('FOG_LOGIN_INFO_DISPLAY');
        $extra = '';
        if ($logininfo) {
            $extra = sprintf(
                '<div id="login-form-info">'
                . '<p>%s: <b><i class="icon fa fa-circle-o-notch fa-spin fa-fw">'
                . '</i></b></p><p>%s: <b><i class="icon fa fa-circle-o-notch fa-'
                . 'spin fa-fw"></i></b></p><p>%s: <b><i class="icon fa fa-circle-'
                . 'o-notch fa-spin fa-fw"></i></b></p><p>%s: <b><i class="icon '
                . 'fa fa-circle-o-notch fa-spin fa-fw"></i></b></p></div>',
                self::$foglang['FOGSites'],
                self::$foglang['LatestVer'],
                self::$foglang['LatestDevVer'],
                self::$foglang['LatestSvnVer']
            );
        }
        printf(
            '<form method="post" action="%s" id="login-form">'
            . '<label for="username">%s</label>'
            . '<input type="text" class="input" name="uname" id="username"/>'
            . '<label for="password">%s</label>'
            . '<input type="password" class="input" name="upass" id="password"/>'
            . '<label for="language">%s</label>'
            . '<select name="ulang" id="language">%s</select>'
            . '<label for="login-form-submit"> </label>'
            . '<input type="submit" value="%s" id="login-form-submit" name="login"/>'
            . '</form>%s',
            $this->formAction,
            self::$foglang['Username'],
            self::$foglang['Password'],
            self::$foglang['LanguagePhrase'],
            $this->_langMenu,
            self::$foglang['Login'],
            $extra
        );
    }
    /**
     * Display the login form for the mobile page.
     *
     * @return void
     */
    public function mobileLoginForm()
    {
        $this->setLang();
        if (in_array($_REQUEST['node'], array('login', 'logout'))) {
            unset($_REQUEST['login']);
            $this->redirect('index.php');
        }
        $this->_getLanguages();
        printf(
            '<div class="c"><p>%s</p>'
            . '<form method="post" action="">'
            . '<br/><br/>'
            . '<label for="username">%s: </label>'
            . '<input type="text" name="uname" id="username"/><br/><br/>'
            . '<label for="password">%s: </label>'
            . '<input type="password" name="upass" id="password"/><br/><br/>'
            . '<label for="language">%s: </label>'
            . '<select name="ulang" id="language">%s</select>'
            . '<br/><br/><label for="login-form-submit"> </label>'
            . '<input type="submit" value="%s" id="login-form-submit" name="login"/>'
            . '</form></div>',
            self::$foglang['FOGMobile'],
            self::$foglang['Username'],
            self::$foglang['Password'],
            self::$foglang['LanguagePhrase'],
            $this->_langMenu,
            self::$foglang['Login']
        );
    }
}
