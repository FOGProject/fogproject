<?php
require((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
// Allow AJAX check
if (!$_SESSION['AllowAJAXTasks'])
	die('FOG Session Invalid');
// Variables
$data = array(
	'domainname' => $FOGCore->getSetting('FOG_AD_DEFAULT_DOMAINNAME'),
	'ou' => $FOGCore->getSetting('FOG_AD_DEFAULT_OU'),
	'domainuser' => $FOGCore->getSetting('FOG_AD_DEFAULT_USER'), 
	'domainpass' => $FOGCore->getSetting('FOG_AD_DEFAULT_PASSWORD'),
);
if ($FOGCore->isAJAXRequest())
	print json_encode($data);
