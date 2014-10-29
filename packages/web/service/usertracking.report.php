<?php
require('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $_REQUEST['mac'] : base64_decode($_REQUEST['mac'])));
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if(!$Host || !$Host->isValid())
		throw new Exception('#!nf');
	if (!in_array(strtolower(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $_REQUEST['action'] : base64_decode($_REQUEST['action']))),array('login','start','logout')))
		throw new Exception('#!er: Postfix requires an action of login,logout, or start to operate');
	$user = explode(chr(92),strtolower(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $_REQUEST['user'] : base64_decode($_REQUEST['user']))));
	if ($user == null)
		throw new Exception('#!us');
	if (count($user) == 2)
		$user = $user[1];
	$date = $FOGCore->nice_date();
	$tmpDate = ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $FOGCore->nice_date($_REQUEST['date']) : $FOGCore->nice_date(base64_decode($_REQUEST['date'])));
	if ($tmpDate < $date)
		$desc = _('Replay from journal: real insert time').' '.$date->format('M j, Y g:i:s a').' Login time: '.$tmpDate->format('M j, Y g:i:s a');
	$actionText = ($_REQUEST['action'] == 'login' ? 1 : ($_REQUEST['action'] == 'logout' ? 0 : 99));
	$user = $_REQUEST['action'] == 'start' ? '' : $user;
	$UserTracking = new UserTracking(array(
		'hostID'	=> $Host->get('id'),
		'username'	=> $user,
		'action'	=> $actionText,
		'datetime'	=> $date->format('Y-m-d H:i:s'),
		'description' => $desc,
		'date' => $date->format('Y-m-d'),
	));
	if ($UserTracking->save())
		$Datatosend = '#!ok';
}
catch (Exception $e)
{
	$Datatosend = $e->getMessage();
}
if ($FOGCore->getSetting('FOG_NEW_CLIENT') && $FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
