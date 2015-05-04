<?php
$vals = function() {
	ini_set("auto_detect_line_endings", true);
	$linearr = array();
	if(($fp = @fopen($_REQUEST['file'],'r')) !== false) {
		stream_set_blocking($fp,false);
		while (!feof($fp)) {
			$line = stream_get_line($fp,8192,"\n");
			array_push($linearr,$line);
			if (count($linearr) > $_REQUEST['lines']) array_shift($linearr);
		}
		@fclose($fp);
	}
	else
		return "No data to read\n";
	return implode("\n",$linearr);
};
print json_encode($vals());
