<?php
// Require FOG Base
require_once('../commons/base.inc.php');
// User session data
$currentUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
// Process Login
$FOGCore->getClass('ProcessLogin')->processMobileLogin();
// Login form + logout
if($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn())
{
	@session_write_close();
	@session_regenerate_id(true);
	// Logout
	if(method_exists($currentUser, 'logout'))
		$currentUser->logout();
	// Unset Session Variables
	unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER'], $MainMenu);
	// Show login form
	$content = $FOGCore->getClass('ProcessLogin')->mobileLoginForm();
}
$_SESSION['AllowAJAXTasks'] = true;
// Render content - must be done before anything is outputted so classes can change HTTP headers
$FOGPageManager = new FOGPageManager();
// Load Page Classes -> Render content based on incoming node variables
if ($node != 'logout')
	$content = $FOGPageManager->render();
// Section title
$sectionTitle = $FOGPageManager->getFOGPageName();
// Page Title - should be set after page has been rendered
$pageTitle = $FOGPageManager->getFOGPageTitle();
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
print "\n".'<html xmlns="http://www.w3.org/1999/xhtml">';
print "\n\t<head>";
print "\n\t\t".'<link media="only screen and (max-device-width: 320px)" rel="stylesheet" type="text/css" href="css/main.css" />';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="css/main.css" />';
print "\n\t\t".'<meta name="viewport" content="width=320" />';
print "\n\t\t".'<meta name="viewport" content="initial-scale=1.0" />';
print "\n\t\t<title>"._("FOG :: Mobile Manager :: Version")." ".FOG_VERSION.'</title>';
print "\n\t</head>";
print "\n<body>";
print "\n\t".'<div id="mainContainer">';
print "\n\t\t".'<div id="header"></div>';
print "\n\t\t".'<div class="mainContent">';
if ($currentUser && $currentUser->isLoggedIn())
{
	$MainMenu = new Mainmenu();
	$MainMenu->mainMenu();
}
if ($FOGPageManager->isFOGPageTitleEnabled())
	print "\n\t\t\t\t<h2>".$FOGPageManager->getFOGPageTitle().'</h2>';
print "\n\t\t\t".'<div id="mobile_content">';
print $content;
print "\n\t\t\t</div>";
print "\n\t\t</div>";
print "\n\t</div>";
print "\n</body>";
print "\n</html>";
ob_end_flush();
