<?php
require('../commons/base.inc.php');
try
{
	// Get MAC Address from Host.
	$ifconfig = base64_decode($_REQUEST['mac']);
	$arIfconfig = explode('HWaddr',$ifconfig);
	$mac = strtolower(trim($arIfconfig[1]));
	// Check if MAC Address is valid
	$MACAddress = new MACAddress($mac);
	if (!$MACAddress->isvalid()) throw new Exception($foglang['InvalidMAC']);
	// Check if host already Exists
	$Host = $FOGCore->getClass('HostManager')->getHostByMacAddresses($mac);
	if ($Host)
	{
		if ($Host->isValid())
			throw new Exception(sprintf('%s: %s',_('This Machine is already registered as'),$Host->get('name')));
	}
	// Host doesn't exist so can be created.
	print "#!ok";
}
catch (Exception $e)
{
	print $e->getMessage();
}
