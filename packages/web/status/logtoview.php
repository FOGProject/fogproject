<?php
function read_file($file, $lines = 20, $max_chunk_size = 4096) {
	$data = '';
	$fp = fopen($file,"r");
	$block = 4096;
	$max = filesize($file);
	for ($len = 0; $len < $max; $len += $block) {
		$seekSize = ($max - $len > $block) ? $block : $max - $len;
		fseek($fp, ($len + $seekSize) * -1, SEEK_END);
		$data = fread($fp, $seekSize) . $data;
		if (substr_count($data, "\n") >= $lines + 1) {
			if (substr($data, strlen($data)-1,1) !== "\n") {
				$data .= "\n";
			}
			preg_match("!(.*?\n){". $lines ."}$!", $data, $match);
			return $match[0];
		}
	}
	return $data;
}
$vals = read_file($_REQUEST['file'],$_REQUEST['lines'] ? $_REQUEST['lines'] : 20);
print json_encode($vals);
