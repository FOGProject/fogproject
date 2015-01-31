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
	if ($_REQUEST['newService'] && $Host->get('pub_key'))
		throw new Exception('#!ihc');
	if ($Host->get('ADPass') && $_REQUEST['newService'] && $FOGCore->getSetting('FOG_NEW_CLIENT'))
	{
		$encdat = substr($Host->get('ADPass'),0,-32);
		$enckey = substr($Host->get('ADPass'),-32);
		$decrypt = $FOGCore->aesdecrypt($encdat,$enckey);
		if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
			$password = $FOGCore->aesencrypt($decrypt,$FOGCore->getSetting('FOG_AES_ADPASS_ENCRYPT_KEY')).$FOGCore->getSetting('FOG_AES_ADPASS_ENCRYPT_KEY');
		else
			$password = $Host->get('ADPass');
		$Host->set('ADPass',trim($password))->save();
	}
	// Make system wait ten seconds before sending data
	sleep(10);
	// Send the information.
	$Datatosend = $FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? "#!ok\nhostname=".$Host->get('name')."\n" : '#!ok='.$Host->get('name')."\n";
	$Datatosend .= '#AD='.$Host->get('useAD')."\n";
	$Datatosend .= '#ADDom='.($Host->get('useAD') ? $Host->get('ADDomain') : '')."\n";
	$Datatosend .= '#ADOU='.($Host->get('useAD') ? $Host->get('ADOU') : '')."\n";
	$Datatosend .= '#ADUser='.($Host->get('useAD') ? (strpos($Host->get('ADUser'),"\\") || strpos($Host->get('ADUser'),'@') ? $Host->get('ADUser') : $Host->get('ADDomain')."\\".$Host->get('ADUser')) : '')."\n";
	$Datatosend .= '#ADPass='.($Host->get('useAD') ? $Host->get('ADPass') : '');
	if (trim(base64_decode($Host->get('productKey'))))
		$Datatosend .= "\n#Key=".base64_decode($Host->get('productKey'));
	if ($_REQUEST['newService'] && $FOGCore->getSetting('FOG_NEW_CLIENT'))
		$FOGCore->setSetting('FOG_AES_ADPASS_ENCRYPT_KEY',$FOGCore->randomString(32));
	if ($_REQUEST['newService'])
		print "#!enkey=".$FOGCore->certEncrypt($Datatosend,$Host);
	else
		print $Datatosend;
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
