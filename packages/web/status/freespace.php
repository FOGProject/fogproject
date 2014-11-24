<?php
require('../commons/base.inc.php');
define('SPACE_DEFAULT_WEBROOT',$FOGCore->getSetting('FOG_WEB_ROOT'));
if ($_REQUEST['id'])
{
	$StorageNode = new StorageNode($_REQUEST['id']);
	if($StorageNode->get('isGraphEnabled'))
	{
		try
		{
			$webroot = $FOGCore->getSetting('FOG_WEB_ROOT') ? '/'.trim($FOGCore->getSetting('FOG_WEB_ROOT'),'/').'/' : '/';
			$URL = 'http://'.$StorageNode->get('ip').$webroot.'status/freespace.php?idnew='.$StorageNode->get('id');
			if ($Response = $FOGCore->fetchURL($URL))
			{
				// Backwards compatibility for old versions of FOG
				if (preg_match('#(.*)@(.*)#', $Response, $match))
					$Data = array('free' => $match[1], 'used' => $match[2]);
				else
				{
					$Response = json_decode($Response, true);
					$Data = array('free' => $Response['free'], 'used' => $Response['used']);
				}
			}
			else
				throw new Exception('Failed to connect to ' . $StorageNode->get('name'));
		}
		catch (Exception $e)
		{
			$Data['error'] = $e->getMessage();
		}
	}
}
else
{
	$StorageNode = ($_REQUEST['idnew'] ? new StorageNode($_REQUEST['idnew']) : null);
	if (!$StorageNode || !$StorageNode->isValid())
	{
		$free = disk_free_space(SPACE_DEFAULT_STORAGE);
		$used = disk_total_space(SPACE_DEFAULT_STORAGE) - $free;
	}
	else
	{
		$free = disk_free_space($StorageNode->get('path'));
		$used = disk_total_space($StorageNode->get('path')) - $free;
	}
	if ($StorageNode->get('isGraphEnabled'))
	{
		$freegb=$free;
		$usedgb=$used;
		$Data = array('free' => $free, 'used' => $usedgb);
	}
}
print json_encode($Data);
