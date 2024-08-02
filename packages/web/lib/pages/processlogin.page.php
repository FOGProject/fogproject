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
    public function index(...$args)
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
        $selected = (
            self::getSetting('FOG_DEFAULT_LOCALE')
        );
        global $foglang;
        $langmenu = '<select class="form-control fog-select2" name="ulang" id="ulang">';
        foreach ($foglang['Language'] as $base => &$lang) {
            $langmenu .= '<option value="'
                . $base
                . '"'
                . ($base == $selected ? ' selected' : '')
                . '>'
                . $lang
                . '</option>';
            unset($lang);
        }
        return $langmenu . '</select>';
    }
    /**
     * The processing post form.
     *
     * @return void
     */
    public static function loginPost()
    {
        header('Content-type: application/json');
        try {
            $ulang = filter_input(INPUT_POST, 'ulang');
            $uname = filter_input(INPUT_POST, 'uname');
            $upass = filter_input(INPUT_POST, 'upass');
            $rememberme = isset($_POST['remember-me']);
            $type = self::$FOGUser->get('type');
            if (isset($_SESSION['FOG_LANG']) && $_SESSION['FOG_LANG'] != $ulang) {
                $_SESSION['FOG_LANG'] = $ulang;
                Initiator::language($_SESSION['FOG_LANG']);
            }
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
                throw new Exception(self::$foglang['InvalidLogin']);
            }
            // Setup language stuff
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
            error_log(
                sprintf(
                    "[%s] - %s - %s - %s - %s: %s %s\n",
                    FOGService::getDateTime(),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    _('Login accepted'),
                    _('username'),
                    $uname,
                    _('logged in')
                ),
                3,
                BASEPATH . 'fog_login_accepted.log'
            );
            chmod(BASEPATH . 'fog_login_accepted.log', 0200);
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_FORBIDDEN;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Login Failed')
                ]
            );
            error_log(
                sprintf(
                    "[%s] - %s - %s - %s - %s: %s %s\n",
                    FOGService::getDateTime(),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    _('Login failed'),
                    _('username'),
                    $uname,
                    $e->getMessage()
                ),
                3,
                BASEPATH . 'fog_login_failed.log'
            );
            chmod(BASEPATH . 'fog_login_failed.log', 0200);
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
                $id = filter_input(INPUT_COOKIE, 'foguserauthid');
                if (!$id) {
                    return self::mainLoginForm();
                }
                $selector = filter_input(INPUT_COOKIE, 'foguserauthsel');
                $password = filter_input(INPUT_COOKIE, 'foguserauthpass');
                Route::indiv('userauth', $id);
                $userauth = json_decode(Route::getData());
                $current_date = self::niceDate()->format('Y-m-d H:i:s');
                $expireTime = self::niceDate($userauth->expire)->format('Y-m-d H:i:s');
                $isExpired = (bool)(
                    $userauth->isExpired
                    || $current_date > $expireTime
                );
                $isSelectorVerified = (bool)password_verify(
                    $selector,
                    $userauth->selector
                );
                $isPasswordVerified = (bool)password_verify(
                    $password,
                    $userauth->password
                );
                if (!$isSelectorVerified || !$isPasswordVerified || $isExpired) {
                    self::clearAuthCookie();
                    Route::delete(
                        'userauth',
                        $userauth->id
                    );
                    return self::mainLoginForm();
                }
                self::$FOGUser = new User($userauth->userID);
                if (self::$FOGUser->isLoggedIn() && self::$FOGUser->isValid()) {
                    return;
                }
                self::mainLoginForm();
            }
        }
    }
    /**
     * Presents the login form.
     *
     * @return void
     */
    public static function mainLoginForm()
    {
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
        echo '<span class="fa fa-user form-control-feedback"></span>';
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
        echo '<span class="fa fa-lock form-control-feedback"></span>';
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
        echo '</div>';
        echo '</div>';
    }
}
