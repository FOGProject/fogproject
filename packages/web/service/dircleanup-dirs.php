<?php
require('../commons/base.inc.php');
if ($FOGCore->getSetting('FOG_NEW_CLIENT') || $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	$Datatosend = "#!ok\n";
else
	$Datatosend = '';
foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
	$Datatosend .= ($_REQUEST['newService'] ? $Dir->get('path') : base64_encode($Dir->get('path')))."\n";
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
