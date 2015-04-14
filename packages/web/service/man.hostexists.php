<?php
require_once('../commons/base.inc.php');
try
{
	// Get all MACs
	$MACs = $FOGCore->getHostItem(true,true,true,true);
	// Check if host already Exists
	$Host = $FOGCore->getClass('HostManager')->getHostByMacAddresses($MACs);
	if ($Host && $Host->isValid())
		throw new Exception(sprintf('%s: %s',_('This Machine is already registered as'),$Host->get('name')));
	// Host doesn't exist so can be created.
	print "#!ok";
}
catch (Exception $e)
{
	print $e->getMessage();
}
