<?php
$vals = function($currentUser,$decrypt) {
    if (!$currentUser->isValid()) return _('Must be logged in to view');
	ini_set("auto_detect_line_endings", true);
    $linearr = array();
    $ftp = trim($decrypt->aesdecrypt($_REQUEST['ftp']));
    $folder = sprintf('/%s/',trim(trim(dirname($_REQUEST['file']),'/')));
    $pattern = sprintf('#^%s$#',$folder);
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2');
    if (!preg_grep($pattern,$folders)) return _('Invalid Folder');
    $file = trim(basename($_REQUEST['file']));
    $path = sprintf('%s%s%s',$ftp,$folder,$file);
    if (($fp = fopen($path,'rb')) !== false) {
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
require('../commons/base.inc.php');
echo json_encode($vals($currentUser,$FOGCore));
exit;
