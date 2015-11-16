<?php
$vals = function($currentUser) {
    if (!$currentUser->isValid()) return _('Must be logged in to view');
	ini_set("auto_detect_line_endings", true);
    $linearr = array();
    $ftp = trim($_REQUEST['ftp']);
    $folder = sprintf('/%s/',trim(trim(dirname($_REQUEST['file']),'/')));
    $file = trim(basename($_REQUEST['file']));
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2');
    $pattern = sprintf('#^%s$#',$folder);
    if (false === preg_grep($pattern,$folders)) return _('Invalid Folder');
    if (($fp = fopen(sprintf('%s/%s/%s',$ftp,$folder,$file),'rb')) !== false) {
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
echo json_encode($vals($currentUser));
exit;
