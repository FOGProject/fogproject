<?php
//ob_start();
echo '<!DOCTYPE html><html><head>';
if (!self::$isMobile) {
    echo '<meta http-equiv="X-UA-Compatible" content="IE=Edge"/><meta http-equiv="content-type" content="text/html; charset=utf-8"/><title>';
    printf('%s%s &gt; FOG &gt; %s</title>',($this->pageTitle ? "$this->pageTitle &gt; " : ''),$this->sectionTitle,self::$foglang['Slogan']);
} else {
    echo '<meta name="viewport" content="width-device-width"/><meta http-equiv="viewport" content="initial-scale=1.0"/><title>';
    printf('FOG :: %s :: %s %s</title>',_('Mobile Manager'),_('Version'),FOG_VERSION);
}
$cnt = 0;
self::$HookManager->processEvent('CSS',array('stylesheets'=>&$this->stylesheets));
array_map(function(&$stylesheet) {
    printf('<link href="%s?ver=%d" rel="stylesheet" type="text/css"/>',$stylesheet,FOG_BCACHE_VER);
    unset($stylesheet);
},(array)$this->stylesheets);
echo '<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon"/></head><body>';
if (!self::$isMobile) {
    printf('<div class="fog-variable" id="FOGPingActive">%s</div>',$_SESSION['FOGPingActive']);
    $this->getMessages();
    echo '<div id="loader-wrapper"><div id="loader"></div><div id="progress"></div></div>';
    echo '<div id="wrapper">';
    echo '<header>';
    printf('<div id="header"%s>',!self::$FOGUser->isValid() ? ' class="login"' : '');
    echo '<div id="logo">';
    printf('<h1><a href="%s"><img src="%s/fog-logo.png" alt="%s" title="%s"/></a></h1><h2>%s</h2>',self::$urlself,$this->imagelink,self::$foglang['Home'],self::$foglang['Home'],self::$foglang['Slogan']);
    printf('<div id="version"><div id="showtime"></div>%s %s<br/>%s: %d</div></div>',_('Running Version'),FOG_VERSION,_('SVN Revision'),FOG_SVN_REVISION); // # #
    if (self::$FOGUser) {
        echo $this->menu;
        if (!$this->isHomepage) echo self::$FOGPageManager->getSideMenu();
    }
    echo '</div></header>';
    printf('<div id="content"%s>',$this->isHomepage ? ' class="dashboard"' : '');
    self::$HookManager->processEvent('CONTENT_DISPLAY',array('content'=>&$this->body,'sectionTitle'=>&$this->sectionTitle,'pageTitle'=>&$this->pageTitle));
    echo '<div id="content-inner">';
    echo "<h1>$this->sectionTitle</h1>";
    if (self::$FOGUser->isValid() && $this->pageTitle) echo "<h2>$this->pageTitle</h2>";
    echo "$this->body</div></div></div>";
    printf('<div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client</a>&nbsp;&nbsp;<a href="https://www.paypal.com/cgi-bin/webscr?item_name=Donation+to+FOG+-+A+Free+Cloning+Solution&cmd=_donations&business=fogproject.org@gmail.com">%s</a></div>',_('Donate to FOG'));
    printf('<!-- <div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a> Memory Usage: %s</div> -->',$this->formatByteSize(memory_get_usage(true)));
    printf('<!-- <div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a> Memory Usage: %s</div> -->',$this->formatByteSize(memory_get_peak_usage()));
    array_map(function($javascript) {
        printf('<script src="%s?ver=%d" type="text/javascript"></script>',$javascript,FOG_BCACHE_VER);
        unset($javascript);
    },(array)$this->javascripts);
} else {
    echo '<div id="header"></div>';
    if (self::$FOGUser->isValid()) {
        printf('<div id="mainContainer"><div class="mainContent">%s%s<div id="mobile_content">%s</div></div></div>',
            $this->menu,
            $this->pageTitle ? "<h2>$this->pageTitle</h2>" : '',
            $this->body
        );
    } else printf('<div id="mobile_content">%s</div>',$this->body);
}
echo '</body></html>';
