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
	if(!$Host || !$Host->isValid() || $Host->get('pending'))
		throw new Exception('#!ih');
	if ($_REQUEST['newService'] && !$Host->get('pub_key'))
		throw new Exception('#!ihc');
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
	$login = ($_REQUEST['newService'] ? strtolower($_REQUEST['action']) : strtolower(base64_decode($_REQUEST['action'])));
	$actionText = ($login == 'login' ? 1 : ($login == 'logout' ? 0 : 99));
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
