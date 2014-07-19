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
		throw new Exception($foglang['InvalidMAC']);
}
catch (Exception $e){print $e->getMessage();}
