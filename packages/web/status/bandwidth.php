<?php
// Require FOG Base
require_once('../commons/base.inc.php');
function getData($interface)
{
	$fp = fopen(PROCNETDEV, "r");
	while ($line = fgets($fp, 256))
	{
		$temp = split(":", trim($line));
		if ($temp[0] == $interface) 
		{
			$line = preg_split("/[\s]+/", trim($temp[1]));
			fclose($fp);
			return array($line[0], $line[8]);
		}
	}
	fclose($fp);
}
define("PROCNETDEV", "/proc/net/dev");
define("SLEEPSEC", 1);
$Data = array();
if ($_REQUEST['id'] && is_numeric($_REQUEST['id']) && $_REQUEST['id'] > 0)
{
	$StorageNode = new StorageNode($_REQUEST['id']);
	$URL = sprintf('http://%s/%s?dev=%s',rtrim($StorageNode->get('ip'),'/'),ltrim($FOGCore->getSetting('FOG_NFS_BANDWIDTHPATH'),'/'),$StorageNode->get('interface'));
	if ($fetchedData = $FOGCore->fetchURL($URL))
		print $fetchedData;
}
if ($_REQUEST['dev'])
{
	if (!file_exists(PROCNETDEV) || !is_readable(PROCNETDEV))
		$Data['error'] = (file_exists(PROCNETDEV) ? PROCNETDEV . ' is not readable' : PROCNETDEV . ' doesnt not exist');
	else
	{
		$dev = ($_REQUEST['dev'] ? trim($_REQUEST['dev']) : (defined('NFS_ETH_MONITOR') ? NFS_ETH_MONITOR : 'eth0'));
		list($intLastRx, $intLastTx) = getData($dev);
		sleep(SLEEPSEC);
		list($intCurRx, $intCurTx) = getData($dev);
		if (is_numeric( $intCurRx ) && is_numeric($intLastRx))
		{
			// Calculate speed in Megabits per second
			$rx = ceil(($intCurRx - $intLastRx) / SLEEPSEC / 1000);
			$tx = ceil(($intCurTx - $intLastTx) / SLEEPSEC / 1000);
			// Sometimes we get negative numbers - no idea why
			$Data = array(
				'rx' => ($rx > 0 ? $rx : 0),
				'tx' => ($tx > 0 ? $tx : 0)
			);
		}
		else
			$Data = array('rx' => 0, 'tx' => 0);
	}
	if ($dev) $Data['dev'] = $dev;
	return json_encode($Data);
}
