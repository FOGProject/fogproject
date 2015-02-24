<?php
require_once('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'],array('deletemulti','deleteconf')))
	unset($_SESSION['delitems']);
$currentUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : null);
$EventManager = new EventManager();
$EventManager->load();
$Page = new Page();
$FOGCore->getClass('ProcessLogin')->processMainLogin();
if (!in_array($node,array('schemaupdater','client')) && !in_array($sub,array('configure','authorize')) && ($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn()))
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
