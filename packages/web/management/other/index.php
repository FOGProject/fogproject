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
echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport"/>';
echo '<title>' . $this->pageTitle . '</title>';

self::$HookManager
    ->processEvent(
        'CSS',
        array(
            'stylesheets' => &$this->stylesheets
        )
    );
foreach ((array)$this->stylesheets as &$stylesheet) {
    echo '<link href="'
        . $stylesheet
        . '?ver='
        . FOG_BCACHE_VER
        . '" rel="stylesheet" type="text/css"/>';
    unset($stylesheet);
}

echo '<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->';
echo '<!--[if lt IE 9]>';
echo '<script src="dist/js/html5shiv.min.js"></script>';
echo '<script src="dist/js/respond.min.js"></script>';
echo '<![endif]-->';

unset($this->stylesheets);
echo '</head>';
echo '<body class="';
if (!self::$FOGUser->isValid())
    echo 'hold-transition login-page';
else
echo 'hold-transition skin-blue sidebar-mini';
echo '">';

if (self::$FOGUser->isValid()) {
    echo '<div class="wrapper">';
    
    // HEADER
    echo '  <header class="main-header">';
    echo '      <a href="./index.php" class="logo">';
    echo '          <span class="logo-mini"><b>FOG</b></span>';
    echo '          <span class="logo-lg"><b>FOG</b> Project</span>';
    echo '      </a>';
    echo '      <nav class="navbar navbar-static-top">';
    echo '          <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">';
    echo '              <span class="sr-only">Toggle navigation</span>';
    echo '              <span class="icon-bar"></span>';
    echo '              <span class="icon-bar"></span>';
    echo '              <span class="icon-bar"></span>';
    echo '          </a>';
    echo '          <div class="navbar-custom-menu">';
    echo '              <ul class="nav navbar-nav">';
    echo '                  <li>';
    echo '                       <a href="../management/index.php?node=logout"><i class="fa fa-sign-out"></i> Logout</a>';
    echo '                  </li>';
    echo '              </ul>';
    echo '          </div>';
    echo '      </nav>';
    echo '  </header>';

    // NAVBAR
    echo '  <aside class="main-sidebar">';
    echo '      <section class="sidebar">';
    echo '          <div class="user-panel">';
    //echo '              <div class="pull-left image">';
    //echo '                  <img src="dist/img/user2-160x160.jpg" class="img-circle" alt="User Image">';
    //echo '              </div>';
    echo '              <div class="">';
    echo '                  <center><a class="">' . trim(self::$FOGUser->get('name')) . '</a></center>';
   // echo '                  <a href="#"><i class="fa fa-circle text-success"></i> Online</a>';
    echo '              </div>';
    echo '          </div>';
    echo '          <ul class="sidebar-menu" data-widget="tree">';
    echo '              <li class="header">MAIN NAVIGATION</li>';
    echo $this->menu;
    echo '              <li class="header">RESOURCES</li>';
    echo '              <li><a href="https://sourceforge.net/donate/index.php?group_id=201099"><i class="fa fa-money"></i> <span>Donate</span></a></li>';    
    echo '              <li><a href="https://news.fogproject.org"><i class="fa fa-bullhorn"></i> <span>News</span></a></li>';
    echo '              <li><a href="https://forums.fogproject.org"><i class="fa fa-users"></i> <span>Forums</span></a></li>';
    echo '              <li><a href="https://wiki.fogproject.org"><i class="fa fa-book"></i> <span>Wiki</span></a></li>';
    echo '          </ul>';
    echo '      </section>';
    echo '  </aside>';

    // BODY
    echo '  <div class="content-wrapper">';
    echo '      <section class="content-header">';
    echo '          <h1 id="sectionTitle">';
    echo $this->sectionTitle;

    echo '              <small id="pageTitle">' . $this->pageTitle . '</small>';
    echo '          </h1>';
    echo '      </section>';
    echo '      <section class="content">';
    echo $this->body;
    echo '      </section>';
    echo '  </div>';

    // FOOTER
    echo '  <footer class="main-footer">';
    echo '      <div class="pull-right hidden-xs">';
    echo '          <b>Version</b> ' . FOG_VERSION;
    echo '      </div>';
    echo '      <strong>Copyright &copy; 2012-2018 <a href="https://fogproject.org">FOG Project</a>.</strong> All rights reserved.';
    echo '  </footer>';
    echo '</div>';
    
} else {
    echo $this->body;
}

foreach ((array)$this->javascripts as &$javascript) {
    echo '<script src="'
        . $javascript
        . '?ver='
        . FOG_BCACHE_VER
        . '" type="text/javascript"></script>';
    unset($javascript);
}
unset($this->javascripts);
// echo '<!-- Memory Usage: ';
// echo self::formatByteSize(memory_get_usage(true));
// echo '-->';
// echo '<!-- Memory Peak: ';
// echo self::formatByteSize(memory_get_peak_usage());
// echo '-->';
echo '</body>';
echo '</html>';
