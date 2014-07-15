<?php
// Require FOG Base
require('../commons/base.inc.php');
try
{
	$MACAddress = new MACAddress($_REQUEST['wakeonlan']);
	if ($MACAddress->isValid())
	{
		$wol = new WakeOnLan($MACAddress->getMACWithColon());
		$wol->send();
	}
	else
<<<<<<< HEAD
		throw new Exception(_('Invalid MAC Address!'));
=======
		throw new Exception($foglang['InvalidMAC']);
>>>>>>> 5e6f2ff5445db9f6ab2678bfad76acfcacc85157
}
catch (Exception $e){print $e->getMessage();}
