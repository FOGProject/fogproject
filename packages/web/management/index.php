<?php
require_once ('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$currentUser = ($_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : $FOGCore->getClass('User'));
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
$Page = $FOGCore->getClass('Page');
if (!in_array($_REQUEST['node'],array('schemaupdater','client')) && !in_array($_REQUEST['sub'],array('configure','authorize')) && ($node == 'logout' || !$currentUser->isValid() || !$currentUser->isLoggedIn())) {
    $HookManager->processEvent('LOGOUT', array('user'=>&$currentUser));
    $currentUser->logout();
    unset($currentUser, $_SESSION['FOG_USERNAME'], $_SESSION['FOG_USER'], $_SESSION['AllowAJAXTasks']);
    $Page->setTitle($foglang['Login']);
    $Page->setSecTitle($foglang['ManagementLogin']);
    $Page->startBody();
    $FOGCore->getClass('ProcessLogin')->mainLoginForm();
    $Page->endBody();
    $Page->render();
} else {
    $_SESSION['AllowAJAXTasks'] = true;
    $content = $FOGPageManager->render();
    if ($FOGCore->ajax) {
        echo $content;
        exit;
    }
    $Page->setTitle($FOGPageManager->getFOGPageTitle());
    $Page->setSecTitle($FOGPageManager->getFOGPageName());
    $Page->startBody();
    echo $content;
    $Page->endBody();
    $Page->render();
}
