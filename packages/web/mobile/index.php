<?php
require_once('../commons/base.inc.php');
$currentUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : null);
$Page = new Page();
$FOGCore->getClass('ProcessLogin')->processMainLogin();
if ($node != 'client' && ($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn()))
{
	@session_write_close();
	@session_regenerate_id(true);
	// Hook
	$HookManager->processEvent('LOGOUT', array('user' => &$currentUser));
	// Logout
	if (method_exists($currentUser, 'logout'))
		$currentUser->logout();
	// Unset session variables
	unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER']);
	// Show login form
	header('X-Content-Type-Options: nosniff');
	header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
	header('X-XSS-Protection: 1; mode=block');
	header('X-Frame-Options: deny');
	header('Cache-Control: no-cache');
	$Page->setTitle($foglang['Login']);
	$Page->setSecTitle($foglang['ManagementLogin']);
	$Page->startBody();
	$FOGCore->getClass('ProcessLogin')->mobileLoginForm();
	$Page->endBody();
	$Page->render();
}
$FOGPageManager = new FOGPageManager();
$content = $FOGPageManager->render();
$sectionTitle = $FOGPageManager->getFOGPageName();
$pageTitle = $FOGPageManager->getFOGPageTitle();
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: deny');
header('Cache-Control: no-cache');
$Page->setTitle($pageTitle);
$Page->setSecTitle($sectionTitle);
$Page->startBody();
print $content;
$Page->endBody();
$Page->render();
