<?php
$vals = function() {
	ini_set("auto_detect_line_endings", true);
	$linearr = array();
	$fp = fopen($_REQUEST['file'],'r');
	stream_set_blocking($fp,false);
	while (!feof($fp)) {
		$line = stream_get_line($fp,8192,"\n");
		array_push($linearr,$line);
		if (count($linearr) > $_REQUEST['lines']) array_shift($linearr);
	}
	fclose($fp);
	return implode("\n",$linearr);
};
print json_encode($vals());
