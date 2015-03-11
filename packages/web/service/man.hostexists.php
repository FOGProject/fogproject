<?php
require_once('../commons/base.inc.php');
try
{
	// Get MAC Addresses from Host.
	$MACs = FOGCore::parseMacList(base64_decode($_REQUEST['mac']));
	if (!$MACs) throw new Exception($foglang['InvalidMAC']);
	// Check if host already Exists
	$Host = $FOGCore->getClass('HostManager')->getHostByMacAddresses($MACs);
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
