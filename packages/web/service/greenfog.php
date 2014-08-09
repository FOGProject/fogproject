<?php
require_once('../commons/base.inc.php');
try
{
	$GF = $FOGCore->getClass('GreenFogManager')->find();
	foreach($GF AS $gf)
		$Datatosend = ($_REQUEST['newService'] ? $gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action') : base64_encode($gf->get('hour').'@'.$gf->get('min').'@'.$gf->get('action')))."\n";
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
