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
	if (!$Host->isValid())
		throw new Exception('#!nf');
	$action = strtolower(base64_decode($_REQUEST['action']));
	$user = explode(chr(92),strtolower(base64_decode($_REQUEST['user'])));
	if ($user == null)
		throw new Exception('#!us');
	if (count($user) == 2)
		$user = $user[1];
	$date = new Date(time());
	$tmpDate = base64_decode($_REQUEST['date']);
	if ($tmpDate != null && strlen($tmpDate) > 0)
	{
		$date = new Date(strtotime($tmpDate));
		$desc = _('Replay from journal: real insert time').' '.$date->toString("M j, Y g:i:s a");
	}
	if ($action == 'login')
		$actionText = 1;
	else if ($action == 'start')
	{
		$user = '';
		$actionText = 99;
	}
	else
		$actionText = 0;
	$UserTracking = new UserTracking(array(
		'hostID'	=> $Host->get('id'),
		'username'	=> $user,
		'action'	=> $actionText,
		'datetime'	=> date('Y-m-d H:i:s',$date->toTimestamp()),
		'description' => $desc,
		'date' => date('Y-m-d', $date->toTimestamp()),
	));
	if ($UserTracking->save())
		print '#!ok';
}
catch( Exception $e )
{
	print $e->getMessage();
}
