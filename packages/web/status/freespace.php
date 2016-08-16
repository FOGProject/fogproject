<?php
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
header('Connection: close');
$path = escapeshellarg(base64_decode($_REQUEST['path']));
$hdtotal = 0;
$hdused = 0;
$freeArray = explode("\n", shell_exec("df -B 1 $path | grep -vE '^Filesystem|shm'"));
array_walk(
    $freeArray,
    function(&$n, &$index) use (&$hdtotal,&$hdused) {
        if (!preg_match('/(\d+) +(\d+) +(\d+) +\d+%/', $n, $matches)) {
            return;
        }
        $hdtotal += $matches[3];
        $hdused += $matches[2];
        unset($n);
    }
);
$Data = array('free' => $hdtotal, 'used' => $hdused);
die(json_encode($Data));
