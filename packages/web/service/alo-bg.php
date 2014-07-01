<?php
require('../commons/base.inc.php');
try
{
	// Just send the image.  It will probably fail as it was originally written for XP!
	throw new Exception(base64_encode($FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE')));
}
catch(Exception $e)
{
	print $e->getMessage();
}
