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
echo '<meta name="viewport" content="width=device-width, initial-scale=1"/>';
echo '<title>' . $this->title . '</title>';
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
unset($this->stylesheets);
echo '<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon"/>';
echo '</head>';
echo '<body>';
echo '<div class="wrapper'
    . (
        !self::$FOGUser->isValid() ?
        ' signin' :
        ''
    )
    . '">';
if (self::$FOGUser->isValid()) {
    echo '<input type="hidden" class="fog-delete" id="FOGDeleteAuth" value="'
        . (int)self::$fogdeleteactive
        . '"/>';
    echo '<input type="hidden" class="fog-export" id="FOGExportAuth" value="'
        . (int)self::$fogexportactive
        . '"/>';
    echo '<input type="hidden" class="fog-variable" id="screenview" value="'
        . self::$defaultscreen
        . '"/>';
    echo '<div class="sidebar" data-color="blue">';
    echo '<div class="sidebar-wrapper">';
    echo $this->menu;
    echo '</div>';
    echo '</div>';
    echo '<div class="main-panel">';
    echo '<nav class="navbar navbar-default navbar-fixed">';
    echo '<div class="container-fluid">';
    echo '<div class="navbar-header navbar-fixed">';
    echo '<button type="button" class="navbar-toggle" data-toggle="collapse">';
    echo '<span class="sr-only">'
        . _('Toggle Navigation')
        . '</span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '</button>';
    echo '<a class="navbar-brand" href="?node=home">';
    echo '<img src="../favicon.ico" alt="'
        . self::$foglang['Slogan']
        . '" title="'
        . self::$foglang['Home']
        . '" class="logoimg"/>';
    echo '</a>';
    echo '</div>';
    echo '<div class="collapse navbar-collapse">';
    if (!$this->isHomepage) {
        echo self::$FOGPageManager->getSideMenu();
    }
    echo '<ul class="nav navbar-nav navbar-right">';
    echo '<li><a href="?node=logout">'
        . self::$foglang['Logout']
        . '</a></li>';
    echo '<li class="separator hidden-1g hidden-md"></li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</nav>';
    echo '<div class="content'
        . (
            $this->isHomepage ?
            ' dashboard' :
            ''
        )
        . '">';
    self::$HookManager
        ->processEvent(
            'CONTENT_DISPLAY',
            array(
                'content' => &$this->body,
                'sectionTitle' => &$this->sectionTitle,
                'pageTitle' => &$this->pageTitle
            )
        );
    echo '<div class="container-fluid">';
    echo '<div class="card">';
    echo '<div class="row text-center">';
    echo '<h4 class="title">'
        . $this->sectionTitle
        . '</h4>';
    echo '</div>';
    if (self::$FOGUser->isValid && $this->pageTitle) {
        echo '<div class="row text-center">';
        echo '<h5 class="title">'
            . $this->pageTitle
            . '</h5>';
        echo '</div>';
    }
    echo $this->body;
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<footer class="footer">';
    echo '<div class="container-fluid">';
    echo '<nav class="pull-left">';
    echo '<ul>';
    echo '<li><a href="https://wiki.fogproject.org/wiki/index.php?title=Credits">'
        . _('Credits')
        . '</a></li>';
    echo '<li><a href="?node=client">'
        . _('FOG Client')
        . '</a></li>';
    echo '<li><a href="https://www.paypal.com/cgi-bin/webscr?item_name=Donation'
        . '+to+FOG+-+A+Free+Cloning+Solution&cmd=_donations&business=fogproject.org'
        . '@gmail.com">'
        . _('Donate to FOG')
        . '</a></li>';
    echo '</ul>';
    echo '</nav>';
    echo '</div>';
    echo '</footer>';
} else {
    echo $this->body;
    echo '<footer class="footer">';
    echo '<div class="container-fluid">';
    echo '<nav class="navbar navbar-default navbar-fixed-bottom">';
    echo '<div class="container text-center">';
    echo '<div class="col-xs-4">';
    echo '<a href="https://wiki.fogproject.org/wiki/index.php?title=Credits">'
        . _('Credits')
        . '</a>';
    echo '</div>';
    echo '<div class="col-xs-4">';
    echo '<a href="?node=client">'
        . _('FOG Client')
        . '</a>';
    echo '</div>';
    echo '<div class="col-xs-4">';
    echo '<a href="https://www.paypal.com/cgi-bin/webscr?item_name=Donation'
        . '+to+FOG+-+A+Free+Cloning+Solution&cmd=_donations&business=fogproject.org'
        . '@gmail.com">'
        . _('Donate to FOG')
        . '</a>';
    echo '</div>';
    echo '</div>';
    echo '</nav>';
    echo '</div>';
    echo '</footer>';
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
echo '</body>';
echo '</html>';
