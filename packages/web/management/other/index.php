<?php
declare(strict_types=1);

/**
 * Presents the page uniformly to all users.
 *
 * PHP version 7.4+
 *
 * @category Index
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 * @version  1.1
 */

use FOG\Auth\Authorization;
use FOG\Auth\CSRF;
use FOG\Base\FOGPage;
use FOG\Base\Page;

// Not an entry point: this is the page shell, included by Page::render()
// with the application already booted, which is what makes the self::
// references below resolve. It nonetheless sits under the document root
// because management/other/ also serves ca.cert.der to fog-client, so a
// direct request reaches it and dies on "Cannot access self:: when no
// class scope is active" -- a bodyless 500 and a fatal in the log.
//
// BASEPATH is defined by commons/init.php and this file includes nothing,
// so its absence means nobody booted us. 404 rather than 403: a fragment
// that is not meant to be addressable should not confirm it exists.
if (!defined('BASEPATH')) {
    http_response_code(404);
    exit;
}

// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = self::$FOGUser->isLoggedIn() && self::$FOGUser->isValid();
$ulang = htmlspecialchars($_SESSION['FOG_LANG'] ?? '', ENT_QUOTES, 'UTF-8');

// Dark mode: Bootstrap 5 / AdminLTE 4 native theming keys off data-bs-theme on
// <html>, which is the single source of truth for both BS components and FOG's
// chrome (see fog-default-ui.scss).
//
// The choice is a USER preference, not a device one, so it lives in userPrefs
// and follows the person to any browser. A forced light/dark is stamped on
// <html> below, server-side, so it is already correct on the first paint. An
// unset preference deliberately leaves the attribute OFF -- that absence is
// what tells the pre-paint script in <head> to resolve prefers-color-scheme
// itself, in either direction. Emitting 'light' for an unset preference would
// give a dark-desktop user a light page and no way to tell why.
//
// The login page has no session, so displayTheme() returns '' there and the
// system preference wins. That is the right answer rather than a compromise:
// there is nobody yet whose preference it could be.
$themePref = self::displayTheme();
$bsTheme = $themePref;

// Start output buffering
ob_start();

// Render the HTML page
?>
<!DOCTYPE html>
<html lang="<?= $ulang; ?>"<?= $bsTheme ? ' data-bs-theme="' . $bsTheme . '"' : ''; ?>>
<head>
    <script nonce="<?= htmlspecialchars(FOG_CSP_NONCE, ENT_QUOTES, 'UTF-8'); ?>">
    // Native dark mode, no flash: when the user has no explicit light/dark
    // preference <html> carries no server-stamped data-bs-theme, so resolve
    // the OS preference here synchronously (before paint) and set it. A
    // forced choice is already stamped server-side; theme.js keeps the picker
    // in sync afterwards and never decides the first paint.
    //
    // First in <head>, inline and not deferred, on purpose: anything later --
    // including a stylesheet above it -- runs after the browser has painted
    // once, which IS the flash. Pinned by
    // tests/output-whitespace-significant-blocks.test.php.
    (function () {
        var e = document.documentElement;
        if (!e.hasAttribute('data-bs-theme')) {
            e.setAttribute(
                'data-bs-theme',
                (window.matchMedia &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches)
                    ? 'dark' : 'light'
            );
        }
    }());
    </script>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="theme-color" content="#367fa9"/>
    <meta name="csrf-token" content="<?= htmlspecialchars(CSRF::token(), ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="shortcut icon" href="../favicon.ico"/>
    <title><?= htmlspecialchars($this->pageTitle, ENT_QUOTES, 'UTF-8') . ' | ' . _('FOG Project'); ?></title>
    <?php
    // Process CSS event hooks
    self::$HookManager->processEvent('CSS', ['stylesheets' => &$this->stylesheets]);
foreach ((array)$this->stylesheets as $stylesheet) {
    echo '<link href="' . htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8') . '?ver=' . Page::assetVersion($stylesheet) . '" rel="stylesheet" type="text/css"/>';
}
unset($this->stylesheets);
?>
</head>
<body class="<?= $isLoggedIn ? 'layout-fixed sidebar-expand-lg bg-body-tertiary' : 'login-page'; ?>">
    <!-- FOG Management only works when JavaScript is enabled. -->
    <noscript>
        <div id="noscriptMessage">
            <p><?= _('You must enable JavaScript to use FOG Management.'); ?></p>
        </div>
        <style>
            body > *:not(noscript) { display: none; }
            #noscriptMessage {
                position:fixed;
                top:50%;
                left:50%;
                transform:translate(-50%,-50%);
                font-size:24px;
            }
        </style>
    </noscript>
<?php
// The impersonation mode line (ADR 0033).
//
// EMITTED BY THE SHELL, not by any page. This file is the only HTML shell
// FOG has -- Page::render()'s full-render arm includes it and nothing else
// emits a <body> -- so a bar here is on every page by construction rather
// than by every page remembering. The contentOnly arm emits no <body> at
// all, and AJAX navigation replaces only #ajaxPageWrapper further down, so
// this survives every in-app navigation without being re-sent.
//
// Fixed, full width, and above everything: z-index 2000 clears the
// Bootstrap modal (1055) and offcanvas (1045), so it covers a dialog too.
// A modal is exactly where an administrator is most likely to forget what
// identity they are wearing.
//
// #c2410c is chosen to belong to NO existing status colour -- not
// btn-warning's amber, not btn-danger's red -- so it reads as a mode rather
// than as an alert about something that just happened. Not dismissible, and
// there is no control to hide it: the failure this exists to prevent is an
// administrator who forgets.
//
// Inline rather than in fog-default-ui.scss because the repository ships no
// build script for that stylesheet (its .min.css is a committed artifact
// reproduced by one exact sass version), so a five-line rule there costs a
// regenerated asset and a cache-buster bump to say something no page can
// override anyway.
$impersonating = \FOG\Auth\Identity::isImpersonating();
if ($isLoggedIn && $impersonating):
    ?>
    <div id="impersonation-bar" role="status"
         aria-label="<?= _('You are impersonating another user'); ?>"></div>
    <style nonce="<?= htmlspecialchars(FOG_CSP_NONCE, ENT_QUOTES, 'UTF-8'); ?>">
        #impersonation-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            z-index: 2000;
            background: linear-gradient(90deg, #c2410c 0%, #9a1c1c 100%);
            pointer-events: none;
        }
    </style>
    <?php endif; ?>

<?php
// The impersonation picker, EMPTY (ADR 0033).
//
// Here rather than on a page of its own for the same reason the mode line
// above is here: this is the only HTML shell FOG has, so the dialog is
// reachable from wherever the administrator happens to be. Impersonating is
// one select and one button -- a dialog, not a destination -- and needing to
// navigate somewhere first to answer "what does this user see" is most of the
// friction the feature exists to remove.
//
// The BODY is fetched by fog.impersonate.js when the modal opens. Rendering
// it here would cost a users query plus a refusalReason() per user -- two
// subset tests each -- on every page render, to populate a dialog almost
// nobody opens.
//
// Rendered only for somebody who can actually use it, so the markup is not
// carried on every page for every user.
//
// Identity::canStart(), NOT Authorization::can() -- the same predicate the
// trigger below uses, and it asks the REAL administrator. A bare can() answers
// for the effective identity, which while a span is open is the impersonated
// user, so wearing a roleless account withdrew the administrator's own ability
// to swap masks. This gate and the trigger must move together: a trigger with
// no modal opens nothing, and a modal with no trigger is dead markup.
if ($isLoggedIn && \FOG\Auth\Identity::canStart()) : ?>
    <div class="modal fade" id="impersonate-modal" tabindex="-1"
         aria-labelledby="impersonate-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="impersonate-modal-label"><?= _('Impersonate a user'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= _('Close'); ?>"></button>
                </div>
                <div class="modal-body" id="impersonate-modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary float-start" data-bs-dismiss="modal"><?= _('Cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
    <?php if ($isLoggedIn): ?>
    <div class="app-wrapper">
        <!-- Header Navigation -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <span class="visually-hidden"><?= _('Toggle navigation'); ?></span>
                            <i class="fas fa-bars"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <a href="../management/index.php" class="nav-link"><b>FOG</b> <?= _('Project'); ?></a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php
                    // The stored theme preference, carried for theme.js.
                    //
                    // Hidden, and an element of its own rather than an
                    // attribute on a control, because the three choices now
                    // live in the preferences dialog and no navbar control
                    // displays the theme any more. What theme.js still needs
                    // is the STORED value, which is not the value on <html>:
                    // '' means the browser resolved it, and the tick in the
                    // dialog has to tell "system, which happens to be dark"
                    // from "dark".
                    //
                    // Its ABSENCE is also how theme.js recognizes the login
                    // page -- no session, so no preference to read or write.
                    // Anything that moves this must keep that true.
        ?>
                    <span id="themePref" class="d-none"
                          data-theme-pref="<?= Initiator::e($themePref); ?>"></span>
                    <?php
        // THE ACCOUNT MENU. One dropdown, everything about who
        // you are and how you stop being them.
        //
        // These were four bare navbar links -- theme, clock,
        // impersonation, logout -- and the impersonation ones
        // pushed Logout along the bar every time a span opened.
        // A control that moves when the mode changes is a control
        // people misclick, and Logout is the worst one to misclick
        // while wearing somebody else's account.
        //
        // So the identity controls live behind one stable target,
        // and the menu states the identity ITSELF rather than
        // leaving it to be inferred from the sidebar. That is not
        // decoration: #impersonation-bar is four pixels of color
        // and carries its text only in aria-label, so before this
        // there was nothing on screen that NAMED the account being
        // worn or the administrator wearing it.
        //
        // THE CLOCK IS THE POINT, not a garnish. The motivating
        // ticket for impersonation is "this user says their times
        // are wrong", so the menu prints the current time as that
        // user sees it, zone abbreviation included. Answering the
        // question the feature exists for should not require
        // navigating anywhere.
        //
        // Rendered server-side, so it is the time at page render
        // rather than a live clock. Deliberate: a ticking clock
        // needs a timer, and a self-rescheduling timer is the bug
        // class ADR 0012 is about. What is being checked is the
        // ZONE, which does not tick.
        $acctName = (string)self::$FOGUser->get('name');
        $acctReal = $impersonating
            ? \FOG\Auth\Identity::realUserName()
            : '';
        ?>
                    <li class="nav-item dropdown">
                        <?php
                        // .dropdown-toggle earns its place: it is what draws
                        // the caret, so the icon says "there is a menu here"
                        // rather than looking like a link to a profile page.
                        // Bootstrap's own class, and the same one
                        // FOGPage::renderTabs() already uses for a
                        // nav-link dropdown, so this is the house pattern
                        // rather than a new one.
                        //
                        // Bootstrap does NOT rotate that caret when the menu
                        // opens -- fog-default-ui.scss does, keyed on the
                        // .show class Bootstrap already puts on the toggle.
                        // aria-expanded is maintained by Bootstrap and is
                        // what actually announces the state; the rotation is
                        // the sighted half of the same signal.
        ?>
                        <a href="#" id="accountMenu" class="nav-link dropdown-toggle" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false"
                           title="<?= _('Account'); ?>"
                           aria-label="<?= _('Account'); ?>">
                            <i class="fas fa-circle-user"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
                            <li>
                                <h6 class="dropdown-header mb-0"><?= Initiator::e($acctName); ?></h6>
                                <?php if ('' !== $acctReal): ?>
                                <span class="dropdown-item-text pt-0 small text-body-secondary"><?= sprintf(_('impersonated by %s'), Initiator::e($acctReal)); ?></span>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <span class="dropdown-item-text small text-body-secondary">
                                    <i class="far fa-clock fa-fw me-1"></i><?= Initiator::e(FOGPage::formatTime('now', 'M j, Y g:i A T')); ?>
                                </span>
                            </li>
                            <?php
                // Ordered as the two questions are actually asked:
                // become somebody (or somebody else), then stop.
                //
                // TWO INDEPENDENT GATES, not one if/else. They
                // used to be an if/elseif on $impersonating, which
                // tied "can I start one" to "am I in one" -- and
                // the elseif arm's bare Authorization::can() then
                // asked the MASK, so it was only ever correct
                // because that arm could not be reached during a
                // span. Wearing a roleless account therefore
                // refused the administrator their own swap, with
                // "you do not have permission to impersonate
                // users" -- a sentence that was true of the mask
                // and false of the person reading it.
                //
                // canStart() asks the real administrator, so it
                // answers the same whether or not a mask is in
                // place. Ending is gated on there being a span and
                // on NOTHING else, ever: revoke the grant mid-span
                // and the swap disappears while the exit stays.
                // The way out is never the control that goes
                // missing.
                //
                // "Impersonate another" is end-then-start on
                // submit, never a swap -- impersonation is always
                // exactly one level deep, so the audit never has
                // to answer "acting as B, who was being acted as
                // by A".
                if (\FOG\Auth\Identity::canStart()): ?>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#impersonate-modal"><i class="fas fa-user-group fa-fw me-2"></i><?= $impersonating ? _('Impersonate another user') : _('Impersonate user'); ?></a>
                            </li>
                            <?php endif; ?>
                            <?php
                // A plain GET link, and it stays one. The exit has
                // to work for a mask holding no roles, from any
                // page, with no JavaScript -- a link is the only
                // thing a script error cannot break. Not an AJAX
                // page link either: ending a span changes who the
                // whole shell belongs to, so it needs a full
                // render rather than a content swap.
                if ($impersonating): ?>
                            <li>
                                <a class="dropdown-item" href="../management/index.php?node=impersonate&amp;sub=end"><i class="fas fa-user-slash fa-fw me-2"></i><?= _('End impersonation'); ?></a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <?php
            // Theme and display timezone, which are the same
            // KIND of thing -- per-user preferences, stored in
            // userPrefs, changing what this one person sees and
            // nothing about what is stored or what anyone else
            // sees. They were two separate navbar icons, which
            // put a three-state picker and a modal trigger in
            // the chrome and said nothing about them belonging
            // together.
            //
            // A dialog rather than more rows in this menu:
            // theme is a three-way choice with a tick and
            // timezone is a several-hundred-option select, and
            // both are form controls wearing a menu's clothes.
            // It is also where the next per-user preference
            // goes without this menu growing again.
            //
            // Reached from HERE because that is what makes the
            // impersonation workflow one path: become the user,
            // open this menu, see their clock is wrong, fix it,
            // drop back. Preferences follow the impersonated
            // identity like every other read, which is the
            // whole point of the feature.
        ?>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#prefsModal"><i class="fas fa-sliders fa-fw me-2"></i><?= _('Preferences'); ?></a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../management/index.php?node=logout"><i class="fas fa-right-from-bracket fa-fw me-2"></i><?= _('Log out'); ?></a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
            <!-- SIDEBAR NAVIGATION -->
            <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
                <div class="sidebar-brand">
                    <a href="../management/index.php" class="brand-link">
                        <span class="brand-text fw-light"><b>FOG</b> <?= _('Project'); ?></span>
                    </a>
                </div>
                <div class="sidebar-wrapper">
                    <div class="user-panel p-2">
                        <?php if (Authorization::can(Authorization::resolvePagePermission('user', 'edit', false))): ?>
                        <a href="../management/index.php?node=user&sub=edit&id=<?= self::$FOGUser->get('id'); ?>" class="fog-user ajax-page-link d-block">
                            <?= htmlspecialchars(self::$FOGUser->getDisplayName(), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php else: ?>
                        <span class="fog-user d-block">
                            <?= htmlspecialchars(self::$FOGUser->getDisplayName(), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="p-2">
                        <?= FOGPage::makeFormTag('sidebar-form', 'universal-search-form', '../../fog/unisearch', 'post', 'application/x-www-form-urlencoded', true); ?>
                            <select id="universal-search-select" class="form-control" name="search" data-placeholder="<?= _('Search') . '...'; ?>"></select>
                        </form>
                    </div>
                    <nav class="mt-2">
                        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="<?= _('Main navigation'); ?>" data-accordion="true">
                            <li class="nav-header"><?= _('MAIN NAVIGATION'); ?></li>
                            <?= $this->menu; ?>
                            <?php if (self::$pluginIsAvailable): ?>
                                <li class="nav-header">
                                    <?= _('PLUGIN OPTIONS'); ?>
                                    <a href="#" class="plugin-options-alternate float-end"><i class="fas fa-minus"></i></a>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <?php if (self::$pluginIsAvailable): ?>
                            <ul class="nav sidebar-menu flex-column plugin-options" data-lte-toggle="treeview" data-accordion="true">
                                <?= $this->menuHook; ?>
                            </ul>
                        <?php endif; ?>
                        <ul class="nav sidebar-menu flex-column">
                            <li class="nav-header"><?= _('RESOURCES'); ?></li>
                            <li class="nav-item"><a class="nav-link" href="https://sourceforge.net/donate/index.php?group_id=201099" target="_blank"><i class="nav-icon fas fa-money-bill-1"></i><p><?= _('Donate'); ?></p></a></li>
                            <li class="nav-item"><a class="nav-link" href="https://news.fogproject.org" target="_blank"><i class="nav-icon fas fa-bullhorn"></i><p><?= _('News'); ?></p></a></li>
                            <li class="nav-item"><a class="nav-link" href="https://forums.fogproject.org" target="_blank"><i class="nav-icon fas fa-users"></i><p><?= _('Forums'); ?></p></a></li>
                            <li class="nav-item"><a class="nav-link" href="https://docs.fogproject.org" target="_blank"><i class="nav-icon fas fa-book"></i><p><?= _('Documentation'); ?></p></a></li>
                        </ul>
                    </nav>
                </div>
            </aside>
            <!-- Main Content -->
            <main class="app-main">
                <?= FOGPage::makeInput('reAuthDelete', 'reAuthDelete', '', 'hidden', 'reAuthDelete', self::getSetting('FOG_REAUTH_ON_DELETE')); ?>
                <?php
            $pageLength = self::getSetting('FOG_VIEW_DEFAULT_SCREEN');
if (in_array(strtolower($pageLength), ['search', 'list'])) {
    $pageLength = 10;
    $Setting = self::getClass('Setting')
        ->set('name', 'FOG_VIEW_DEFAULT_SCREEN')
        ->load('name')
        ->set(
            'description',
            _(
                'This setting defines the number of items to display '
                . 'when listing/searching elements. The default value is 10.'
            )
        )->set('value', $pageLength)
        ->save();
    unset($Setting);
}
?>
                <?= FOGPage::makeInput('pageLength', 'pageLength', '', 'hidden', 'pageLength', self::getSetting('FOG_VIEW_DEFAULT_SCREEN')); ?>
                <?= FOGPage::makeInput('scrollMode', 'scrollMode', '', 'hidden', 'scrollMode', self::getSetting('FOG_TABLE_SCROLL_MODE')); ?>
                <?= FOGPage::makeInput('showpass', 'showpass', '', 'hidden', 'showpass', self::getSetting('FOG_ENABLE_SHOW_PASSWORDS')); ?>
                <?php
                // Where the REST API lives, for the JS that stores a user's
                // preferences. Normalized exactly as Route::defineRoutes()
                // normalizes it, from the same setting, so the path the
                // browser calls and the path the router answers on cannot
                // drift -- FOG_WEB_ROOT is installer-settable and is not
                // always '/fog/'.
                $apiBase = trim((string)self::getSetting('FOG_WEB_ROOT'), '/');
$apiBase = '/' . ($apiBase === '' ? '' : $apiBase . '/');
?>
                <?= FOGPage::makeInput('apiBase', 'apiBase', '', 'hidden', 'apiBase', $apiBase); ?>
                <?php
                // Display-timezone picker. Lives in the shell rather than on a
                // page of its own because every signed-in user must be able to
                // reach it, including one holding no role at all -- there is no
                // self-service node today and adding one would mean widening
                // the exempt-node list, which is an access-control change this
                // does not need: the preference route is already reachable by
                // any authenticated user and can only ever address that user's
                // own row.
                //
                // The zone list comes from the platform's own tzdata, so it
                // cannot drift from what DateTimeZone will accept back.
                $tzDefault = (string)self::getSetting('FOG_TZ_INFO');
if ('' === trim($tzDefault)) {
    $tzDefault = ini_get('date.timezone') ?: 'UTC';
}
$tzList = \DateTimeZone::listIdentifiers();
?>
                <?php
                // PREFERENCES: everything about how THIS person sees FOG.
                //
                // Was #tzModal, holding the timezone alone while the theme sat
                // in a navbar dropdown of its own. They are the same kind of
                // thing -- per-user, stored in userPrefs, changing only what
                // the one viewer sees -- and splitting them across two bits of
                // chrome said otherwise.
                //
                // STATIC, not fetched. The impersonation picker is fetched on
                // open because building it costs a users query and two subset
                // tests per user; this costs a listIdentifiers() call and no
                // database at all. Shipping it also means theme.js and
                // fog.common.js still find their controls at DOMContentLoaded,
                // where they bind directly -- fetching the body would break
                // both silently, with the dialog rendering perfectly and
                // nothing in it working.
                //
                // THE TWO HALVES SAVE DIFFERENTLY, deliberately. Theme applies
                // on click and writes in the background: it is a client-side
                // attribute flip, so waiting on the network would add lag to
                // something that cannot fail visibly. Timezone needs Save and
                // then reloads, because every date already on the page was
                // rendered server-side in the old zone and cannot be relabeled
                // in place.
?>
                <div class="modal fade" id="prefsModal" tabindex="-1" aria-labelledby="prefsModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="prefsModalLabel"><?= _('Preferences'); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= _('Close'); ?>"></button>
                            </div>
                            <div class="modal-body">
                                <?php
                // A three-state picker, not a toggle: "follow
                // the system" is a state a user can choose and
                // return to, and a two-way toggle has nowhere
                // to put it.
                //
                // The tick is `invisible` rather than `d-none`
                // so the three rows keep the same width and
                // nothing shifts as it moves.
?>
                                <h6 class="mb-1"><?= _('Theme'); ?></h6>
                                <p class="text-body-secondary small">
                                    <?= _('How FOG looks to you. "System" follows whatever your device is set to.'); ?>
                                </p>
                                <div class="list-group mb-4">
                                    <button class="list-group-item list-group-item-action d-flex align-items-center" type="button" data-theme-choice="">
                                        <i class="fas fa-circle-half-stroke fa-fw me-2"></i><?= _('System'); ?>
                                        <i class="fas fa-check fa-fw ms-auto theme-choice-tick invisible"></i>
                                    </button>
                                    <button class="list-group-item list-group-item-action d-flex align-items-center" type="button" data-theme-choice="light">
                                        <i class="far fa-sun fa-fw me-2"></i><?= _('Light'); ?>
                                        <i class="fas fa-check fa-fw ms-auto theme-choice-tick invisible"></i>
                                    </button>
                                    <button class="list-group-item list-group-item-action d-flex align-items-center" type="button" data-theme-choice="dark">
                                        <i class="far fa-moon fa-fw me-2"></i><?= _('Dark'); ?>
                                        <i class="fas fa-check fa-fw ms-auto theme-choice-tick invisible"></i>
                                    </button>
                                </div>
                                <h6 class="mb-1"><?= _('Display timezone'); ?></h6>
                                <p class="text-body-secondary small">
                                    <?= _('Choose the timezone dates and times are shown to you in. This changes only what you see; nothing about what is stored, and nothing for anyone else.'); ?>
                                </p>
                                <select class="form-select" id="tzSelect" aria-label="<?= _('Display timezone'); ?>">
                                    <option value=""><?= sprintf(_('Server default (%s)'), Initiator::e($tzDefault)); ?></option>
                                    <?php foreach ($tzList as $tzName): ?>
                                    <option value="<?= Initiator::e($tzName); ?>"><?= Initiator::e($tzName); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary float-start" data-bs-dismiss="modal"><?= _('Cancel'); ?></button>
                                <button type="button" class="btn btn-primary" id="tzSave"><?= _('Save'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
        // No-role warning: with deny-by-default a user holding zero roles
        // can reach nothing but the exempt nodes (dashboard, logout), and
        // every menu entry disappears -- which looks like a broken install
        // rather than a permission state. Say so explicitly. The old
        // "are roles in use anywhere?" guard is gone: it existed only to
        // avoid nagging installs where no-role meant implicit admin, and
        // no-role is now equally broken whether or not anyone else has a
        // role.
        $showNoRoleBanner = self::$FOGUser
            && self::$FOGUser->isValid()
            && !count(Authorization::getPermissions());
if ($showNoRoleBanner):
    ?>
                <div class="container-fluid pt-3" id="no-role-banner">
                    <div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
                        <strong><?= _('No role assigned'); ?>:</strong>
                        <?= _('this account has no role, so it has no access to any management page. Ask an administrator to assign it a role.'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= _('Close'); ?>"></button>
                    </div>
                </div>
                <script nonce="<?= htmlspecialchars(FOG_CSP_NONCE, ENT_QUOTES, 'UTF-8'); ?>">
                (function() {
                    var banner = document.getElementById('no-role-banner');
                    if (window.sessionStorage.getItem('fogNoRoleBannerDismissed')) {
                        banner.parentNode.removeChild(banner);
                        return;
                    }
                    banner.querySelector('.btn-close').addEventListener('click', function() {
                        window.sessionStorage.setItem('fogNoRoleBannerDismissed', '1');
                    });
                })();
                </script>
                <?php endif; ?>
                <div id="ajaxPageWrapper">
                    <div class="app-content-header">
                        <div class="container-fluid">
                            <h1 id="sectionTitle"><?= htmlspecialchars($this->sectionTitle, ENT_QUOTES, 'UTF-8'); ?>
                                <small id="pageTitle"><?= htmlspecialchars($this->pageTitle, ENT_QUOTES, 'UTF-8'); ?></small>
                            </h1>
                        </div>
                    </div>
                    <div class="app-content">
                        <div class="container-fluid">
                            <?= $this->body; ?>
                        </div>
                    </div>
                </div>
            </main>
            <!-- Footer -->
            <footer class="app-footer">
                <div class="float-end d-none d-sm-inline">
                    <b><?= _('Channel'); ?></b>&nbsp;<?= FOG_CHANNEL; ?> |
                    <a href="../management/index.php?node=about&sub=home" style="text-decoration: none"><b><?= _('Version'); ?></b>&nbsp;<?= FOG_VERSION; ?></a>
                </div>
                <strong>
                    <?= _('Copyright'); ?> &copy; 2012-<?php echo self::formatTime('now', 'Y'); ?> <a href="https://fogproject.org">FOG Project</a>.
                </strong> <?= _('All rights reserved.'); ?>
            </footer>
    </div>
    <?php else: ?>
        <?= $this->body; ?>
    <?php endif; ?>
    <div id="scripts">
        <?php
        // Process JS event hooks
        self::$HookManager->processEvent('JS', ['javascripts' => &$this->javascripts]);
foreach ((array)$this->javascripts as $javascript) {
    echo '<script src="' . htmlspecialchars($javascript, ENT_QUOTES, 'UTF-8') . '?ver=' . Page::assetVersion($javascript) . '" type="text/javascript"></script>';
}
unset($this->javascripts);
// Drain any queued flash messages and toast them once the JS bundle
// (jQuery/Bootstrap) above has loaded. Nonce'd so the CSP allows it.
$flashmessages = self::getMessage();
if ($flashmessages) {
    echo '<script nonce="' . htmlspecialchars(FOG_CSP_NONCE, ENT_QUOTES, 'UTF-8') . '">';
    echo '$(function(){';
    foreach ($flashmessages as $flashmessage) {
        echo '$.notify('
            . json_encode($flashmessage['title'] ?? '')
            . ','
            . json_encode($flashmessage['body'] ?? '')
            . ','
            . json_encode($flashmessage['type'] ?? 'info')
            . ');';
    }
    echo '});';
    echo '</script>';
}
?>
    </div>
    <!-- Memory Usage: <?= self::formatByteSize(memory_get_usage(true)); ?> -->
    <!-- Memory Peak: <?= self::formatByteSize(memory_get_peak_usage()); ?> -->
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
