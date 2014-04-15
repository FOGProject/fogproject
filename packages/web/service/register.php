<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	if ($_REQUEST['version'] != 2)
		throw new Exception('#!er:Invalid Version Number, please update this module.');
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!er:Invalid MAC');
	// The total number of pending macs that can be used.
	$maxPending = $FOGCore->getSetting('FOG_QUICKREG_MAX_PENDING_MACS');
	// The ignore list.  Comma Separated.
	$ignoreList = explode(',', $FOGCore->getSetting('FOG_QUICKREG_PENDING_MAC_FILTER'));
	// Get the actual host (if it is registered)
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if(!$Host)
		throw new Exception('#!er:Invalid Host');
	// Check if count is okay.
	if (count($MACs) > $maxPending + 1)
		throw new Exception('#!er:Too many MACs');
	// Cycle the MACs
	foreach($MACs AS $MAC)
	{
		$MAC = strtolower($MAC);
		// Cycle the ignorelist if there is anything.
		if ($ignoreList)
		{
			foreach($ignoreList AS $mac)
				$mac1[] = strtolower($mac);
			if(in_array($MAC, $mac1))
				throw new Exception('#!ig');
		}
		// For comparing the additionalMACs registered if there are any.
		if ($Host->get('additionalMACs'))
		{
			foreach ($Host->get('additionalMACs') AS $mac)
				$mac2[] = strtolower($mac);
		}
		// Is this one already registered?
		if($MAC == strtolower($Host->get('mac')) || in_array($MAC, $mac2))
			throw new Exception('#!ig');
		if($HostManager->addMACToPendingForHost($Host,$MAC))
			print ('#!ok');
	}
}
catch (Exception $e)
{
	print $e->getMessage();
}
