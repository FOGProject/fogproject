<?php
require('../commons/base.inc.php');
if (isset($_SESSION['delitems']) && !in_array($_REQUEST['sub'], array('deletemulti', 'deleteconf'))) unset($_SESSION['delitems']);
$currentUser = $_SESSION['FOG_USER'] ? unserialize($_SESSION['FOG_USER']) : $FOGCore->getClass('User');
if ($currentUser->isValid()) $currentUser->isLoggedIn();
$FOGPageManager = $FOGCore->getClass('FOGPageManager');
$FOGCore->getClass('ProcessLogin')->processMainLogin();
$Page = $FOGCore->getClass('Page');
if (!in_array($_REQUEST['node'],array('schemaupdater','client')) && !in_array($_REQUEST['sub'],array('configure','authorize')) && ($node == 'logout' || !$currentUser->isValid())) $currentUser->logout();
$Page->render();
