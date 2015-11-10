<?php
$path = escapeshellarg(base64_decode($_REQUEST['path']));
$t = shell_exec("df -B 1 $path | grep -vE '^Filesystem|shm'");
$l = explode("\n",$t);
unset($t);
$hdtotal = 0;
$hdused = 0;
foreach($l AS $i => &$n) {
    if (!preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches)) continue;
    $hdtotal += intval($matches[3]);
    $hdused += intval($matches[2]);
    unset($n);
}
unset($l);
$Data = array('free' => $hdtotal, 'used' => $hdused);
echo json_encode($Data);
exit;
