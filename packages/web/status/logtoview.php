<?php
$vals = function() {
	ini_set("auto_detect_line_endings", true);
	$linearr = array();
	if(($fp = fopen(dirname($_REQUEST['file']).DIRECTORY_SEPARATOR.basename($_REQUEST['file']),'rb')) !== false) {
		stream_set_blocking($fp,false);
		while (!feof($fp)) {
			$line = stream_get_line($fp,8192,"\n");
			array_push($linearr,$line);
			if (count($linearr) > $_REQUEST['lines']) array_shift($linearr);
		}
		@fclose($fp);
	}
	else return _("No data to read")."\n";
	return implode("\n",$linearr);
};
echo json_encode($vals());
exit;
