<<<<<<< HEAD
<?php
require('../commons/base.inc.php');
try
{
	// Send the Dir's to the client.
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
		print base64_encode($Dir->get('path'))."\n";
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
<?php
require('../commons/base.inc.php');
$index = 0;
foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
{
	$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#dir_$index=".base64_encode($Dir->get('path'))."\n" : base64_encode($Dir->get('path')))."\n";
	$index++;
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
>>>>>>> dev-branch
