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
        require __DIR__ . '/../../commons/text.php';
        $this->_lang = self::$locale;
        $this->setLang();
    }
    /**
     * Index page.
     *
     * @return void
     */
    public function index()
    {
        if (self::$FOGUser->isValid()) {
            self::redirect('../management/index.php?node=home');
        }
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
        switch ($this->_lang) {
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
        $ulang = filter_input(INPUT_POST, 'ulang');
        if (isset($ulang)) {
            $this->_lang = self::$locale = $ulang;
        } else {
            $this->_lang = self::$locale = $this->_transLang();
        }
        switch ($this->_lang) {
        case self::$foglang['Language']['de']:
            $this->_lang = self::$locale = 'de_DE';
            break;
        case self::$foglang['Language']['en']:
            $this->_lang = self::$locale = 'en_US';
            break;
        case self::$foglang['Language']['es']:
            $this->_lang = self::$locale = 'es_ES';
            break;
        case self::$foglang['Language']['fr']:
            $this->_lang = self::$locale = 'fr_FR';
            break;
        case self::$foglang['Language']['it']:
            $this->_lang = self::$locale = 'it_IT';
            break;
        case self::$foglang['Language']['pt']:
            $this->_lang = self::$locale = 'pt_BR';
            break;
        case self::$foglang['Language']['zh']:
            $this->_lang = self::$locale = 'zh_CN';
            break;
        default:
            $this->_lang = self::$locale = $this->_transLang();
        }
    }
    /**
     * Sets the language we need.
     *
     * @return void
     */
    public function setLang()
    {
        $this->_specLang();
        $domain = 'messages';
        putenv('LANG=' . $this->_lang.'.UTF-8');
        setlocale(
            LC_ALL,
            sprintf(
                '%s.UTF-8',
                $this->_lang
            )
        );
        $path = __DIR__ . '/../../management/languages/';
        bindtextdomain(
            $domain,
            $path
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
            unset($redirect['login']);
            self::redirect('index.php');
        }
        $query = trim(http_build_query($http_query));
        $redir = 'index.php';
        if ($query) {
            $redir .= "?$query";
        }
        self::redirect($redir);
    }
    /**
     * Processes the login.
     *
     * @return void
     */
    public function processMainLogin()
    {
        global $currentUser;
        $uname = filter_input(INPUT_POST, 'uname');
        $upass = filter_input(INPUT_POST, 'upass');
        $this->_username = $uname;
        $this->_password = $upass;
        $type = self::$FOGUser->get('type');
        self::$HookManager
            ->processEvent(
                'USER_TYPE_HOOK',
                array('type' => &$type)
            );
        if (!isset($_POST['login'])) {
            return;
        }
        if (!$this->_username) {
            self::setMessage(self::$foglang['InvalidLogin']);
            self::redirect('index.php?node=logout');
        }
        self::$FOGUser = self::attemptLogin(
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
     * Displays the main login form.
     *
     * @return void
     */
    public function mainLoginForm()
    {
        $this->setLang();
        global $node;
        if (in_array($node, array('login', 'logout'))) {
            if (session_status() != PHP_SESSION_NONE) {
                self::setMessage($_SESSION['FOG_MESSAGES']);
            }
            unset($_GET['login']);
            self::redirect('index.php');
        }
        $this->_getLanguages();
        $logininfo = self::getSetting('FOG_LOGIN_INFO_DISPLAY');
        $extra = '';
        if ($logininfo) {
            $extra = '<div id="login-form-info">'
                . '<p>'
                . self::$foglang['FOGSites']
                . ': <b>'
                . '<i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i>'
                . '</b>'
                . '</p>'
                . '<p>'
                . self::$foglang['LatestVer']
                . ': <b>'
                . '<i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i>'
                . '</b>'
                . '</p>'
                . '<p>'
                . self::$foglang['LatestDevVer']
                . ': <b>'
                . '<i class="icon fa fa-circle-o-notch fa-spin fa-fw"></i>'
                . '</b>'
                . '</p>'
                . '</div>';
        }
        // Login form
        echo '<div class="form-signin">';
        echo '<form class="form-horizontal" method="post" action="';
        echo $this->formAction;
        echo '">';
        echo '<h3 class="form-signin-heading text-center">';
        echo '<span class="col-xs-1">';
        echo '<img src="../favicon.ico" class="logoimg" alt="'
            . self::$foglang['Slogan']
            . '"/>';
        echo '</span>';
        echo _('FOG Project');
        echo '</h3>';
        echo '<hr/>';
        // Username
        echo '<div class="form-group">';
        echo '<label class="control-label col-md-2" for="uname">';
        echo self::$foglang['Username'];
        echo '</label>';
        echo '<div class="col-md-10">';
        echo '<input type="text" class="form-control" name="uname" '
            . 'required="" autofocus="" id="uname"/>';
        echo '</div>';
        echo '</div>';
        // Password
        echo '<div class="form-group">';
        echo '<label class="control-label col-md-2" for="upass">';
        echo self::$foglang['Password'];
        echo '</label>';
        echo '<div class="col-md-10">';
        echo '<input type="password" class="form-control" name="upass" '
            . 'required="" id="upass"/>';
        echo '</div>';
        echo '</div>';
        // Language
        echo '<div class="form-group">';
        echo '<label class="control-label col-md-2" for="ulang">';
        echo self::$foglang['LanguagePhrase'];
        echo '</label>';
        echo '<div class="col-md-10">';
        echo '<select class="form-control" name="ulang" id="ulang">';
        echo $this->_langMenu;
        echo '</select>';
        echo '</div>';
        echo '</div>';
        // Submit button
        echo '<div class="form-group">';
        echo '<div class="col-md-offset-2 col-md-10">';
        echo '<button class="btn btn-default btn-block" '
            . 'type="submit" name="login">';
        echo self::$foglang['Login'];
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '<hr/>';
        // Login information
        echo '<div class="row">';
        echo '<div class="form-group">';
        echo $extra;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Gets the locale.
     *
     * @return string
     */
    public static function getLocale()
    {
        $lang = explode('_', self::$locale);
        $lang = $lang[0];
        return $lang;
    }
}
