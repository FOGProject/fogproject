<?php
$dev = ($_REQUEST['dev'] ? trim($_REQUEST['dev']) : (defined('NFS_ETH_MONITOR') ? NFS_ETH_MONITOR : 'eth0'));
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: deny');
header('Cache-Control: no-cache');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
$rx = @file_get_contents("/sys/class/net/$dev/statistics/rx_bytes");
$tx = @file_get_contents("/sys/class/net/$dev/statistics/tx_bytes");
$Data = array('dev' => $dev,'rx' => $rx,'tx' => $tx);
echo json_encode($Data);
