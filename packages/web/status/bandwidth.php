<?php
function getData($interface)
{
	$rx1 = @file_get_contents("/sys/class/net/$interface/statistics/rx_bytes");
	$tx1 = @file_get_contents("/sys/class/net/$interface/statistics/tx_bytes");
	sleep(1);
	$rx2 = @file_get_contents("/sys/class/net/$interface/statistics/rx_bytes");
	$tx2 = @file_get_contents("/sys/class/net/$interface/statistics/tx_bytes");
	return array(round(($rx2-$rx1)/1024,2),round(($tx2-$tx1)/1024,2));
}
$dev = ($_REQUEST['dev'] ? trim($_REQUEST['dev']) : (defined('NFS_ETH_MONITOR') ? NFS_ETH_MONITOR : 'eth0'));
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
list($rx,$tx) = getData($dev);
$Data = array('dev' => $dev,'rx' => $rx,'tx' => $tx);
print json_encode($Data);
