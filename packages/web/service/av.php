<?php
require_once('../commons/base.inc.php');
try
{
	// Get the mode.
	if (trim($_REQUEST['mode']) != array('q','s'))
		throw new Exception(_('Invalid operational mode'));
	// Get the info
	$string = explode(':',base64_decode($_REQUEST['string']));
	$vInfo = explode(' ',trim($string[1]));
	//Store the info.
	$Virus = new Virus(array(
		'name' => trim($vInfo[0]),
		'hostMAC' => strtolower($FOGCore->getHostItem(false)->get('mac')),
		'file' => $string[0],
		'date' => $FOGCore->formatTime('now','Y-m-d H:i:s'),
		'mode' => $_REQUEST['mode']
	));
	if ($Virus->save())
		throw new Exception(_('Accepted'));
	else
		throw new Exception(_('Failed'));
}
catch (Exception $e)
{
	print $e->getMessage();
}
