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
$FOGPageManager = FOGCore::getClass('FOGPageManager');

// Capture login process output
ob_start();
FOGCore::getClass('ProcessLogin')->processMainLogin();
$login = ob_get_clean();

require '../commons/text.php';
$Page = FOGCore::getClass('Page');

// Define allowed nodes
$nodes = [
    'schema',
    'client'
];

// Handle logout or login nodes
if (isset($node) && in_array($node, ['logout', 'login'])) {
    if ($node === 'logout') {
        $currentUser->logout();
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
     */
    if (!defined('FOG_LOCAL_LOGIN')) {
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
