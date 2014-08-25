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
	if (!$Host->isValid())
		throw new Exception('#!nf');
	if (!in_array(strtolower($_REQUEST['action']),array('login','start','logout')))
		throw new Exception('#!er: Postfix requires an action of login,logout, or start to operate');
	$user = explode(chr(92),strtolower(($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $_REQUEST['user'] : base64_decode($_REQUEST['user']))));
	if ($user == null)
		throw new Exception('#!us');
	if (count($user) == 2)
		$user = $user[1];
	$date = new Date(time());
	$tmpDate = ($FOGCore->getSetting('FOG_NEW_CLIENT') && $_REQUEST['newService'] ? $_REQUEST['date'] : base64_decode($_REQUEST['date']));
	if ($tmpDate != null && strlen($tmpDate) > 0)
	{
		$date = new Date(strtotime($tmpDate));
		$desc = _('Replay from journal: real insert time').' '.$date->toString("M j, Y g:i:s a");
	}
	$actionText = ($_REQUEST['action'] == 'login' ? 1 : ($_REQUEST['action'] == 'logout' ? 0 : 99));
	$user = $_REQUEST['action'] == 'start' ? '' : $user;
	$UserTracking = new UserTracking(array(
		'hostID'	=> $Host->get('id'),
		'username'	=> $user,
		'action'	=> $actionText,
		'datetime'	=> date('Y-m-d H:i:s',$date->toTimestamp()),
		'description' => $desc,
		'date' => date('Y-m-d', $date->toTimestamp()),
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
