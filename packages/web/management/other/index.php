<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php if (!static::$isMobile) {
    foreach ($this->headJavascripts AS $i => &$javascript) {
        echo '<script src="' . $javascript . '?ver=' . FOG_BCACHE_VER . '" language="javascript" type="text/javascript" defer></script>';
    }
    unset($javascript);
?><meta http-equiv="X-UA-Compatible" content="IE=Edge" />
	<meta http-equiv="content-type" content="text/json; charset=utf-8" />
		<title><?php echo ($this->pageTitle ?  "$this->pageTitle &gt; $this->sectionTitle &gt; FOG &gt; ".static::$foglang['Slogan'] : "$this->sectionTitle &gt; FOG &gt; ".static::$foglang['Slogan']) ?></title><?php
} else { ?><meta name="viewport" content="width=device-width" />
			<meta name="viewport" content="initial-scale=1.0" />
				<title><?php echo 'FOG :: ' . _('Mobile Manager') . ' :: ' . _('Version') . ' ' . FOG_VERSION ?></title>
				<?php
}
$cnt = 0;
static::$HookManager->processEvent('CSS', array('stylesheets' => & $this->stylesheets));
foreach ($this->stylesheets AS $i => & $stylesheet) echo '<link href="'.$stylesheet.'?ver='.FOG_BCACHE_VER.'" rel="stylesheet" type="text/css" />';
unset($stylesheet); ?>
<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon"/>
</head>
<body>
<?php if (!static::$isMobile) { ?><div class="fog-variable" id="FOGPingActive"><?php echo (int) $_SESSION['FOGPingActive'] ?></div><?php
} ?>
<!-- Session Messages -->
<?php !static::$isMobile ? $this->getMessages() : '' ?>
<?php if (static::$isMobile) { // Mobile Login
     ?><div id="header"></div>
	<?php if (static::$FOGUser) { ?><div id="mainContainer">
		<div class="mainContent"><?php echo $this->menu;
        echo ($this->pageTitle ? "<h2>$this->pageTitle</h2>" : null) . "\n" ?>
			<div id="mobile_content">
			<?php echo $this->body ?>	</div>
			</div>
			</div><?php
    } else echo $this->body;
} else { // Main Login
     ?><!-- FOG Message Boxes -->
				<div id="loader-wrapper"><div id="loader"></div><div id="progress"></div></div>
					<!-- Main -->
					<div id="wrapper">
					<!-- Header --><header>
					<div id="header"<?php echo (!static::$FOGUser ? ' class="login"' : '') ?>>
					<div id="logo">
					<h1><a href="<?php echo static::$urlself ?>"><img src="<?php echo $this->imagelink ?>fog-logo.png" title="<?php echo static::$foglang['Home'] ?>" /><sup><?php echo FOG_VERSION ?></sup></a></h1>
					<h2><?php echo static::$foglang['Slogan'] ?></h2>
					</div>
					<?php if (static::$FOGUser) { ?><!-- Mainmenu -->
							<?php echo $this->menu ?>
							<?php
    } ?></div>
							<?php if (static::$FOGUser && !$this->isHomepage) { ?><!-- Submenu -->
								<?php echo static::$FOGPageManager->getSideMenu();
    } ?>
								</header><!-- Content -->
								<div id="content"<?php echo ($this->isHomepage ? ' class="dashboard"' : '') ?>>
								<?php echo "<h1>$this->sectionTitle</h1>\n" ?>
								<div id="content-inner">
								<?php if (static::$FOGUser) {
        echo ($this->pageTitle ? "<h2>$this->pageTitle</h2>" : null);
        static::$HookManager->processEvent('CONTENT_DISPLAY', array('content' => & $this->body, 'sectionTitle' => & $this->sectionTitle, 'pageTitle' => & $this->pageTitle));
    }
    echo $this->body
?>
					</div>
					</div>
					</div>
					<!-- Footer: Be nice, give us some credit -->
					<div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a></div>
					<!-- <div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a> Memory Usage: <?php echo $this->formatByteSize(memory_get_usage(true)) ?></div> -->
					<!-- <div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a> Memory Usage: <?php echo $this->formatByteSize(memory_get_peak_usage()) ?></div> -->
					<!-- Javascript -->
					<?php $cnt = 0;
    static::$HookManager->processEvent('JAVASCRIPT',array('javascripts'=>&$this->javascripts));
    foreach ($this->javascripts AS $i => &$javascript) {
        echo ($cnt++ > 0 ? "\t\t" : '') . '<script src="' . $javascript . '?ver=' . FOG_BCACHE_VER . '" language="javascript" type="text/javascript" defer></script>' . "\n";
    }
    unset($javascript);
} ?>
</body>
</html>
