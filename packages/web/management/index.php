<?php
// Require FOG Base
require('../commons/base.inc.php');
// Config load check
if (IS_INCLUDED !== true) die($foglang['NoLoad']);
// User session data
$currentUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
$MainMenu = new Mainmenu($currentUser);
$SubMenu = new SubMenu($currentUser);
// Process Login
$FOGCore->getClass('ProcessLogin')->processMainLogin();
// Login form + logout
if ($node != 'client' && ($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn()))
{
	// Hook
	$HookManager->processEvent('LOGOUT', array('user' => &$currentUser));
	// Logout
	if (method_exists($currentUser, 'logout'))
		$currentUser->logout();
	// Unset session variables
	unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER'], $_SESSION['AllowAJAXTasks'], $MainMenu);
	// Show login form
	$FOGCore->getClass('ProcessLogin')->mainLoginForm();
}
// Ping Active
$_SESSION['FOGPingActive'] = ($FOGCore->getSetting('FOG_HOST_LOOKUP') == '1' ? true : false);
// Allow AJAX Tasks
$_SESSION['AllowAJAXTasks'] = true;
// Are we on the Homeapge?
$isHomepage = (!$_REQUEST['node'] || in_array($_REQUEST['node'], array('home', 'dashboard','client')) ? true : false);
// Render content - must be done before anything is outputted so classes can change HTTP headers
$FOGPageManager = new FOGPageManager();
// Load Page Classes -> Render content based on incoming node variables
$content = $FOGPageManager->render();
// Section title
$sectionTitle = $FOGPageManager->getFOGPageName();
// Page Title - should be set after page has been rendered
$pageTitle = $FOGPageManager->getFOGPageTitle();
if ($FOGCore->isAJAXRequest())
{
	print $content; 
	exit;
}
ob_start('ob_gzhandler');
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
print "\n".'<html xmlns="http://www.w3.org/1999/xhtml">';
print "\n\t<head>";
print "\n\t\t".'<meta http-equiv="X-UA-Compatible" content="IE=Edge" />';
print "\n\t\t".'<meta http-equiv="content-type" content="text/json; charset=utf-8" />';
print "\n\t\t".'<title>'.($pageTitle ? $pageTitle.' &gt; ' : '').$sectionTitle.' &gt; FOG &gt; '.$foglang['Slogan'].'</title>';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/calendar/calendar-win2k-1.css" />';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/jquery-ui.css" />';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/jquery.organicTabs.css" />';
print "\n\t\t".'<!--<link rel="stylesheet" type="text/css" href="css/'.$GLOBALS['FOGCore']->getSetting('FOG_THEME').'" />-->';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/fog.css" />';
print "\n\t\t".'<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon" />';
// Hook
$HookManager->processEvent('CSS');
print "\n\t</head>";
print "\n<body>";
print "\n\t<!-- FOG Message Boxes -->";
print "\n\t".'<div id="loader-wrapper"><div id="loader"><div id="progress"></div></div></div>';
print "\n\t<!-- Main -->";
print "\n\t".'<div id="wrapper">';
print "\n\t\t<!-- Header -->";
print "\n\t\t".'<div id="header">';
print "\n\t\t".'<div id="logo">';
print "\n\t\t\t".'<h1><a href="'.$_SERVER['PHP_SELF'].'"><img src="images/fog-logo.png" title="'.$foglang['Home'].'" /><sup>'.FOG_VERSION.'</sup></a></h1>';
print "\n\t\t\t".'<h2>'.$foglang['Slogan'].'</h2>';
print "\n\t\t".'</div>';
print "\n\t\t".'<div id="menu">';
$MainMenu->mainMenu();
print "\n\t\t</div>";
print "\n\t</div>";
print "\n\t<!-- Content -->";
print "\n\t".'<div id="content"'.($isHomepage ? ' class="dashboard"' : '').'>';
print "\n\t\t<h1>".$sectionTitle.'</h1>';
print "\n\t\t".'<div id="content-inner">';
if ($FOGPageManager->isFOGPageTitleEnabled())
	printf('%s<h2>%s</h2>',"\n\t\t\t\t",$FOGPageManager->getFOGPageTitle());
print $content."\n\t\t</div>\n";
print "\n\t</div>";
if (!$isHomepage) 
{
	print "\n\t<!-- Menu -->";
	print "\n\t\t".'<div id="sidebar">';
	$SubMenu->buildMenu();
	print "\n\t\t</div>";
}
print "\n\t</div>";
print "\n\t<!-- Footer: Be nice, give us some credit -->";
print "\n\t".'<div id="footer">FOG Project: Chuck Syperski, Jian Zhang, Peter Gilchrist &amp; Tom Elliott FOG Client/Prep link: <a href="?node=client">FOG Client/FOG Prep</a></div>';
// Session Messages
$FOGCore->getMessages();
print "\n\t".'<div class="fog-variable" id="FOGPingActive">'.($_SESSION['FOGPingActive'] ? '1' : '0').'</div>';
print "\n\t<!-- JavaScript -->";
print "\n\t".'<script type="text/javascript" src="js/jquery-latest.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery-migrate-1.2.1.min.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/calendar/jquery.dynDateTime.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/calendar/calendar-en.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.tablesorter.min.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.tipsy.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.progressbar.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.tmpl.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.organicTabs.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.placeholder.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery.disableSelection.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/fog.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/fog.main.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/hideShowPassword.min.js"></script>';
print "\n\t".'<script type="text/javascript" src="js/jquery-ui.min.js"></script>';
// Auto find javascript based on $node and/or $sub
foreach (array("js/fog.{$node}.js", "js/fog.{$node}.{$sub}.js") AS $jsFilepath)
{
	if (file_exists($jsFilepath))
		printf('%s<script type="text/javascript" src="%s"></script>%s', "\n\t", $jsFilepath, "\n");
}
if ($isHomepage)
{
	print "\n\t".'<script type="text/javascript" src="js/jquery.flot.js"></script>';
	print "\n\t".'<script type="text/javascript" src="js/jquery.flot.pie.js"></script>';
	print "\n\t".'<script type="text/javascript" src="js/fog.dashboard.js"></script>';
	// Include 'excanvas' for HTML5 <canvas> support in IE 6/7/8/9...
	// I hate IE soooo much, only Microsoft wouldnt fix their own broken software
	if (preg_match('#MSIE [6|7|8|9|10|11]#', $_SERVER['HTTP_USER_AGENT']))
		print "\n\t".'<script type="text/javascript" src="js/excanvas.js"></script>';
}
// Hook
$HookManager->processEvent('JAVASCRIPT');
print "\n</body>";
print "\n</html>";
ob_end_flush();
