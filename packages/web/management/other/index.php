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
// chrome (see fog-default-ui.scss). The per-user preference is persisted in the
// fogTheme cookie and stamped on <html> below (server-side, on first paint) so
// there is no light flash before theme.js runs. With no cookie the attribute is
// left off here and a pre-paint head script resolves the OS preference instead.
$themePref = filter_input(INPUT_COOKIE, 'fogTheme');
$bsTheme = ($themePref === 'dark')
    ? 'dark'
    : (($themePref === 'light') ? 'light' : '');

// Start output buffering
ob_start();

// Render the HTML page
?>
<!DOCTYPE html>
<html lang="<?= $ulang; ?>"<?= $bsTheme ? ' data-bs-theme="' . $bsTheme . '"' : ''; ?>>
<head>
    <script nonce="<?= htmlspecialchars(FOG_CSP_NONCE, ENT_QUOTES, 'UTF-8'); ?>">
    // Native dark mode, no flash: when the user has no explicit fogTheme cookie
    // choice <html> carries no server-stamped data-bs-theme, so resolve the OS
    // preference here synchronously (before paint) and set it. The cookie cases
    // are already stamped server-side; theme.js keeps it in sync afterwards.
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
    echo '<link href="' . htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8') . '?ver=' . FOG_BCACHE_VER . '" rel="stylesheet" type="text/css"/>';
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
                    <li class="nav-item">
                        <a href="#" id="themeToggle" class="nav-link" role="button"
                           data-label-dark="<?= _('Switch to light mode'); ?>"
                           data-label-light="<?= _('Switch to dark mode'); ?>"
                           title="<?= _('Toggle dark mode'); ?>"
                           aria-label="<?= _('Toggle dark mode'); ?>">
                            <i class="far fa-moon"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../management/index.php?node=logout"><i class="fas fa-right-from-bracket"></i> <?= _('Logout'); ?></a>
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
    echo '<script src="' . htmlspecialchars($javascript, ENT_QUOTES, 'UTF-8') . '?ver=' . FOG_BCACHE_VER . '" type="text/javascript"></script>';
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
