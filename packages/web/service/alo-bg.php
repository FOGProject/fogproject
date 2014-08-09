<?php
require('../commons/base.inc.php');
try
{
	// Just send the image.  It will probably fail as it was originally written for XP!
	throw new Exception($_REQUEST['newService'] ? $FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE') : base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')));
}
catch(Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
