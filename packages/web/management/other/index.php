<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<?php if (!preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) { ?><meta http-equiv="X-UA-Compatible" content="IE=Edge" />
		<meta http-equiv="content-type" content="text/json; charset=utf-8" />
		<title><?php $this->pageTitle ? print "$this->pageTitle &gt; $this->sectionTitle &gt; FOG &gt; {$this->foglang['Slogan']}" : "$this->sectionTitle &gt; FOG &gt; {$this->foglang['Slogan']}" ?></title><?php } else { ?><meta name="viewport" content="width=device-width" />
		<meta name="viewport" content="initial-scale=1.0" />
		<title><?php print 'FOG :: '._('Mobile Manager').' :: '._('Version').' '.FOG_VERSION ?></title>
		<?php } $cnt=0; $this->HookManager->processEvent('CSS',array('stylesheets' => &$this->stylesheets)); foreach($this->stylesheets AS $stylesheet) {
			print ($cnt++ > 0 ? "\t\t" : '').'<link href="'.$stylesheet.'" rel="stylesheet" type="text/css" />'."\n";
		} ?>
		<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon"/>
	</head>
	<body>
		<?php if (preg_match('#/mobile/#i',$_SERVER['PHP_SELF'])) { if ($this->FOGUser && $this->FOGUser->isLoggedIn()) { ?><div id="mainContainer">
			<div id="header"></div>
			<div class="mainContent"><?php print $this->menu."\n\t\t\t\t";
				print ($this->pageTitle ? "<h2>$this->pageTitle</h2>" : null)."\n" ?>
				<div id="mobile_content">
				<?php print $this->body ?>	</div>
			</div>
		</div><?php } else print $this->body; } else { ?><!-- FOG Message Boxes -->
		<div id="loader-wrapper"><div id="loader"><div id="progress"></div></div></div>
		<!-- Main -->
		<div id="wrapper">
			<!-- Header -->
			<div id="header"<?php !$this->FOGUser ? print ' class="login"' : ''?>>
				<div id="logo">
					<h1><a href="<?php print $_SERVER['PHP_SELF'] ?>"><img src="images/fog-logo.png" title="<?php print $this->foglang['Home'] ?>" /><sup><?php print FOG_VERSION ?></sup></a></h1>
					<h2><?php print $this->foglang['Slogan'] ?></h2>
				</div>
				<?php if ($this->FOGUser && $this->FOGUser->isLoggedIn()) { ?><!-- Mainmenu -->
				<div id="menu">
					<?php print $this->menu ?>
				</div>
				<?php } ?></div>
			<!-- Content -->
			<div id="content"<?php $this->isHomepage ? print ' class="dashboard"' : '' ?>>
				<?php print "<h1>$this->sectionTitle</h1>\n" ?>
				<div id="content-inner">
					<?php if ($this->FOGUser && $this->FOGUser->isLoggedIn()) {
						$this->pageTitle ? print "<h2>$this->pageTitle</h2>" : null;
						$this->HookManager->processEvent('CONTENT_DISPLAY',array('content' => &$this->body,'sectionTitle' => &$this->sectionTitle,'pageTitle' => &$this->pageTitle));
					}
					print $this->body."\n" ?>
				</div>
			</div>
			<?php if ($this->FOGUser && $this->FOGUser->isLoggedIn() && !$this->isHomepage) { ?><!-- Submenu -->
			<div id="sidebar">
			<?php print $this->submenu ?>
			</div>
			<?php } ?>
		</div>
		<!-- Footer: Be nice, give us some credit -->
		<div id="footer"><a href="http://fogproject.org/wiki/index.php/Credits">Credits</a>&nbsp;&nbsp;<a href="?node=client">FOG Client/FOG Prep</a></div>
		<!-- Session Messages -->
		<?php $this->FOGCore->getMessages() ?>
		<div class="fog-variable" id="FOGPingActive"><?php intval($_SESSION['FOGPingActive']) ?></div>
		<!-- Javascript -->
		<?php $cnt=0; $this->HookManager->processEvent('JAVASCRIPT',array('javascripts' => &$this->javascripts)); foreach($this->javascripts AS $javascript) {
			print ($cnt++ > 0 ? "\t\t" : '').'<script src="'.$javascript.'" language="javascript" type="text/javascript" defer="defer"></script>'."\n";
		} } ?>
	</body>
</html>
