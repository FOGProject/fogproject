<?php
require((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
$data = array();
$fetchDataInfo = array(
	'sites' 	=> 'http://www.fogproject.org/globalusers/',
	'version'	=> 'http://freeghost.sourceforge.net/version/version.php',
);
foreach ($fetchDataInfo AS $key => $url)
{
	if ($fetchedData = $FOGCore->fetchURL($url))
		$data[$key] = $fetchedData;
	else
		$data['error-' . $key] = _('Error contacting server');
}
print json_encode($data);
