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
		if ($_REQUEST['newService'] && !$Host->get('pub_key'))
			throw new Exception('#!ihc');
	}
	$index = 0;
	foreach($FOGCore->getClass('DirCleanerManager')->find() AS $Dir)
	{
		$Datatosend .= ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? ($index == 0 ? "#!ok\n" : '')."#dir$index=".base64_encode($Dir->get('path'))."\n" : base64_encode($Dir->get('path')))."\n";
		$index++;
	}
//	if ($_REQUEST['newService'])
//		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
//	else
		print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
