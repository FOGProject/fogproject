<?php
require('../commons/base.inc.php');
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
{
	foreach($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		$Usertosend[] = $User->get('name');
	$Datatosend = "#!ok\n#users=".implode('|',$Usertosend);
}
else
{
	$Datatosend = "#!start\n";
	foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
		$Datatosend .= base64_encode($User->get('name'))."\n";
	$Datatosend .= "#!end\n";
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
