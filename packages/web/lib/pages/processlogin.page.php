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
     * Redirect if no direct page to go to.
     *
     * @return void
     */
    public function index()
    {
        if (self::$FOGUser->isValid()) {
            self::redirect('../management/index.php?node=home');
        }
        self::mainLoginForm();
    }
    /**
     * Gets the languages into a string.
     *
     * @return string
     */
    private static function _getLanguages()
    {
        $translang = self::_translang();
        $langmenu = '<select class="form-control select2" name="ulang" id="ulang">';
        foreach ((array)self::$foglang['Language'] as &$lang) {
            $langmenu .= '<option value="'
                . $lang
                . '"'
                . ($translang == $lang ? ' selected' : '')
                . '>'
                . $lang
                . '</option>';
            unset($lang);
        }
        return $langmenu . '</select>';
    }
    /**
     * Returns the language.
     *
     * @param string $lang Two Letter Code to return language.
     *
     * @return string
     */
    private static function _returnLang($lang)
    {
        return self::$foglang['Language'][$lang];
    }
    /**
     * The translation.
     *
     * @return string
     */
    private static function _translang()
    {
        switch (self::$locale) {
        case 'de_DE':
            $lang = 'de';
            break;
        case 'en_US':
            $lang = 'en';
            break;
        case 'es_ES':
            $lang = 'es';
            break;
        case 'eu_ES':
            $lang = 'eu';
            break;
        case 'fr_FR':
            $lang = 'fr';
            break;
        case 'it_IT':
            $lang = 'it';
            break;
        case 'pt_BR':
            $lang = 'pt';
            break;
        case 'zh_CN':
            $lang = 'zh';
        default:
            $lang = self::getSetting('FOG_DEFAULT_LOCALE');
        }
        return self::_returnLang($lang);
    }
    /**
     * Set the session language.
     *
     * @return void
     */
    private static function _specLang()
    {
        $ulang = filter_input(INPUT_POST, 'ulang');
        if (!isset($ulang)) {
            $ulang = self::_translang();
        }
        switch ($ulang) {
        case self::$foglang['Language']['de']:
            self::$locale = 'de_DE';
            break;
        case self::$foglang['Language']['en']:
            self::$locale = 'en_US';
            break;
        case self::$foglang['Language']['es']:
            self::$locale = 'es_ES';
            break;
        case self::$foglang['Language']['eu']:
            self::$Locale = 'eu_ES';
            break;
        case self::$foglang['Language']['fr']:
            self::$locale = 'fr_FR';
            break;
        case self::$foglang['Language']['it']:
            self::$locale = 'it_IT';
            break;
        case self::$foglang['Language']['pt']:
            self::$locale = 'pt_BR';
            break;
        case self::$foglang['Language']['zh']:
            self::$locale = 'zh_CN';
            break;
        default:
            self::$locale = self::_translang();
        }
    }
    /**
     * Sets the language we need.
     *
     * @return void
     */
    public static function setLang()
    {
        $langs = [
            'de_DE' => true,
            'en_US' => true,
            'es_ES' => true,
            'eu_ES' => true,
            'fr_FR' => true,
            'it_IT' => true,
            'pt_BR' => true,
            'zh_CN' => true,
        ];
        self::_specLang();
        setlocale(
            (int)LC_MESSAGES,
            sprintf(
                '%s.UTF-8',
                self::$locale
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
    public static function loginPost()
    {
        header('Content-type: application/json');
        self::setLang();
        $uname = filter_input(INPUT_POST, 'uname');
        $upass = filter_input(INPUT_POST, 'upass');
        $rememberme = isset($_POST['remember-me']);
        $type = self::$FOGUser->get('type');
        self::$HookManager->processEvent(
            'USER_TYPE_HOOK',
            ['type' => &$type]
        );
        self::$FOGUser = self::attemptLogin(
            $uname,
            $upass,
            $rememberme
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
    public static function processMainLogin()
    {
        if (self::$reqmethod == 'POST') {
            if (isset($_POST['login'])) {
                self::loginPost();
            }
        } else {
            if (self::$FOGUser->isValid()) {
                return;
            } else {
                self::mainLoginForm();
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
    public static function mainLoginForm()
    {
        self::setLang();
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
        echo self::_getLanguages();
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
