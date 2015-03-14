<?php
require_once('../commons/base.inc.php');
try
{
	if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
		$FOGCore->sendData($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE'));
	else
		$FOGCore->sendData(base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')));
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
