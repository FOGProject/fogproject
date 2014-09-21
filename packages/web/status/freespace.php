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
			$URL = 'http://'.$StorageNode->get('ip').SPACE_DEFAULT_WEBROOT.'status/freespace.php?idnew='.$StorageNode->get('id');
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
				throw new Exception('Failed to connect to ' . $Node['ngmMemberName']);
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
		$t = shell_exec("df ".SPACE_DEFAULT_STORAGE."| grep -vE \"^Filesystem|shm\"");
	else
		$t = shell_exec("df ".$StorageNode->get('path')."| grep -vE \"^Filesystem|shm\"");
	if ($StorageNode->get('isGraphEnabled'))
	{
		$l = explode("\n", $t);
		$hdtotal = 0;
		$hdused = 0;
		foreach ($l as $n)
		{
			if (preg_match("/(\d+) +(\d+) +(\d+) +\d+%/", $n, $matches))
			{
				if (is_numeric($matches[3]))
					$hdtotal += $matches[3];
			}
			if ( is_numeric( $matches[2] ) )
				$hdused += $matches[2];
		}
		$freegb=$hdtotal;
		$usedgb=$hdused;
		$Data = array('free' => $freegb, 'used' => $usedgb);
	}
}
print json_encode($Data);
