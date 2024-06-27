<?php
/**
 * Presents the page the same to all.
 *
 * PHP version 5
 *
 * @category Index
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the page the same to all.
 *
 * @category Index
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$isLoggedIn = self::$FOGUser->isLoggedIn() && self::$FOGUser->isvalid();
$ulang = $_SESSION['FOG_LANG'] ?: '';
echo '<!DOCTYPE html>';
echo '<html lang="' . $ulang . '">';
echo '<head>';
echo '<meta charset="utf-8"/>';
echo '<meta http-equiv="X-UA-Compatible" content="IE=edge"/>';
echo '<meta name="viewport" content="width=device-width, '
    . 'initial-scale=1, maximum-scale=1, user-scalable=no" />';
echo '<meta name="theme-color" content="#367fa9"/>';
echo '<link rel="shortcut icon" href="../favicon.ico"/>';
echo '<title>';
echo $this->pageTitle . ' | ' . _('FOG Project');
echo '</title>';
self::$HookManager
    ->processEvent(
        'CSS',
        ['stylesheets' => &$this->stylesheets]
    );
foreach ((array)$this->stylesheets as $stylesheet) {
    echo '<link href="'
        . $stylesheet
        . '?ver='
        . FOG_BCACHE_VER
        . '" rel="stylesheet" type="text/css"/>';
}
unset($this->stylesheets);
echo '<!-- HTML5 Shim and Respond.js IE8 support of HTML5 '
    . 'elements and media queries -->';
echo '<!--[if lt IE 9]>';
echo '<script src="dist/js/html5shiv.min.js"></script>';
echo '<script src="dist/js/respond.min.js"></script>';
echo '<![endif]-->';
echo '</head>';
echo '<body class="';
echo 'hold-transition ';
echo ($isLoggedIn ? 'skin-blue sidebar-mini' : 'login-page');
echo '">';
echo '<!-- FOG Management only works when JavaScript is enabled. -->';
echo '<noscript>';
echo '<div id="noscriptMessage">';
echo '<p>';
echo _('You must enable JavaScript to use FOG Management.');
echo '</p>;';
echo '</div>';
echo '<style>';
echo 'body > *:not(noscript) {';
echo 'display: none;';
echo '}';
echo '#noscriptMessage {';
echo 'position:fixed;';
echo 'top:50%;';
echo 'left:50%;';
echo 'transform:translate(-50%,-50%);';
echo 'font-size:24px;}';
echo '</style>';
echo '</noscript>';
echo '<div class="wrapper">';
echo '<!-- Header Navigation -->';
echo '<header class="main-header">';
if ($isLoggedIn) {
    echo '<a href="./index.php" class="logo">';
    echo '<span class="logo-mini"><b>FOG</b></span>';
    echo '<span class="logo-lg"><b>FOG</b> ' . _('Project') . '</span>';
    echo '</a>';
}
echo '<nav class="navbar navbar-static-top">';
if ($isLoggedIn) {
    echo '<p class="mobile-logo">';
    echo '<a href="../management/index.php">';
    echo '<b>FOG</b> ' . _('Project');
    echo '</a>';
    echo '</p>';
    echo '<a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">';
    echo '<span class="sr-only">' . _('Toggle navigation') . '</span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '</a>';
}
echo '<div class="navbar-custom-menu">';
echo '<ul class="nav navbar-nav">';
echo '<li>';
if ($isLoggedIn) {
    echo '<a href="../management/index.php?node=logout">';
    echo '<i class="fa fa-sign-out"></i>' . _('Logout');
    echo '</a>';
} else {
    global $node;
    if ($node != 'home') {
        echo '<a href="../management/index.php?node=login">';
        echo '<i class="fa fa-sign-in"></i>' . _('Login');
        echo '</a>';
    }
}
echo '</li>';
echo '</ul>';
echo '</div>';
echo '</nav>';
echo '</header>';
if ($isLoggedIn) {
    echo '<!-- SIDEBAR NAVIGATION -->';
    echo '<aside class="main-sidebar">';
    echo '<section class="sidebar">';
    echo '<div class="user-panel">';
    echo '<div>';
    echo '<a href="../management/index.php?node=user&sub=edit&id=';
    echo self::$FOGUser->get('id');
    echo '" class="fog-user ajax-page-link">';
    echo self::$FOGUser->getDisplayName();
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo FOGPage::makeFormTag(
        'sidebar-form',
        'universal-search-form',
        '../../fog/unisearch',
        'post',
        'application/x-www-form-urlencoded',
        true
    );
    echo '<div>';
    echo '<select id="universal-search-select" class="form-control" ';
    echo 'name="search" data-placeholder="';
    echo _('Search') . '..."></select>';
    echo '</div>';
    echo '</form>';
    echo '<ul class="sidebar-menu" data-widget="tree">';
    echo '<li class="header">';
    echo _('MAIN NAVIGATION');
    echo '</li>';
    echo $this->menu;
    if (self::$pluginIsAvailable) {
        echo '<li class="header">';
        echo '<span class="pull-left">';
        echo _('PLUGIN OPTIONS');
        echo '</span>';
        echo '<span class="pull-right-container">';
        echo '<a href="#" class="plugin-options-alternate">';
        echo '<i class="fa fa-minus"></i>';
        echo '</a>';
        echo '</span>';
        echo '</li>';
        echo '<div class="sidebar-menu plugin-options">';
        echo $this->menuHook;
        echo '</div>';
    }
    echo '<li class="header">';
    echo _('RESOURCES');
    echo '</li>';
    echo '<li>';
    echo '<a href="https://sourceforge.net/donate/index.php?group_id=201099" target="_blank">';
    echo '<i class="fa fa-money"></i> ';
    echo '<span>';
    echo _('Donate');
    echo '</span>';
    echo '</a>';
    echo '</li>';
    echo '<li>';
    echo '<a href="https://news.fogproject.org" target="_blank">';
    echo '<i class="fa fa-bullhorn"></i> ';
    echo '<span>';
    echo _('News');
    echo '</span>';
    echo '</a>';
    echo '</li>';
    echo '<li>';
    echo '<a href="https://forums.fogproject.org" target="_blank">';
    echo '<i class="fa fa-users"></i> ';
    echo '<span>';
    echo _('Forums');
    echo '</span>';
    echo '</a>';
    echo '</li>';
    echo '<li>';
    echo '<a href="https://docs.fogproject.org" target="_blank">';
    echo '<i class="fa fa-book"></i> ';
    echo '<span>';
    echo _('Documentation');
    echo '</span>';
    echo '</a>';
    echo '</li>';
    echo '</ul>';
    echo '</section>';
    echo '</aside>';
}
echo '<!-- Main Content -->';
if ($isLoggedIn) {
    echo '<div class="content-wrapper">';
    echo FOGPage::makeInput(
        'reAuthDelete',
        'reAuthDelete',
        '',
        'hidden',
        'reAuthDelete',
        self::getSetting('FOG_REAUTH_ON_DELETE')
    );
    $pageLength = self::getSetting('FOG_VIEW_DEFAULT_SCREEN');
    if (in_array(strtolower($pageLength), ['search','list'])) {
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
    echo FOGPage::makeInput(
        'pageLength',
        'pageLength',
        '',
        'hidden',
        'pageLength',
        self::getSetting('FOG_VIEW_DEFAULT_SCREEN')
    );
    echo FOGPage::makeInput(
        'showpass',
        'showpass',
        '',
        'hidden',
        'showpass',
        self::getSetting('FOG_ENABLE_SHOW_PASSWORDS')
    );
    echo '<div id="ajaxPageWrapper">';
    echo '<section class="content-header">';
    echo '<h1 id="sectionTitle">';
    echo $this->sectionTitle;
    echo '<small id="pageTitle">';
    echo $this->pageTitle;
    echo '</small>';
    echo '</h1>';
    echo '</section>';
    echo '<section class="content">';
    echo $this->body;
    echo '</section>';
    echo '</div>';
    echo '</div>';
} else {
    echo $this->body;
}
echo '<!-- Footer -->';
if ($isLoggedIn) {
    echo '<footer class="main-footer">';
    echo '<div class="pull-right hidden-xs">';
    echo '<b>';
    echo _('Channel');
    echo '</b>';
    echo '&nbsp;';
    echo FOG_CHANNEL;
    echo ' | ';
    echo '<a href="../management/index.php?node=about&sub=home" ';
    echo 'style="text-decoration: none">';
    echo '<b>';
    echo _('Version');
    echo '</b>';
    echo '&nbsp;';
    echo FOG_VERSION;
    echo '</a>';
    echo '</div>';
    echo '<strong>';
    echo _('Copyright');
    echo ' &copy; 2012-2023 ';
    echo ' <a href="https://fogproject.org">';
    echo 'FOG Project';
    echo '</a>';
    echo '</strong> ';
    echo _('All rights reserved.');
    echo '</footer>';
}
echo '</div>';
echo '<div id="scripts">';
self::$HookManager
    ->processEvent(
        'JS',
        ['javascripts' => &$this->stylesheets]
    );
foreach ((array)$this->javascripts as $javascript) {
    echo '<script src="'
        . $javascript
        . '?ver='
        . FOG_BCACHE_VER
        . '" type="text/javascript"></script>';
}
unset($this->javascripts);
echo '</div>';
echo '<!-- Memory Usage: ';
echo self::formatByteSize(memory_get_usage(true));
echo ' -->';
echo '<!-- Memory Peak: ';
echo self::formatByteSize(memory_get_peak_usage());
echo ' -->';
echo '</body>';
echo '</html>';
