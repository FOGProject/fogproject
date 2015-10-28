<?php
require_once ('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$currentUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : $FOGCore->getClass('User'));
$currentUser->isLoggedIn();
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$Page = $FOGCore->getClass('Page');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
if ($_REQUEST['node'] == 'logout' || (!in_array($_REQUEST['sub'],array('configure','authorize','loginInfo')) && !in_array($_REQUEST['node'],array('schemaupdater','client')) && !$currentUser->isValid())) {
    $HookManager->processEvent('LOGOUT', array('user'=>&$currentUser));
    $currentUser->logout();
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
