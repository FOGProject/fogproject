<?php
/**
 * Processes the current login.
 *
 * PHP version 7.4+
 *
 * @category ProcessLogin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\FOGPage;
use FOG\Items\User;
use FOG\Router\HTTPResponseCodes;
use FOG\Router\Route;

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
                . \Initiator::e($base)
                . '"'
                . ($base == $selected ? ' selected' : '')
                . '>'
                . \Initiator::e($lang)
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
            if ($ulang && isset($_SESSION['FOG_LANG']) && $_SESSION['FOG_LANG'] != $ulang) {
                $_SESSION['FOG_LANG'] = $ulang;
                \Initiator::language($_SESSION['FOG_LANG']);
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
                throw new \Exception(self::$foglang['InvalidLogin']);
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
                    'username' => $uname
                ]
            );
        } catch (\Exception $e) {
            $code = HTTPResponseCodes::HTTP_FORBIDDEN;
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Login Failed')
                ]
            );
        }
        self::jsonSend($code, $msg);
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
                // getItem(), not indiv(): a userauth row that vanished mid
                // login used to end the response with a 404 instead of
                // falling through to the normal "not authenticated" path.
                $userauth = Route::getItem('userauth', $id);
                if (!$userauth) {
                    return self::mainLoginForm();
                }
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
        echo '<div class="card">';
        echo '<div class="card-body login-card-body">';
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
        // Username -- trailing user icon (AdminLTE 4 input-group pattern).
        echo '<div class="input-group mb-3">';
        echo self::makeInput(
            'form-control',
            'uname',
            self::$foglang['Username'],
            'text',
            'uname',
            '',
            true
        );
        echo '<div class="input-group-text"><span class="fas fa-user"></span></div>';
        echo '</div>';
        // Password -- the trailing icon is a button that shows/hides the value
        // so the user can confirm what they typed (see fog.common.js).
        echo '<div class="input-group mb-3">';
        echo self::makeInput(
            'form-control',
            'upass',
            self::$foglang['Password'],
            'password',
            'upass',
            '',
            true
        );
        echo '<button type="button" class="input-group-text fog-password-toggle"'
            . ' aria-label="' . \Initiator::e(_('Show password')) . '"'
            . ' aria-pressed="false" tabindex="-1">'
            . '<span class="far fa-eye"></span></button>';
        echo '</div>';
        echo '<div class="mb-3">';
        echo self::_getLanguages();
        echo '</div>';
        echo '<div class="row align-items-center">';
        echo '<div class="col-7">';
        echo '<div class="form-check">';
        echo self::makeInput(
            'form-check-input',
            'remember-me',
            '',
            'checkbox',
            'remember-me',
            ''
        );
        echo '<label class="form-check-label" for="remember-me">';
        echo _('Remember Me');
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="col-5">';
        echo self::makeButton(
            'loginSubmit',
            _('Sign In'),
            'btn btn-primary w-100'
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
        echo self::loginProviders();
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * "Sign in with ..." buttons contributed by authentication plugins.
     *
     * Closes ADR 0009's third gap. This page fired exactly two hooks --
     * USER_TYPE_HOOK before the credential check and LoginSuccess after it --
     * and neither contributes markup, so a provider had nowhere to put a
     * button. Password login is the only thing a visitor could ever be
     * offered.
     *
     * A provider supplies a label, a start URL and optionally an icon class:
     *
     *   $providers[] = [
     *       'label' => _('Sign in with Acme'),
     *       'url'   => '/fog/ext/oidc/start',
     *       'icon'  => 'fas fa-key'
     *   ];
     *
     * The URL is the only part with teeth, and it is checked rather than
     * trusted: a same-origin absolute path, or an https:// URL, and nothing
     * else. Without that, a plugin -- or anything that can register a hook --
     * could put a javascript: or data: URI on the one page every
     * unauthenticated visitor sees. http:// is refused too: an authentication
     * handshake that starts in clear text is not one worth starting.
     *
     * Everything rendered goes through Initiator::e(), including the icon
     * class, which is attacker-supplied markup as much as the label is.
     *
     * @return string
     */
    public static function loginProviders()
    {
        $providers = [];
        if (self::$HookManager) {
            self::$HookManager->processEvent(
                'LOGIN_PAGE_PROVIDERS',
                ['providers' => &$providers]
            );
        }
        $buttons = [];
        foreach ((array)$providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $label = isset($provider['label']) ? trim((string)$provider['label']) : '';
            $url = isset($provider['url']) ? trim((string)$provider['url']) : '';
            $icon = isset($provider['icon']) ? trim((string)$provider['icon']) : '';
            if ('' === $label || '' === $url) {
                continue;
            }
            // A path must be site-absolute and must not be protocol-relative
            // ("//evil.example" is a URL, not a path). Anything else has to
            // spell out https.
            $sameOrigin = 0 === strpos($url, '/') && 0 !== strpos($url, '//');
            if (!$sameOrigin && 0 !== stripos($url, 'https://')) {
                error_log(
                    sprintf(
                        'FOG login provider: refusing "%s" -- a start URL must '
                        . 'be a site-absolute path or an https:// URL.',
                        $label
                    )
                );
                continue;
            }
            if (!preg_match('#^[A-Za-z0-9 _-]*$#', $icon)) {
                $icon = '';
            }
            $buttons[] = '<a class="btn btn-outline-secondary w-100 mb-2"'
                . ' href="' . \Initiator::e($url) . '">'
                . ('' === $icon
                    ? ''
                    : '<span class="' . \Initiator::e($icon) . ' me-2"></span>')
                . \Initiator::e($label)
                . '</a>';
        }
        if (0 === count($buttons)) {
            return '';
        }
        return '<div class="text-center text-muted my-3">'
            . \Initiator::e(_('or'))
            . '</div>'
            . implode('', $buttons);
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ProcessLogin', 'ProcessLogin');
