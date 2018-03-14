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
        $this->_lang = self::$locale;
    }
    /**
     * Redirect if no direct page to go to.
     *
     * @return void
     */
    public function index()
    {
        if (self::$FOGUser->isValid()) {
            self::redirect('../management/index.php?node=home');
        }
        $this->mainLoginForm();
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
        $langs = [
            'de_DE' => true,
            'en_US' => true,
            'es_ES' => true,
            'fr_FR' => true,
            'it_IT' => true,
            'pt_BR' => true,
            'zh_CN' => true,
        ];
        $this->_specLang();
        setlocale(
            (int)LC_MESSAGES,
            sprintf(
                '%s.UTF-8',
                $this->_lang
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
     * The processing post form.
     *
     * @return void
     */
    public function loginPost()
    {
        header('Content-type: application/json');
        global $currentUser;
        $this->setLang();
        $uname = filter_input(INPUT_POST, 'uname');
        $upass = filter_input(INPUT_POST, 'upass');
        $type = self::$FOGUser->get('type');
        self::$HookManager->processEvent(
            'USER_TYPE_HOOK',
            ['type' => &$type]
        );
        self::$FOGUser = self::attemptLogin(
            $uname,
            $upass
        );
        if (!self::$FOGUser->isValid()) {
            $code = HTTPResponseCodes::HTTP_FORBIDDEN;
            $msg = json_encode(
                [
                    'error' => self::$foglang['InvalidLogin'],
                    'title' => _('Login Failed')
                ]
            );
        } else {
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $msg = json_encode(
                [
                    'msg' => _('Login successful!'),
                    'title' => _('Login Success')
                ]
            );
            self::$HookManager->processEvent(
                'LoginSuccess',
                [
                    'username' => $uname,
                    'password' => $upass
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
    /**
     * Processes the login.
     *
     * @return void
     */
    public function processMainLogin()
    {
        global $currentUser;
        if (self::$reqmethod == 'POST') {
            if (isset($_POST['login'])) {
                $this->loginPost();
            }
        } else {
            if (self::$FOGUser->isValid()) {
                return;
            } else {
                $this->mainLoginForm();
            }
        }
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
    /**
     * Presents the login form.
     *
     * @return void
     */
    public function mainLoginForm()
    {
        $this->setLang();
        $this->_getLanguages();
        echo '<div class="login-box">';
        echo '<div class="login-logo">';
        echo '<a href="./index.php"><b>FOG</b> Project</a>';
        echo '</div>';
        echo '<div class="login-box-body">';
        echo '<p class="login-box-msg">';
        echo _('Sign in to start your session');
        echo '</p>';
        echo self::makeFormTag(
            '',
            'loginForm',
            '../management/index.php?node=home&sub=login',
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="form-group has-feedback">';
        echo self::makeInput(
            'form-control',
            'uname',
            self::$foglang['Username'],
            'text',
            'uname',
            '',
            true
        );
        echo '<span class="glyphicon glyphicon-user form-control-feedback"></span>';
        echo '</div>';
        echo '<div class="form-group has-feedback">';
        echo self::makeInput(
            'form-control',
            'upass',
            self::$foglang['Password'],
            'password',
            'upass',
            '',
            true
        );
        echo '<span class="glyphicon glyphicon-lock form-control-feedback"></span>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<select class="form-control select2" name="ulang" id="ulang">';
        echo $this->_langMenu;
        echo '</select>';
        echo '</div>';
        echo '<div class="row">';
        echo '<div class="col-xs-8">';
        echo '<div class="checkbox icheck">';
        echo '<label for="remember-me">';
        echo self::makeInput(
            'remember-me',
            'remember-me',
            '',
            'checkbox',
            'remember-me',
            ''
        );
        echo ' ';
        echo _('Remember Me');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-xs-4">';
        echo self::makeButton(
            'loginSubmit',
            _('Sign In'),
            'btn btn-primary btn-block btn-flat'
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo self::makeInput(
            '',
            'login',
            '',
            'hidden',
            'login',
            '1',
            true
        );
        echo '</form>';
    }
}
