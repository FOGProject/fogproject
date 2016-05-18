<?php
$path = escapeshellarg(base64_decode($_REQUEST['path']));
$hdtotal = 0;
$hdused = 0;
$freeArray = explode("\n",shell_exec("df -B 1 $path | grep -vE '^Filesystem|shm'"));
@array_walk($freeArray,function(&$n,&$index) use (&$hdtotal,&$hdused) {
    if (!preg_match('/(\d+) +(\d+) +(\d+) +\d+%/',$n,$matches)) return;
    $hdtotal += $matches[3];
    $hdused += $matches[2];
    unset($n);
});
$Data = array('free' => $hdtotal, 'used' => $hdused);
ob_start();
header('Content-Type: text/event-stream');
header('Connection: close');
echo json_encode($Data);
flush();
ob_flush();
ob_end_flush();
exit;
