<<<<<<< HEAD
<?php
require('../commons/base.inc.php');
try
{
	if (isset($_REQUEST['action']))
	{
		if ($_REQUEST['action'] == 'ask' && isset($_REQUEST['file']) && $_REQUEST['newService'])
		{
			print "#!ok"."\n";
			foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('name' => base64_decode($_REQUEST['file']))) AS $ClientUpdate) {
				print "md5=".$ClientUpdate->get('md5');
			}
		}
		else if ($_REQUEST['action'] == 'ask' && isset($_REQUEST['file']))
		{
			foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('name' => base64_decode($_REQUEST['file']))) AS $ClientUpdate)
				print $ClientUpdate->get('md5');
		}
		else if ($_REQUEST['action'] == 'get' && isset($_REQUEST['file']))
		{
			foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('name' => base64_decode($_REQUEST['file']))) AS $ClientUpdate)
			{
				header("Cache-control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Description: File Transfer");
				header("ContentType: application/octet-stream");
				header("Content-Disposition: attachment; filename=".basename($ClientUpdate->get('name')));
				print $ClientUpdate->get('file');
			}
		}
		else if ( $_REQUEST['action'] == 'list'  && $_REQUEST['newService'])
		{
			print "#!ok"."\n";
			$updateIndex = 0;
			foreach($FOGCore->getClass('ClientUpdaterManager')->find() AS $ClientUpdate) {
				print "update{$updateIndex}=".base64_encode($ClientUpdate->get('name'))."\n";
				$updateIndex++;
			}
			
		} else if ($_REQUEST['action'] == 'list') {
			foreach($FOGCore->getClass('ClientUpdaterManager')->find() AS $ClientUpdate)
				print base64_encode($ClientUpdate->get('name'))."\n";	
		}
		else
			throw new Exception('#!er');		
	}
	else
		throw new Exception('#!er');
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
<?php
require('../commons/base.inc.php');
try
{
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
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
>>>>>>> dev-branch
