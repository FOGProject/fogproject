<?php
require '../commons/base.inc.php';
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
$dev = trim($_REQUEST['dev'] ? basename($_REQUEST['dev']) : 'eth0');
$dirints = array_diff(scandir('/sys/class/net'),array('..','.'));
array_walk($dirints,function(&$iface,&$index) use (&$interfaces) {
    if (trim(file_get_contents(sprintf('/sys/class/net/%s/operstate',$iface))) !== 'up') return;
    $interfaces[] = $iface;
});
$interface = preg_grep("#$dev#",(array)$interfaces);
$dev = array_shift($interface);
if (empty($dev)) $dev = FOGCore::getMasterInterface($FOGCore->resolveHostname($_SERVER['SERVER_ADDR']));
$rx_data = trim(file_get_contents("/sys/class/net/$dev/statistics/rx_bytes"));
$tx_data = trim(file_get_contents("/sys/class/net/$dev/statistics/tx_bytes"));
$rx = is_numeric($rx_data) && $rx_data > 0 ? $rx_data * 8 / 1024 : 0;
$tx = is_numeric($tx_data) && $tx_data > 0 ? $tx_data * 8 / 1024 : 0;
$ret = array('dev'=>$dev,'rx'=>$rx,'tx'=>$tx);
if (empty($dev)) $ret = array('dev'=>'Unknown','rx'=>0,'tx'=>0);
die(json_encode($ret));
