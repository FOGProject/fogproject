<?php
require_once('../commons/base.inc.php');
try
{
	$_REQUEST['newService'] ? $FOGCore->sendData($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')) : $FOGCore->sendData(base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')));
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
