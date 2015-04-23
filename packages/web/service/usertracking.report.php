<?php
require_once('../commons/base.inc.php');
try
{
	if (!in_array(strtolower(($_REQUEST['newService'] ? $_REQUEST['action'] : base64_decode($_REQUEST['action']))),array('login','start','logout')))
		throw new Exception('#!er: Postfix requires an action of login,logout, or start to operate');
	$user = explode(chr(92),strtolower(($_REQUEST['newService'] ? $_REQUEST['user'] : base64_decode($_REQUEST['user']))));
	$user = count($user) == 2 ? $user[1] : $user[0];
	if ($user == null)
		throw new Exception('#!us');
	$date = $FOGCore->nice_date();
	$tmpDate = ($_REQUEST['newService'] ? $FOGCore->nice_date($_REQUEST['date']) : $FOGCore->nice_date(base64_decode($_REQUEST['date'])));
	if ($tmpDate < $date)
		$desc = _('Replay from journal: real insert time').' '.$date->format('M j, Y g:i:s a').' Login time: '.$tmpDate->format('M j, Y g:i:s a');
	$login = ($_REQUEST['newService'] ? strtolower($_REQUEST['action']) : strtolower(base64_decode($_REQUEST['action'])));
	$actionText = ($login == 'login' ? 1 : ($login == 'logout' ? 0 : 99));
	$user = $_REQUEST['action'] == 'start' ? '' : $user;
	$Host = $FOGCore->getHostItem(true,($_REQUEST['newService'] ? false : true));
	$UserTracking = new UserTracking(array(
		'hostID'	=> $Host->get('id'),
		'username'	=> $user,
		'action'	=> $actionText,
		'datetime'	=> $date->format('Y-m-d H:i:s'),
		'description' => $desc,
		'date' => $date->format('Y-m-d'),
	));
	if ($UserTracking->save())
		throw new Exception('#!ok');
		$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
