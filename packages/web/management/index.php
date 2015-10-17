<?php
require_once ('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
$Page = $FOGCore->getClass('Page');
if ($_REQUEST['node'] == 'logout' || (!in_array($_REQUEST['sub'],array('configure','authorize','loginInfo')) && !in_array($_REQUEST['node'],array('schemaupdater','client')) && !$currentUser->isValid())) {
    $HookManager->processEvent('LOGOUT', array('user'=>&$currentUser));
    $currentUser->logout();
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
