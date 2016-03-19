<?php
$path = escapeshellarg(base64_decode($_REQUEST['path']));
$hdtotal = 0;
$hdused = 0;
array_map(function(&$n) use (&$hdtotal,&$hdused) {
    if (!preg_match('/(\d+) +(\d+) +(\d+) +\d+%/',$n,$matches)) return;
    $hdtotal += $matches[3];
    $hdused += $matches[2];
    unset($n);
},explode("\n",shell_exec("df -B 1 $path | grep -vE '^Filesystem|shm'")));
$Data = array('free' => $hdtotal, 'used' => $hdused);
echo json_encode($Data);
exit;
