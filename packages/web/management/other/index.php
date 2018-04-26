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
echo '<!DOCTYPE html>';
echo '<html lang="'
    . ProcessLogin::getLocale()
    . '">';
echo '<head>';
echo '<meta charset="utf-8"/>';
echo '<meta http-equiv="X-UA-Compatible" content="IE=edge"/>';
echo '<meta name="viewport" content="width=device-width, '
    . 'initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport"/>';
echo '<meta name="theme-color" content="#367fa9"/>';
echo '<title>' . $this->pageTitle . '</title>';

self::$HookManager
    ->processEvent(
        'CSS',
        ['stylesheets' => &$this->stylesheets]
    );
foreach ((array)$this->stylesheets as &$stylesheet) {
    echo '<link href="'
        . $stylesheet
        . '?ver='
        . FOG_BCACHE_VER
        . '" rel="stylesheet" type="text/css"/>';
    unset($stylesheet);
}

echo '<!-- HTML5 Shim and Respond.js IE8 support of HTML5'
    . 'elements and media queries -->';
echo '<!--[if lt IE 9]>';
echo '<script src="dist/js/html5shiv.min.js"></script>';
echo '<script src="dist/js/respond.min.js"></script>';
echo '<![endif]-->';

unset($this->stylesheets);
echo '</head>';
echo '<body class="';
if (!self::$FOGUser->isValid()) {
    echo 'hold-transition login-page';
} else {
    echo 'hold-transition skin-blue sidebar-mini';
}
echo '">';

echo '<div class="wrapper">';

// HEADER
echo '<header class="main-header">';
if (self::$FOGUser->isValid()) {
    echo '<a href="./index.php" class="logo">';
    echo '<span class="logo-mini"><b>FOG</b></span>';
    echo '<span class="logo-lg"><b>FOG</b> Project</span>';
    echo '</a>';
}
echo '<nav class="navbar navbar-static-top">';
if (self::$FOGUser->isValid()) {
    echo '<p class="mobile-logo">';
    echo '<a href="../management/index.php">';
    echo '<b>'
        . _('FOG')
        . '</b> '
        . _('Project')
        . '</a>';
    echo '</p>';
    echo '<a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">';
    echo '<span class="sr-only">Toggle navigation</span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '</a>';
}
echo '<div class="navbar-custom-menu">';
echo '<ul class="nav navbar-nav">';
echo '<li>';
if (self::$FOGUser->isValid()) {
    echo '<a href="../management/index.php?node=logout">';
    echo '<i class="fa fa-sign-out"></i> ';
    echo _('Logout');
    echo '</a>';
} else {
    global $node;
    if ($node != 'home') {
        echo '<a href="../management/index.php?node=login">';
        echo '<i class="fa fa-sign-in"></i> ';
        echo _('Login');
        echo '</a>';
    }
}
echo '</li>';
echo '</ul>';
echo '</div>';
echo '</nav>';
echo '</header>';
if (self::$FOGUser->isValid()) {
    $userDisp = trim(self::$FOGUser->getDisplayName());
    if (!$userDisp) {
        $userDisp = trim(self::$FOGUser->get('name'));
    }
    // NAVBAR
    echo '<aside class="main-sidebar">';
    echo '<section class="sidebar">';
    echo '<div class="user-panel">';
    echo '<div class="">';
    echo '<a href="../management/index.php?node=user&sub=edit&id='
        . self::$FOGUser->get('id')
        . '" class="fog-user">'
        . $userDisp
        . '</a>';
    echo '</div>';
    echo '</div>';
    echo FOGPage::makeFormTag(
        'sidebar-form',
        'universal-search-form',
        '../fog/unisearch',
        'post',
        'application/x-www-form-urlencoded',
        true
    );
    echo '<div class="">';
    echo '<select id="universal-search-select" class="form-control" name="search"'
        . ' data-placeholder="'
        . _('Search')
        . '...">';
    echo '</select>';
    echo '</div>';
    echo '</form>';
    echo '<ul class="sidebar-menu" data-widget="tree">';
    echo '<li class="header">';
    echo _('MAIN NAVIGATION');
    echo '</li>';
    echo $this->menu;
    if (self::getSetting('FOG_PLUGINSYS_ENABLED')) {
        echo '<li class="header">';
        echo '<span class="pull-left">';
        echo _('PLUGIN OPTIONS');
        echo '</span> ';
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
    echo '<li class="header">'
        . _('RESOURCES')
        . '</li>';
    echo '<li>';
    echo '<a href="https://sourceforge.net/donate/index.php?group_id=201099" '
       . 'target="_blank">';
    echo '<i class="fa fa-money"></i> ';
    echo '<span>'
        . _('Donate')
        . '</span>';
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
    echo '<a href="https://wiki.fogproject.org" target="_blank">';
    echo '<i class="fa fa-book"></i> ';
    echo '<span>';
    echo _('Wiki');
    echo '</span>';
    echo '</a>';
    echo '</li>';
    echo '</ul>';
    echo '</section>';
    echo '</aside>';

    // BODY
    echo '  <div class="content-wrapper">';
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
        $Service = self::getClass('Service')
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
        unset($Service);
    }
    echo FOGPage::makeInput(
        'pageLength',
        'pageLength',
        '',
        'hidden',
        'pageLength',
        self::getSetting('FOG_VIEW_DEFAULT_SCREEN')
    );
    echo '<section class="content-header">';
    echo '<h1 id="sectionTitle">';
    echo $this->sectionTitle;
    echo '<small id="pageTitle">' . $this->pageTitle . '</small>';
    echo '</h1>';
    echo '</section>';
    echo '<section class="content">';
    echo $this->body;
    echo '</section>';
    echo '</div>';

    // FOOTER
    echo '<footer class="main-footer">';
    echo '<div class="pull-right hidden-xs">';
    echo '<b>';
    echo _('Channel');
    echo '</b> ' . FOG_CHANNEL . ' | ';
    echo '<a href="../management/index.php?node=about&sub=home" '
        . 'style="text-decoration: none">';
    echo '<b>';
    echo _('Version');
    echo '</b> ' . FOG_VERSION;
    echo '</a>';
    echo '</div>';
    echo '<strong>'
        . _('Copyright')
        . ' &copy; 2012-2018 '
        . '<a href="https://fogproject.org">FOG Project</a>'
        . '.</strong> '
        . _('All rights reserved.');
    echo '</footer>';
} else {
    echo $this->body;
}
echo '</div>';

foreach ((array)$this->javascripts as &$javascript) {
    echo '<script src="'
        . $javascript
        . '?ver='
        . FOG_BCACHE_VER
        . '" type="text/javascript"></script>';
    unset($javascript);
}
unset($this->javascripts);
echo '<!-- Memory Usage: ';
echo self::formatByteSize(memory_get_usage(true));
echo '-->';
echo '<!-- Memory Peak: ';
echo self::formatByteSize(memory_get_peak_usage());
echo '-->';
echo '</body>';
echo '</html>';
