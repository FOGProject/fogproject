<?php
require('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$FOGPageManager = FOGCore::getClass('FOGPageManager');
FOGCore::getClass('ProcessLogin')->processMainLogin();
require('../commons/text.php');
$Page = FOGCore::getClass('Page');
if (!in_array($_REQUEST['node'],array('schema','client')) && ($node == 'logout' || !$currentUser->isValid())) {
    $currentUser->logout();
    $Page->setTitle($foglang['Login']);
    $Page->setSecTitle($foglang['ManagementLogin']);
    $Page->startBody();
    FOGCore::getClass('ProcessLogin')->mobileLoginForm();
    $Page->endBody();
    $Page->render();
} else {
    $Page->setTitle($FOGPageManager->getFOGPageTitle());
    $Page->setSecTitle($FOGPageManager->getFOGPageName());
    $Page->startBody();
    $FOGPageManager->render();
    $Page->endBody();
    $Page->render();
}
