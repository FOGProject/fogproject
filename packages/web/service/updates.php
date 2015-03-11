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
		//if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		//	throw new Exception('#!ihc');
	}
	if (!in_array($_REQUEST['action'],array('ask','get','list')))
		throw new Exception('#!er: Needs action string of ask, get, or list');
	if (in_array($_REQUEST['action'],array('ask','get')) && !$_REQUEST['file'])
		throw new Exception('#!er: If action of ask or get, needs a file name in request');
	else if ($_REQUEST['action'] == 'ask')
	{
		foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('name' => base64_decode($_REQUEST['file']))) AS $ClientUpdate)
			$Datatosend = $FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? "#!ok\n#md5=".$ClientUpdate->get('md5') : $ClientUpdate->get('md5');
	}
	else if ($_REQUEST['action'] == 'get')
	{
		foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('name' => base64_decode($_REQUEST['file']))) AS $ClientUpdate)
		{
			if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'])
				$Datatosend = "#!ok\n#filename=".basename($ClientUpdate->get('name'))."\n#updatefile=".bin2hex($ClientUpdate->get('file'));
			else
			{
				header("Cache-control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Description: File Transfer");
				header("ContentType: application/octet-stream");
				header("Content-Disposition: attachment; filename=".basename($ClientUpdate->get('name')));
				$Datatosend = $ClientUpdate->get('file');
			}
		}
	}
	else if ( $_REQUEST['action'] == 'list')
	{
		$updateIndex = 0;
		foreach($FOGCore->getClass('ClientUpdaterManager')->find() AS $ClientUpdate)
		{
			$Data[] = $FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? "#update{$updateIndex}=".base64_encode($ClientUpdate->get('name'))."\n" : base64_encode($ClientUpdate->get('name'));
			$updateIndex++;
		}
	}
	if ($Data)
		$Datatosend = $FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? "#!ok\n".implode("\n",$Data) : implode("\n",$Data);
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
