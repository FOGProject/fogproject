<?php
$dev = trim($_REQUEST['dev'] ? basename($_REQUEST['dev']) : 'eth0');
$interfaces = array_map(function(&$iface) {
    if (trim(file_get_contents(sprintf('/sys/class/net/%s/operstate',$iface))) !== 'up') return;
    return $iface;
},array_diff(scandir('/sys/class/net'),array('..','.')));
$interface = preg_grep("#$dev#",(array)$interfaces);
$def = @array_shift($interface);
if (empty($dev)) {
    echo json_encode(array('dev'=>'unknown','rx'=>0,'tx'=>0));
    exit;
}
$rx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/rx_bytes',$dev)));
$tx = trim(file_get_contents(sprintf('/sys/class/net/%s/statistics/tx_bytes',$dev)));
echo json_encode(array('dev'=>$dev,'rx'=>$rx,'tx'=>$tx));
exit;
