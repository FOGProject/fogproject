<?php
function read_file($file, $lines = 20, $max_chunk_size = 4096) {
	$linearr = array();
	$fp = fopen($file,'r');
	stream_set_blocking($fp,false);
	while (!feof($fp)) {
		$line = fgets($fp,$max_chunk_size);
		array_push($linearr,$line);
		if (count($linearr) > $lines) array_shift($linearr);
	}
	fclose($fp);
	return implode($linearr);
}
$vals = read_file($_REQUEST['file'],$_REQUEST['lines'] ? $_REQUEST['lines'] : 20);
print json_encode($vals);
