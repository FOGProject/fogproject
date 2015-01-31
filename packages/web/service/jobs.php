<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = FOGCore::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	// Find out about tasks in queue.
	$Task = $Host->get('task');
	// If there is no task, or it's of snapin deploy type, don't reboot.
	if (!$Task->isValid() || ($Task->get('typeID') == 12 || $Task->get('typeID') == 13))
		throw new Exception('#!nj');
	else
		throw new Exception('#!ok');
}
catch (Exception $e)
{
	print $e->getMessage();
}
