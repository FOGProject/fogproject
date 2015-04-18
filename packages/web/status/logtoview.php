<?php
function read_file($file, $lines = 20, $max_chunk_size = 4096) {
	$data = file_get_contents($file);
	$data = explode("\n",$data);
	$data = array_reverse($data);
	$cnt = 0;
	foreach($data AS $line)
	{
		$text[] = $line;
		if ($cnt++ == $lines) break;
	}
	return implode("\n",array_reverse($text));
}
$vals = read_file($_REQUEST['file'],$_REQUEST['lines'] ? $_REQUEST['lines'] : 20);
print json_encode($vals);
