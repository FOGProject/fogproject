<?php
header('Content-Type: text/event-stream');
header('Connection: close');
require('../commons/base.inc.php');
$dev = trim($_REQUEST['dev'] ? basename($_REQUEST['dev']) : 'eth0');
$dirints = array_diff(scandir('/sys/class/net'),array('..','.'));
array_walk($dirints,function(&$iface,&$index) use (&$interfaces) {
    if (trim(file_get_contents(sprintf('/sys/class/net/%s/operstate',$iface))) !== 'up') return;
    $interfaces[] = $iface;
});
$interface = preg_grep("#$dev#",(array)$interfaces);
$dev = array_shift($interface);
if (empty($dev)) $dev = FOGCore::getMasterInterface($FOGCore->resolveHostname($_SERVER['SERVER_ADDR']));
$rx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/rx_bytes',$dev))) * 8 / 1024;
$tx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/tx_bytes',$dev))) * 8 / 1024;
$ret = array('dev'=>$dev,'rx'=>$rx,'tx'=>$tx);
if (empty($dev)) $ret = array('dev'=>'Unknown','rx'=>0,'tx'=>0);
echo json_encode($ret);
exit;
