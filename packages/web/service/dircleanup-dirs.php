<?php
require('../commons/base.inc.php');
$index = 0;
foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
{
	$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#dir$index=".base64_encode($Dir->get('path'))."\n" : base64_encode($Dir->get('path')))."\n";
	$index++;
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
