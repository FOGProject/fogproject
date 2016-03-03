<?php
function getInterface($type = 'tx') {
    $output = array();
    exec("/sbin/ip addr | awk -F'[: /]+' '/,?UP,?/ {print $2}'",$interfaces,$retVal);
    if (!count($interfaces)) exec("/sbin/ifconfig -a | awk -F'[: /]+' '/HWaddr/ {print $1}'",$interfaces,$retVal);
    if (!count($interfaces)) die ('No interfaces found');
    $dev = trim(($_REQUEST['dev'] ? basename(htmlentities($_REQUEST['dev'],ENT_QUOTES,'utf-8')) : (defined('NFS_ETH_MONITOR') ? NFS_ETH_MONITOR : 'eth0')));
    $interface = preg_grep("#$dev#",(array)$interfaces);
    $dev = @array_shift($interface);
    if ($type === true) return $dev;
    $handle = fopen(sprintf('/sys/class/net/%s/statistics/%s_bytes',$dev,$type),'rb');
    if ($handle === false) return 0;
    return fgets($handle);
}
header('Content-Type: text/event-stream');
$dev = getInterface(true);
$rx = getInterface('rx');
$tx = getInterface('tx');
$Data = array('dev' => $dev,'rx' => $rx,'tx' => $tx);
echo json_encode($Data);
exit;
