<?php
function getInterface() {
    $dev = trim($_REQUEST['dev'] ? basename(htmlentities($_REQUEST['dev'],ENT_QUOTES,'utf-8')) : 'eth0');
    $sys_interfaces = array_diff(scandir('/sys/class/net'), array('..', '.'));
    $interfaces = array();
    foreach ($sys_interfaces AS &$iface) {
        if (trim(file_get_contents(sprintf('/sys/class/net/%s/operstate',$iface))) !== 'up') continue;
        array_push($interfaces,$iface);
        unset($iface);
    }
    unset($sys_interfaces);
    $interface = preg_grep("#$dev#",(array)$interfaces);
    $dev = @array_shift($interface);
    if (empty($dev)) return array('dev'=>'unknown','rx'=>0,'tx'=>0);
    $rx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/rx_bytes',$dev)));
    $tx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/tx_bytes',$dev)));
    return array('dev'=>$dev,'rx'=>$rx,'tx'=>$tx);
}
header('Content-Type: text/event-stream');
echo json_encode(getInterface());
exit;
