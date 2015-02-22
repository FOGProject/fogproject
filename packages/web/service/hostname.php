<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = FOGCore::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host || !$Host->isValid() || $Host->get('pending') || !HostManager::isHostnameSafe($Host->get('name')))
		throw new Exception('#!ih');
	sleep(10);
	// Send the information.
	$Datatosend = $_REQUEST['newService'] ? "#!ok\nhostname=".$Host->get('name')."\n" : '#!ok='.$Host->get('name')."\n";
	$Datatosend .= '#AD='.$Host->get('useAD')."\n";
	$Datatosend .= '#ADDom='.($Host->get('useAD') ? $Host->get('ADDomain') : '')."\n";
	$Datatosend .= '#ADOU='.($Host->get('useAD') ? $Host->get('ADOU') : '')."\n";
	$Datatosend .= '#ADUser='.($Host->get('useAD') ? (strpos($Host->get('ADUser'),"\\") || strpos($Host->get('ADUser'),'@') ? $Host->get('ADUser') : $Host->get('ADDomain')."\\".$Host->get('ADUser')) : '')."\n";
	$Datatosend .= '#ADPass='.($Host->get('useAD') ? $Host->get('ADPass') : '');
	if (trim(base64_decode($Host->get('productKey'))))
		$Datatosend .= "\n#Key=".base64_decode($Host->get('productKey'));
	print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
