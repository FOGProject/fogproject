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
printf(
    '<html lang="%s">',
    ProcessLogin::getLocale()
);
echo '<head>';
echo '<meta charset="utf-8"/>';
echo '<meta http-equiv="X-UA-Compatible" content="IE=edge"/>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1"/>';
echo '<title>'.$this->title.'</title>';
self::$HookManager
    ->processEvent(
        'CSS',
        array(
            'stylesheets' => &$this->stylesheets
        )
    );
foreach ((array)$this->stylesheets as &$stylesheet) {
    printf(
        '<link href="%s?ver=%d" rel="stylesheet" type="text/css"/>',
        $stylesheet,
        FOG_BCACHE_VER
    );
    unset($stylesheet);
}
unset($this->stylesheets);
echo '<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon"/>';
echo '</head>';
echo '<body>';
printf(
    '<input type="hidden" class="fog-delete" id="FOGDeleteAuth" value="%s"/>',
    (int)self::$fogdeleteactive
);
printf(
    '<input type="hidden" class="fog-export" id="FOGExportAuth" value="%s"/>',
    (int)self::$fogexportactive
);
printf(
    '<div class="fog-variable" id="screenview" value="%s"></div>',
    self::$defaultscreen
);
if (self::$FOGUser->isValid()) {
    echo '<div class="wrapper">';
    echo '<div class="sidebar" data-color="blue">';
    echo '<div class="sidebar-wrapper">';
    echo $this->menu;
    echo '</div>';
    echo '</div>';
    echo '<div class="main-panel">';
    echo '<nav class="navbar navbar-default navbar-fixed">';
    echo '<div class="container-fluid">';
    echo '<div class="navbar-header navbar-fixed">';
    echo '<button type="button" class="navbar-toggle" data-toggle='
        . '"collapse" data-target="#navigation-example-2">';
    echo '<span class="sr-only">Toggle navigation</span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '</button>';
    echo '<a class="navbar-brand" href="?node=home">';
    printf(
        '<img src="%s" alt="%s" title="%s" class="logoimg"/>',
        '../favicon.ico',
        self::$foglang['Slogan'],
        self::$foglang['Home']
    );
    echo '</a>';
    echo '</div>';
    echo '<div class="collapse navbar-collapse">';
    if (!$this->isHomepage) {
        echo self::$FOGPageManager->getSideMenu();
    }
    echo '<ul class="nav navbar-nav navbar-right">';
    echo '<li>';
    echo '<a href="?node=logout">';
    echo self::$foglang['Logout'];
    echo '</a>';
    echo '</li>';
    echo '<li class="separator hidden-1g hidden-md"></li>';
    echo '</ul>';
    global $node;
    global $sub;
    if (in_array($node, self::$searchPages)) {
        echo '<form class="navbar-form search-wrapper" role='
            . '"search" method="post" action="'
            . '?node='
            . $node
            . '&sub=search'
            . '">';
        echo '<div class="input-group">';
        echo '<input type="text" class='
            . '"form-control search-input placeholder" placeholder='
            . '"'
            . self::$foglang['Search']
            . '..." name="crit"/>';
        echo '<span class="input-group-addon search-submit">';
        echo '<i class="fa fa-search">';
        echo '<span class="sr-only">';
        echo self::$foglang['Search'];
        echo '</span>';
        echo '</i>';
        echo '</span>';
        echo '</div>';
        echo '</form>';
    }
    echo '</div>';
    echo '</div>';
    echo '</nav>';
    printf(
        '<div class="content%s">',
        (
            $this->isHomepage ?
            ' dashboard' :
            ''
        )
    );
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
    printf(
        '<h4 class="title">%s</h4>',
        $this->sectionTitle
    );
    echo '</div>';
    echo '<div class="row text-center">';
    if (self::$FOGUser->isValid() && $this->pageTitle) {
        printf(
            '<h5 class="title">%s</h5>',
            $this->pageTitle
        );
    }
    echo '</div>';
    echo $this->body;
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<footer class="footer">';
    echo '<div class="container-fluid">';
    echo '<nav class="pull-left">';
    echo '<ul>';
    echo '<li>';
    printf(
        '<a href="http://fogproject.org/wiki/index.php?title=Credits">%s</a>',
        _('Credits')
    );
    echo '</li>';
    echo '<li>';
    printf(
        '<a href="?node=client">%s</a>',
        _('FOG Client')
    );
    echo '</li>';
    echo '<li>';
    printf(
        '<a href="https://www.paypal.com/cgi-bin/webscr?'
        . 'item_name=Donation+to+FOG+-+A+Free+Cloning+Solution&cmd=_donations'
        . '&business=fogproject.org@gmail.com">%s</a>',
        _('Donate to FOG')
    );
    echo '<li>';
    echo '</ul>';
    echo '</nav>';
    echo '</div>';
    echo '</footer>';
    echo '</div>';
} else {
    echo '<div class="wrapper signin">';
    echo $this->body;
    echo '<footer class="footer">';
    echo '<div class="container-fluid">';
    echo '<nav class="navbar navbar-default navbar-fixed-bottom">';
    echo '<div class="container text-center">';
    echo '<div class="span3">';
    echo '<span class="span1">';
    printf(
        '<a href="http://fogproject.org/wiki/index.php?title=Credits">%s</a>',
        _('Credits')
    );
    echo '</span>&nbsp;&nbsp;';
    echo '<span class="span1">';
    printf(
        '<a href="?node=client">%s</a>',
        _('FOG Client')
    );
    echo '</span>&nbsp;&nbsp;';
    echo '<span class="span1">';
    printf(
        '<a href="https://www.paypal.com/cgi-bin/webscr?'
        . 'item_name=Donation+to+FOG+-+A+Free+Cloning+Solution&cmd=_donations'
        . '&business=fogproject.org@gmail.com">%s</a>',
        _('Donate to FOG')
    );
    echo '</span>';
    echo '</div>';
    echo '</nav>';
    echo '</div>';
    echo '</footer>';
}
echo '</div>';
echo '</body>';
foreach ((array)$this->javascripts as &$javascript) {
    printf(
        '<script src="%s?ver=%d" type="text/javascript"></script>',
        $javascript,
        FOG_BCACHE_VER
    );
    unset($javascript);
}
unset($this->javascripts);
echo '</html>';
