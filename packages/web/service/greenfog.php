<?php
require_once('../commons/base.inc.php');
$index = 0;
foreach($FOGCore->getClass('GreenFogManager')->find() AS $gf)
{
	$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#task$index=".$gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action') : base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action')))."\n";
	$index++;
}
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
