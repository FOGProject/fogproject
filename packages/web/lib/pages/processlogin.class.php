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
        $this->_lang = self::$locale;
    }
    /**
     * Index page.
     *
     * @return void
     */
    public function index()
    {
        if (self::$FOGUser->isValid()) {
            self::redirect('?node=home');
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
        $this->setLang();
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
        echo '<div class="login-box">';
        echo '  <div class="login-logo">';
        echo '      <a href="./index.php"><b>FOG</b> Project</a>';
        echo '  </div>';
        echo '  <div class="login-box-body">';
        echo '      <p class="login-box-msg">Sign in to start your session</p>';
        
        echo '      <form method="post" action="'; 
        echo $this->formAction; 
        echo '">';

        echo '          <div class="form-group has-feedback">';
        echo '              <input type="username" class="form-control" placeholder="';
        echo self::$foglang['Username'];
        echo '" name="uname" id="uname">';
        echo '              <span class="glyphicon glyphicon-user form-control-feedback"></span>';
        echo '          </div>';
        echo '          <div class="form-group has-feedback">';
        echo '              <input type="password" class="form-control" placeholder="';
        echo self::$foglang['Password'];
        echo '" name="upass" id="upass">';
        echo '              <span class="glyphicon glyphicon-lock form-control-feedback"></span>';
        echo '          </div>';
        echo '          <div class="form-group">';
        echo '                      <select class="form-control select2" name="ulang" id="ulang">';
        echo $this->_langMenu;
        echo '                      </select>';
        echo '          </div>';
        echo '          <div class="row">';
        echo '              <div class="col-xs-8">';
        echo '                  <div class="checkbox icheck">';
        echo '                      <label>';
        echo '                          <input type="checkbox"> Remember Me';
        echo '                      </label>';
        echo '                  </div>';
        echo '              </div>';
        echo '              <div class="col-xs-4">';
        echo '                  <button type="submit" class="btn btn-primary btn-block btn-flat" name="login">Sign In</button>';
        echo '              </div>';
        echo '          </div>';
        echo '          </div>';
        echo '      </form>';
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
