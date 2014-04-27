<?php
require('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	// Get the mode.
	if (trim($_REQUEST['mode']) != array('q','s'))
		throw new Exception(_('Invalid operational mode'));
	// Get the info
	$string = explode(':',base64_decode($_REQUEST['string']));
	$vInfo = explode(' ',trim($string[1]));
	//Store the info.
	$Virus = new Virus(array(
		'name' => trim($vInfo[0]),
		'hostMAC' => strtolower($Host->get('mac')),
		'file' => $string[0],
		'date' => date('Y-m-d H:i:s'),
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
