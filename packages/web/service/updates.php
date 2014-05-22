<?php
require('../commons/base.inc.php');
try
{
	if (isset($_REQUEST['action']))
	{
		if ($_REQUEST['action'] == 'ask' && isset($_REQUEST['file']))
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
		else if ( $_REQUEST['action'] == 'list' )
		{
			foreach($FOGCore->getClass('ClientUpdaterManager')->find(array('type' => 'bin')) AS $ClientUpdate)
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
