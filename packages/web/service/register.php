<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	if (!$_REQUEST['newService'] && $_REQUEST['version'] != 2)
		throw new Exception('#!er:Invalid Version Number, please update this module.');
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs) throw new Exception('#!im');
	// The total number of pending macs that can be used.
	$maxPending = $FOGCore->getSetting('FOG_QUICKREG_MAX_PENDING_MACS');
	// The ignore list.  Comma Separated.
	$ignoreList = explode(',', $FOGCore->getSetting('FOG_QUICKREG_PENDING_MAC_FILTER'));
	// Get the actual host (if it is registered)
	$Host = $HostManager->getHostByMacAddresses($MACs);
	$HostPend = false;
	if($_REQUEST['newService'] && (!$Host || !$Host->isValid()) && !$Host->get('pending'))
	{
		if (!$_REQUEST['hostname'] || HostManager::isHostnameSafe($_REQUEST['hostname']))
			throw new Exception('#!ih');
		$HostPend = true;
		$Host = new Host(array(
			'name' => $_REQUEST['hostname'],
			'description' => 'Pending Registration created by FOG_CLIENT',
			'pending' => 1,
			'pub_key' => $FOGCore->certDecrypt($_REQUEST['pub_key']),
		));
		foreach($FOGCore->getClass('ModuleManager')->find() AS $Module)
			$ModuleIDs[] = $Module->get('id');
		if ($Host->save())
		{
			$Host->addModule($ModuleIDs);
			$PriMAC = ((preg_match('#|#i',$_REQUEST['mac']) ? explode('|',$_REQUEST['mac']) : $_REQUEST['mac']));
			if (is_array($PriMAC))
				$PriMAC = $PriMAC[0];
			if($Host->save())
				$Host->addPriMAC($PriMAC);
		}
	}
	else if (!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		throw new Exception('#!ihc');
	// Check if count is okay.
	if (count($MACs) > $maxPending + 1)
		throw new Exception('#!er:Too many MACs');
	// Cycle the MACs
	foreach($MACs AS $MAC)
	{
		$MAC = strtolower($MAC);
		$mac1[] = strtolower($Host->get('mac'));
		// Get all the additional MACs
		foreach((array)$Host->get('additionalMACs') AS $mac)
			$mac1[] = $mac && $mac->isValid() ? strtolower($mac) : '';
		// Get all the pending MACs
		foreach((array)$Host->get('pendingMACs') AS $mac)
			$mac1[] = $mac && $mac->isValid() ? strtolower($mac) : '';
		// Get all the mac's in the list to ensure we don't register a pending mac to another host.
		// Particularly where the mac exists on a host but is set to be ignored.
		foreach((array)$FOGCore->getClass('MACAddressAssociationManager')->find() AS $MAC)
		{
			$MAC = strtolower($MAC);
			if (!in_array($MAC,(array)$mac1))
				$mac1[] = $MAC;
		}
		// Cycle the ignorelist if there is anything.
		if ($ignoreList)
		{
			foreach((array)$ignoreList AS $mac)
				$mac1[] = strtolower($mac);
		}
		$mac1 = array_unique($mac1);
		if (!in_array($MAC,(array)$mac1))
		{
			if ($Host->addPendMAC($MAC))
				$Datatosend = "#!ok\n";
			else
				throw new Exception('#!er: Error adding MAC');
		}
		else
			($HostPend && $mac1[0] == $Host->get('mac') ? $Datatosend.="#!ok\n" : $Datatosend .= "#!ig\n");
	}
	if ($_REQUEST['newService'])
		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
	else
		print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
