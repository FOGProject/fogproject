<?php
$path = base64_decode($_REQUEST['path']);
$t = shell_exec("df -B 1 $path | grep -vE '^Filesystem|shm'");
$l = explode("\n",$t);
$hdtotal = 0;
$hdused = 0;
foreach($l AS $n)
{
	if (preg_match("/(\d+) +(\d+) +(\d+) +\d+%/", $n, $matches))
	{
		if (is_numeric($matches[3]))
			$hdtotal += $matches[3];
		if (is_numeric($matches[2]))
			$hdused += $matches[2];
	}
}
$free = $hdtotal;
$used = $hdused;
$Data = array('free' => $free, 'used' => $used);
print json_encode($Data);
