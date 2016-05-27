<?php
require('../commons/base.inc.php');
$dev = trim($_REQUEST['dev'] ? basename($_REQUEST['dev']) : 'eth0');
$dirints = array_diff(scandir('/sys/class/net'),array('..','.'));
@array_walk($dirints,function(&$iface,&$index) use (&$interfaces) {
    if (trim(file_get_contents(sprintf('/sys/class/net/%s/operstate',$iface))) !== 'up') return;
    $interfaces[] = $iface;
});
$interface = preg_grep("#$dev#",(array)$interfaces);
$dev = @array_shift($interface);
if (empty($dev)) $dev = FOGCore::getMasterInterface($FOGCore->resolveHostname($_SERVER['SERVER_ADDR']));
if (empty($dev)) $ret = array('dev'=>'Unknown','rx'=>0,'tx'=>0);
else {
    $rx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/rx_bytes',$dev)));
    $tx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/tx_bytes',$dev)));
    $ret = array('dev'=>$dev,'rx'=>$rx,'tx'=>$tx);
}
ob_start();
header('Content-Type: text/event-stream');
header('Connection: close');
echo json_encode($ret);
flush();
ob_flush();
ob_end_flush();
exit;
