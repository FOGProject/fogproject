<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#!nf');
	if (!$Host->isHostnameSafe())
		throw new Exception('#!ih');
	// Send the information.
	print '#!ok='.$Host->get('name')."\n";
	print '#AD='.$Host->get('useAD')."\n";
	print '#ADDom='.$Host->get('ADDomain')."\n";
	print '#ADOU='.$Host->get('ADOU')."\n";
	print '#ADUser='.$Host->get('ADDomain').'\\'.$Host->get('ADUser')."\n";
	print '#ADPass='.$Host->get('ADPass');
	print '#Key='.base64_decode($Host->get('productKey'));
	// Just inform the user (probably not needed and probably won't display.)
	if (!$Host->get('useAD'))
		throw new Exception("#!er: Join domain disabled on this host.\n");
}
catch (Exception $e)
{
	print $e->getMessage();
}
