<?php
declare(strict_types=1);

/**
 * The main index presenter.
 *
 * PHP version 7.4+
 *
 * @category Index_Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 * @version  1.1
 */

use FOG\Auth\Identity;
use FOG\Base\FOGCore;
use FOG\Base\FOGPageManager;
use FOG\Base\Page;
use FOG\Pages\ProcessLogin;
use FOG\Router\HTTPResponseCodes;

/*
 * The web UI is the one entry point that needs a session created for a
 * visitor who arrives without a cookie -- the login form's CSRF token has to
 * live somewhere before anyone has logged in. Every other entry point either
 * presents an existing session cookie or does not use sessions at all, so
 * Initiator only starts one when this is defined or a cookie is present.
 */
define('FOG_WANTS_SESSION', true);

require '../commons/base.inc.php';

// Initialize required classes
$FOGPageManager = new FOGPageManager();

// Capture login process output
ob_start();
(new ProcessLogin())->processMainLogin();
$login = ob_get_clean();

require '../commons/text.php';
$Page = new Page();

// Define allowed nodes
$nodes = [
    'schema',
    'client'
];

// Handle logout or login nodes
if (isset($node) && in_array($node, ['logout', 'login'])) {
    $logoutRedirect = '';
    if ($node === 'logout') {
        // Close any open impersonation span BEFORE the session goes, so the
        // bracket never dangles on the ordinary way out and the logout row
        // below is written as -- and about -- the real administrator.
        // Identity::end() puts them back into $currentUser, which is what
        // logout() then reads. See ADR 0033.
        Identity::end('session ended by logout');
        // A USER_LOGGING_OUT listener may name somewhere else to go -- an
        // external provider's end_session_endpoint, so signing out of FOG
        // also ends the SSO session that would otherwise sign the same
        // account straight back in (fog-plugins#15). Empty on every install
        // without such a plugin, which is the unchanged path below.
        $logoutRedirect = (string)$currentUser->logout();
    }
    if ('' !== $logoutRedirect) {
        /*
         * Only an absolute http(s) URL, and only from a listener. Anything
         * else falls back to the normal destination rather than being sent
         * as-is: this value reaches a Location header, and "whatever a hook
         * put there" is not a good enough answer for that. PHP's header()
         * has refused embedded CR/LF since 5.1.2, so the residual risk is an
         * open redirect rather than a split response -- which is precisely
         * what the scheme check below is for.
         *
         * 302, not the 308 default: the target carries a single-use
         * id_token_hint and must not be cached as a permanent redirect for
         * ?node=logout.
         */
        if (preg_match('#^https?://#i', $logoutRedirect)) {
            FOGCore::redirect($logoutRedirect, 302);
            exit;
        }
    }
    FOGCore::redirect('../management/index.php');
    exit;
}

// Render login page if user is not valid
if (!isset($node) || (!in_array($node, $nodes) && !$currentUser->isValid())) {
    /*
     * Somewhere else to send an anonymous visitor instead of the login form
     * -- an external identity provider, where the install has decided
     * everyone signs in through one (fog-plugins#17). A listener that sets
     * nothing changes nothing, which is every install without such a plugin.
     *
     * NOT offered when FOG_LOCAL_LOGIN is defined. management/login.php
     * defines it, and that is the whole of how the break-glass page is
     * guaranteed: the hook is not fired and overruled there, it is never
     * reached. A forced-redirect setting can otherwise lock every
     * administrator out of a server permanently -- provider down, expired
     * certificate, broken discovery -- with no way back in through a
     * browser, including for the local account that exists for exactly that.
     *
     * Only for a visitor who is NOT signed in, and only on the form render,
     * so nothing here can bounce a working session or an in-flight callback.
     *
     * And only for a caller that could actually follow a browser sign-in.
     * The installer is the proof that matters: updateDB() POSTs its schema
     * deploy to ?node=schema, a schema that is already current bounces that
     * request here (schemaupdaterpage.page.php), and with a forced redirect
     * configured the next hop was the provider -- so installfog.sh reported
     * "Updating Database...Failed!" on a server whose database was fine.
     * This is the same class of gate as FOG_WANTS_SESSION at the top of this
     * file: an entry point shared by browsers and machines has to tell them
     * apart before doing something only a browser can complete.
     *
     * A document navigation is a GET that asks for text/html. fetch/XHR,
     * curl, the installer and every client library send a wildcard or a
     * specific type. A caller that fails the test gets the login form, which
     * is the safe direction to fail in -- it is what this page did before
     * any of this existed.
     */
    $browserNavigation = 'GET' === ($_SERVER['REQUEST_METHOD'] ?? '')
        && false !== stripos(
            (string)($_SERVER['HTTP_ACCEPT'] ?? ''),
            'text/html'
        );
    if (!defined('FOG_LOCAL_LOGIN') && $browserNavigation) {
        $loginRedirect = '';
        $HookManager->processEvent(
            'LOGIN_PAGE_REDIRECT',
            ['redirect' => &$loginRedirect]
        );
        // Absolute http(s) only. This reaches a Location header, and
        // "whatever a hook put there" is not a good enough answer for one.
        // 302, not redirect()'s cacheable 308 default: a browser that
        // remembered the login page as permanently moved to a provider that
        // has since been turned off would have no way back.
        if ('' !== $loginRedirect
            && preg_match('#^https?://#i', $loginRedirect)
        ) {
            FOGCore::redirect($loginRedirect, 302);
            exit;
        }
    }
    /*
     * A caller that cannot follow a browser sign-in must not be handed the
     * sign-in FORM either.
     *
     * Gated on X-Requested-With, NOT on !$browserNavigation. Getting that
     * wrong broke installs: the first shape of this arm answered 401 to
     * everything that was not a document navigation, and checkWebTier() in
     * lib/common/functions.sh probes this very URL with a plain tokenless GET
     * and curl -fL, so it took the 401, saw zero bytes and aborted the install
     * with "Checking web server serves FOG...Failed!". Its comment says in so
     * many words that it expects a page to render regardless -- and it is only
     * one such caller. Monitoring, a hand-rolled curl and the recovery command
     * this installer prints on failure all want the same thing, and none of
     * them can be enumerated from here.
     *
     * Only an XHR can commit the actual error, which is reading a login form
     * as a successful save. Everything else either renders the form (exactly
     * as it did before any of this) or, for a real API client, uses /api/,
     * which has answered 401 properly all along -- see
     * tests/api-unauthenticated-401.test.php. So the narrow gate loses nothing
     * and the wide one silently changed the contract for every machine that
     * has ever fetched a FOG page.
     *
     * The bug itself: execution used to fall through to the login page for
     * signed-out callers of every kind, so an XHR got the form at HTTP 200
     * with Content-type: text/html. jQuery treats
     * 200 as success, so $.apiCall ran its SUCCESS handler with a string body;
     * $.notifyFromAPI found none of error/info/warning/msg in it and -- until
     * the matching change in fog.common.js -- fell back to a GREEN toast
     * reading "Bad Response". The write had been discarded and the UI said it
     * worked. Reported against the plugin Update button, but this branch is
     * reached by every ?node= endpoint, so it was every Save on every page.
     *
     * 401 with a readable reason instead. That is what the XHR error handler
     * is for, and $.notifyFromAPI renders it as the red toast the user should
     * have seen all along.
     *
     * The login POST cannot reach this: processMainLogin() answers it through
     * jsonSend(), which exits.
     *
     * FOG_LOCAL_LOGIN is exempt, for the same reason the redirect above exempts
     * it and not a weaker one. management/login.php defines it and then
     * requires this file; that page exists so a human can always reach a form
     * when the provider is down, and answering it with JSON would take the
     * break-glass page away on exactly the request shape it is there to serve.
     * A browser sends an Accept naming text/html and would have rendered
     * anyway -- this makes it true regardless of what the client asks for,
     * which is what a break-glass path has to be.
     */
    if (!defined('FOG_LOCAL_LOGIN') && FOGCore::$ajax) {
        header('Content-type: application/json');
        echo json_encode(
            [
                'error' => _('Your session has ended. Sign in again.'),
                'title' => _('Session Expired')
            ]
        );
        http_response_code(HTTPResponseCodes::HTTP_UNAUTHORIZED);
        exit;
    }
    $Page
        ->setTitle($foglang['Login'])
        ->setSecTitle($foglang['ManagementLogin'])
        ->startBody();
    
    echo $login;
    
    $Page
        ->endBody()
        ->render();
} else {
    // Handle AJAX requests
    if (FOGCore::$ajax) {
        $FOGPageManager->render();
        exit;
    }

    // Render main page content
    $Page->startBody();
    $FOGPageManager->render();
    $Page
        ->setTitle($FOGPageManager->getFOGPageTitle())
        ->setSecTitle($FOGPageManager->getFOGPageName())
        ->endBody()
        ->render();
}
