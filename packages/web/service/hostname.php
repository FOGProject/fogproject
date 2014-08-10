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
	if ($Host->get('ADPass') && $_REQUEST['newService'])
	{
		$decrypt = $FOGCore->aesdecrypt($Host->get('ADPass'),$FOGCore->getSetting('FOG_AES_ADPASS_ENCRYPT_KEY'));
		if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
			$password = $FOGCore->aesencrypt($decrypt,$FOGCore->getSetting('FOG_AES_ADPASS_ENCRYPT_KEY'));
		else
			$password = $Host->get('ADPass');
		$Host->set('ADPass',trim($password))->save();
	}
	// Send the information.
	$Datatosend = !$_REQUEST['newService'] ? '#!ok='.$Host->get('name')."\n" : "#!ok\n#hostname=".$Host->get('name')."\n";
	$Datatosend .= '#AD='.$Host->get('useAD')."\n";
	$Datatosend .= '#ADDom='.$Host->get('ADDomain')."\n";
	$Datatosend .= '#ADOU='.$Host->get('ADOU')."\n";
	$Datatosend .= '#ADUser='.$Host->get('ADDomain').'\\'.$Host->get('ADUser')."\n";
	$Datatosend .= '#ADPass='.$Host->get('ADPass');
	if (trim(base64_decode($Host->get('productKey'))))
		$Datatosend .= "\n#Key=".base64_decode($Host->get('productKey'));
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
