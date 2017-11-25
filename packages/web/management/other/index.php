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
echo '<title>' . $this->title . '</title>';
echo '<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->';
echo '<!--[if lt IE 9]>';
echo '<script src="dist/js/html5shiv.min.js"></script>';
echo '<script src="dist/js/respond.min.js"></script>';
echo '<![endif]-->';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">';


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
echo '</head>';
echo '<body class="';
if (!self::$FOGUser->isValid())
    echo 'hold-transition login-page';
echo '">';

if (self::$FOGUser->isValid()) {
    /**
     * Navigation items
     */
    echo '<nav class="navbar navbar-inverse navbar-fixed-top">';
    echo '<div class="container-fluid">';
    echo '<div class="navbar-header">';
    echo '<button type="button" class="navbar-toggle collapsed" data-toggle="'
        . 'collapse" data-target=".navbar-collapse">';
    echo '<span class="sr-only">'
        . _('Toggle Navigation')
        . '</span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '<span class="icon-bar"></span>';
    echo '</button>';
    echo '<a class="navbar-brand" href="../management/index.php?node=home">';
    echo '<img src="../favicon.ico" alt="'
        . self::$foglang['Slogan']
        . '" data-toggle="tooltip" data-placement="bottom" title="'
        . self::$foglang['Home']
        . '" class="logoimg"/>';
    echo '</a>';
    echo '<span class="nav-text version-info pull-left">';
    printf(
        '%s %s<br/>%s: %d<br/>',
        _('Running Version'),
        FOG_VERSION,
        _('SVN Revision'),
        FOG_SVN_REVISION
    );
    echo '<span id="showtime">'
        . FOGCore::formatTime(
            'Now',
            'M d, Y G:i a'
        )
        . '</span>';
    echo '</span>';
    echo '</div>';
    echo '<div class="collapse navbar-collapse">';
    echo '<ul class="nav navbar-nav">';
    self::getSearchForm();
    echo $this->menu;
    self::getLogout();
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    echo '</nav>';
    self::$HookManager
        ->processEvent(
            'CONTENT_DISPLAY',
            array(
                'content' => &$this->body,
                'sectionTitle' => &$this->sectionTitle,
                'pageTitle' => &$this->pageTitle
            )
        );
    /**
     * Main Content
     */
    echo '<div class="container-fluid'
        . (
            $this->isHomepage ?
            ' dashboard' :
            ''
        )
        . '">';
    echo '<div class="panel panel-primary">';
    echo '<div class="panel-heading text-center">';
    echo '<h4 class="title">'
        . $this->sectionTitle
        . '</h4>';
    if (self::$FOGUser->isValid && $this->pageTitle) {
        echo '<h5 class="title">'
            . $this->pageTitle
            . '</h5>';
    }
    echo '</div>';
    echo '<input type="hidden" class="fog-delete" id="FOGDeleteAuth" value="'
        . (int)self::$fogdeleteactive
        . '"/>';
    echo '<input type="hidden" class="fog-export" id="FOGExportAuth" value="'
        . (int)self::$fogexportactive
        . '"/>';
    echo '<input type="hidden" class="fog-variable" id="screenview" value="'
        . self::$defaultscreen
        . '"/>';
    echo '<div class="panel-body">';
    self::getMenuItems();
    self::getMainSideMenu();
    echo $this->body;
    echo '</div>';
    echo '</div>';
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
echo '<!-- Memory Usage: ';
echo self::formatByteSize(memory_get_usage(true));
echo '-->';
echo '<!-- Memory Peak: ';
echo self::formatByteSize(memory_get_peak_usage());
echo '-->';
echo '</body>';
echo '</html>';
