<?php
require_once ('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$currentUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : $FOGCore->getClass('User'));
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$Page = $FOGCore->getClass('Page');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
if (!in_array($node, array('schemaupdater', 'client')) && !in_array($sub, array('configure', 'authorize')) && ($node == 'logout' || $currentUser == null || !method_exists($currentUser, 'isLoggedIn') || !$currentUser->isLoggedIn())) {
    $HookManager->processEvent('LOGOUT', array('user'=>&$currentUser));
    if (method_exists($currentUser, 'logout')) $currentUser->logout();
    unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER'], $_SESSION['AllowAJAXTasks']);
    // Show login form
    $Page->setTitle($foglang['Login']);
    $Page->setSecTitle($foglang['ManagementLogin']);
    $Page->startBody();
    $FOGCore->getClass('ProcessLogin')->mobileLoginForm();
    $Page->endBody();
    $Page->render();
} else {
    $content = $FOGPageManager->render();
    $sectionTitle = $FOGPageManager->getFOGPageName();
    $pageTitle = $FOGPageManager->getFOGPageTitle();
    $Page->setTitle($pageTitle);
    $Page->setSecTitle($sectionTitle);
    $Page->startBody();
    echo $content;
    $Page->endBody();
    $Page->render();
}
