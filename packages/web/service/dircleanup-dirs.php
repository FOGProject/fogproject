<?php
require_once('../commons/base.inc.php');
try
{
	if ($_REQUEST['newService'])
	{
		$HostManager = new HostManager();
		$MACs = FOGCore::parseMacList($_REQUEST['mac']);
		if (!$MACs)
			throw new Exception('#!im');
		// Get the Host
		$Host = $HostManager->getHostByMacAddresses($MACs);
		if (!$Host || !$Host->isValid() || $Host->get('pending'))
			throw new Exception('#!ih');
	}
	$Datatosend = '';
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
		$Datatosend .= base64_encode($Dir->get('path'))."\n";
	print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
