<?php
require('../commons/base.inc.php');
$Datatosend = "#!start\n";
foreach ($FOGCore->getClass('UserCleanupManager')->find() AS $User)
	$Datatosend .= base64_encode($User->get('name'))."\n";
$Datatosend .= "#!end\n";
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
