<?php
if (session_id() === '') {
    session_start();
    session_cache_limiter('no-cache');
    $domain = isset($_SERVER['SERVER_NAME']);
    $https = isset($_SERVER['HTTPS']);
    session_set_cookie_params(0,'/',$domain,$https,true);
}
require_once ('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
$Page = $FOGCore->getClass('Page');
if ($_REQUEST['node'] == 'logout' || (!in_array($_REQUEST['sub'],array('configure','authorize','loginInfo')) && !in_array($_REQUEST['node'],array('schemaupdater','client')) && !$currentUser->isValid())) {
    $currentUser->logout();
    $HookManager->processEvent('LOGOUT', array('user'=>&$currentUser));
    $Page->startBody();
    $FOGCore->getClass('ProcessLogin')->mobileLoginForm();
    $Page->endBody();
    $Page->render();
} else {
    $content = $FOGPageManager->render();
    $Page->setTitle($FOGPageManager->getFOGPageTitle());
    $Page->setSecTitle($FOGPageManager->getFOGPageName());
    $Page->startBody();
    echo $content;
    $Page->endBody();
    $Page->render();
}
