<?php
$dev = ($_REQUEST['dev'] ? trim(basename(mb_convert_encoding($_REQUEST['dev'],'UTF-8'))) : (defined('NFS_ETH_MONITOR') ? NFS_ETH_MONITOR : 'eth0'));
header('Content-Type: text/event-stream');
$rx = @file_get_contents("/sys/class/net/$dev/statistics/rx_bytes");
$tx = @file_get_contents("/sys/class/net/$dev/statistics/tx_bytes");
$Data = array('dev' => $dev,'rx' => $rx,'tx' => $tx);
echo json_encode($Data);
exit;
