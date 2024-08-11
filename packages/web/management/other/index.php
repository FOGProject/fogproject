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

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = self::$FOGUser->isLoggedIn() && self::$FOGUser->isValid();
$ulang = htmlspecialchars($_SESSION['FOG_LANG'] ?? '', ENT_QUOTES, 'UTF-8');

// Start output buffering
ob_start();

// Render the HTML page
?>
<!DOCTYPE html>
<html lang="<?= $ulang; ?>">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="theme-color" content="#367fa9"/>
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
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="dist/js/html5shiv.min.js"></script>
    <script src="dist/js/respond.min.js"></script>
    <![endif]-->
</head>
<body class="<?= $isLoggedIn ? 'skin-blue sidebar-mini' : 'login-page'; ?>">
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
    <div class="wrapper">
        <!-- Header Navigation -->
        <header class="main-header">
            <?php if ($isLoggedIn): ?>
                <a href="./index.php" class="logo">
                    <span class="logo-mini"><b>FOG</b></span>
                    <span class="logo-lg"><b>FOG</b> <?= _('Project'); ?></span>
                </a>
            <?php endif; ?>
            <nav class="navbar navbar-static-top">
                <?php if ($isLoggedIn): ?>
                    <p class="mobile-logo">
                        <a href="../management/index.php"><b>FOG</b> <?= _('Project'); ?></a>
                    </p>
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                        <span class="sr-only"><?= _('Toggle navigation'); ?></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                <?php endif; ?>
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <li>
                            <?php if ($isLoggedIn): ?>
                                <a href="../management/index.php?node=logout"><i class="fa fa-sign-out"></i> <?= _('Logout'); ?></a>
                            <?php else: ?>
                                <?php global $node; ?>
                                <?php if ($node !== 'home'): ?>
                                    <a href="../management/index.php?node=login"><i class="fa fa-sign-in"></i> <?= _('Login'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <?php if ($isLoggedIn): ?>
            <!-- SIDEBAR NAVIGATION -->
            <aside class="main-sidebar">
                <section class="sidebar">
                    <div class="user-panel">
                        <div>
                            <a href="../management/index.php?node=user&sub=edit&id=<?= self::$FOGUser->get('id'); ?>" class="fog-user ajax-page-link">
                                <?= htmlspecialchars(self::$FOGUser->getDisplayName(), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    </div>
                    <?= FOGPage::makeFormTag('sidebar-form', 'universal-search-form', '../../fog/unisearch', 'post', 'application/x-www-form-urlencoded', true); ?>
                    <div>
                        <select id="universal-search-select" class="form-control" name="search" data-placeholder="<?= _('Search') . '...'; ?>"></select>
                    </div>
                    </form>
                    <ul class="sidebar-menu" data-widget="tree">
                        <li class="header"><?= _('MAIN NAVIGATION'); ?></li>
                        <?= $this->menu; ?>
                        <?php if (self::$pluginIsAvailable): ?>
                            <li class="header">
                                <span class="pull-left"><?= _('PLUGIN OPTIONS'); ?></span>
                                <span class="pull-right-container">
                                    <a href="#" class="plugin-options-alternate"><i class="fa fa-minus"></i></a>
                                </span>
                            </li>
                            <div class="sidebar-menu plugin-options">
                                <?= $this->menuHook; ?>
                            </div>
                        <?php endif; ?>
                        <li class="header"><?= _('RESOURCES'); ?></li>
                        <li><a href="https://sourceforge.net/donate/index.php?group_id=201099" target="_blank"><i class="fa fa-money"></i> <span><?= _('Donate'); ?></span></a></li>
                        <li><a href="https://news.fogproject.org" target="_blank"><i class="fa fa-bullhorn"></i> <span><?= _('News'); ?></span></a></li>
                        <li><a href="https://forums.fogproject.org" target="_blank"><i class="fa fa-users"></i> <span><?= _('Forums'); ?></span></a></li>
                        <li><a href="https://docs.fogproject.org" target="_blank"><i class="fa fa-book"></i> <span><?= _('Documentation'); ?></span></a></li>
                    </ul>
                </section>
            </aside>
        <?php endif; ?>
        <!-- Main Content -->
        <?php if ($isLoggedIn): ?>
            <div class="content-wrapper">
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
                <?= FOGPage::makeInput('showpass', 'showpass', '', 'hidden', 'showpass', self::getSetting('FOG_ENABLE_SHOW_PASSWORDS')); ?>
                <div id="ajaxPageWrapper">
                    <section class="content-header">
                        <h1 id="sectionTitle"><?= htmlspecialchars($this->sectionTitle, ENT_QUOTES, 'UTF-8'); ?>
                            <small id="pageTitle"><?= htmlspecialchars($this->pageTitle, ENT_QUOTES, 'UTF-8'); ?></small>
                        </h1>
                    </section>
                    <section class="content">
                        <?= $this->body; ?>
                    </section>
                </div>
            </div>
        <?php else: ?>
            <?= $this->body; ?>
        <?php endif; ?>
        <!-- Footer -->
        <?php if ($isLoggedIn): ?>
            <footer class="main-footer">
                <div class="pull-right hidden-xs">
                    <b><?= _('Channel'); ?></b>&nbsp;<?= FOG_CHANNEL; ?> |
                    <a href="../management/index.php?node=about&sub=home" style="text-decoration: none"><b><?= _('Version'); ?></b>&nbsp;<?= FOG_VERSION; ?></a>
                </div>
                <strong>
                    <?= _('Copyright'); ?> &copy; 2012-<?php echo self::formatTime('now', 'Y'); ?> <a href="https://fogproject.org">FOG Project</a>.
                </strong> <?= _('All rights reserved.'); ?>
            </footer>
        <?php endif; ?>
    </div>
    <div id="scripts">
        <?php
        // Process JS event hooks
        self::$HookManager->processEvent('JS', ['javascripts' => &$this->javascripts]);
foreach ((array)$this->javascripts as $javascript) {
    echo '<script src="' . htmlspecialchars($javascript, ENT_QUOTES, 'UTF-8') . '?ver=' . FOG_BCACHE_VER . '" type="text/javascript"></script>';
}
unset($this->javascripts);
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
