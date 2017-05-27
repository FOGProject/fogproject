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
echo '<div class="wrapper">';
echo '<div class="sidebar" data-color="blue">';
echo '<div class="sidebar-wrapper">';
echo '<div class="logo">';
printf(
    '<a href="%s" class="simple-text">'
    . '<img src="%s" alt="%s" title="%s" class="logoimg"/>'
    . '</a>',
    self::$scriptname,
    '../favicon.ico',
    self::$foglang['Home'],
    self::$foglang['Home']
);
echo '</div>';
if (self::$FOGUser->isValid()) {
    echo $this->menu;
}
echo '</div>';
echo '</div>';
echo '<div class="main-panel">';
echo '<nav class="navbar navbar-default navbar-fixed">';
echo '<div class="container-fluid">';
echo '<div class="navbar-header">';
echo '<button type="button" class="navbar-toggle" data-toggle='
    . '"collapse" data-target="#navigation-example-2">';
echo '<span class="sr-only">Toggle navigation</span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '<span class="icon-bar"></span>';
echo '</button>';
echo '<a class="navbar-brand" href="?node=home">';
echo self::$foglang['Slogan'];
echo '</div>';
echo '<div class="collapse navbar-collapse">';
echo '<ul class="nav navbar-nav navbar-right">';
echo '<li>';
echo '<a href="?node=logout">';
echo '<i class="fa fa-sign-out"></i>';
echo '<p>';
echo self::$foglang['Logout'];
echo '</p>';
echo '</a>';
echo '</li>';
echo '<li class="separator hidden-1g hidden-md"></li>';
echo '</ul>';
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
printf(
    '<h4 class="title">%s</h4>',
    $this->sectionTitle
);
if (self::$FOGUser->isValid() && $this->pageTitle) {
    printf(
        '<h5 class="title">%s</h5>',
        $this->pageTitle
    );
}
echo $this->body;
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
/*if (!self::$isMobile) {
    echo '<div id="loader-wrapper">';
    echo '<div id="loader"></div>';
    self::getMessages();
    echo '<div id="progress"></div>';
    echo '</div>';
    echo '<header>';
    printf(
        '<div id="header"%s>',
        (
            !self::$FOGUser->isValid() ?
            ' class="login"' :
            ''
        )
    );
    echo '<div id="version">';
    echo '<div id="showtime"></div>';
    printf(
        '%s %s<br/>%s: %d',
        _('Running Version'),
        FOG_VERSION,
        _('SVN Revision'),
        FOG_SVN_REVISION
    );
    echo '</div>';
    echo '</div>';
    if (self::$FOGUser->isValid()) {
        echo $this->menu;
    }
    echo '</div></header><hr/>';
    echo '<div id="wrapper">';
    if (self::$FOGUser->isValid()) {
        if (!$this->isHomepage) {
            echo self::$FOGPageManager->getSideMenu();
        }
    }
    echo '<div class="clear"></div>';
    printf(
        '<div id="content"%s>',
        (
            $this->isHomepage ?
            ' class="dashboard"' :
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
    echo '<div id="content-inner">';
    printf(
        '<h1>%s</h1>',
        $this->sectionTitle
    );
    if (self::$FOGUser->isValid() && $this->pageTitle) {
        printf(
            '<h2 class="title">%s</h2>',
            $this->pageTitle
        );
    }
    echo $this->body;
    echo '</div>';
    echo '</div>';
    echo '</div>';
    printf(
        '<!-- <div id="footer">Memory Usage: %s</div> -->'
        . '<!-- <div id="footer">Memory Peak: %s</div> -->',
        self::formatByteSize(memory_get_usage(true)),
        self::formatByteSize(memory_get_peak_usage())
    );
} else {
    echo '<div id="header"></div>';
    if (self::$FOGUser->isValid()) {
        echo '<div id="mainContainer">';
        echo '<div class="mainContent">';
        printf(
            '%s%s',
            $this->menu,
            (
                $this->pageTitle ?
                sprintf(
                    '<h2>%s</h2>',
                    $this->pageTitle
                ) :
                ''
            )
        );
        echo '<div id="mobile_content">';
        echo $this->body;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div id="mobile_content">';
        echo $this->body;
        echo '</div>';
    }
}
echo '</body>';*/
