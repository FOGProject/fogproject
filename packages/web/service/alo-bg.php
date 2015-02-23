<?php
require_once('../commons/base.inc.php');
try
{
	$Datatosend = base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE'));
	print $Datatosend;
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
