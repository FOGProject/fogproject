<?php
require_once('../commons/base.inc.php');
$currentUser = $FOGCore->FOGUser = $HookManager->FOGUser = (!empty($_SESSION['FOG_USER']) ? unserialize($_SESSION['FOG_USER']) : null);
$Page = new Page();
$FOGCore->getClass('ProcessLogin')->processMainLogin();
if ($node != 'client' && !in_array($sub,array('configure','authorize')) && ($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn()))
{
	@session_write_close();
	@session_regenerate_id(true);
	// Hook
	$HookManager->processEvent('LOGOUT', array('user' => &$currentUser));
	// Logout
	if (method_exists($currentUser, 'logout'))
		$currentUser->logout();
	// Unset session variables
	unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER'], $_SESSION['AllowAJAXTasks']);
	// Show login form
	$Page->setTitle($foglang['Login']);
	$Page->setSecTitle($foglang['ManagementLogin']);
	$Page->startBody();
	$FOGCore->getClass('ProcessLogin')->mainLoginForm();
	$Page->endBody();
	$Page->render();
}
$FOGPageManager = new FOGPageManager();
$_SESSION['FOGPingActive'] = ($FOGCore->getSetting('FOG_HOST_LOOKUP') == '1' ? true : false);
$_SESSION['AllowAJAXTasks'] = true;
$content = $FOGPageManager->render();
$sectionTitle = $FOGPageManager->getFOGPageName();
$pageTitle = $FOGPageManager->getFOGPageTitle();
if ($FOGCore->isAJAXRequest())
{
	print $content;
	exit;
}
$Page->setTitle($pageTitle);
$Page->setSecTitle($sectionTitle);
$Page->startBody();
print $content;
$Page->endBody();
$Page->render();
