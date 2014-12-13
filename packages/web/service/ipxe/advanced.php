<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
/**
* parseMe($Send)
* @param $Send the data to be sent.
* @return void
*/
function parseMe($Send)
{
	foreach($Send AS $ipxe => $val)
		print implode("\n",$val)."\n";
}
if ($_REQUEST['login'] == 1)
{
	$Send['loginstuff'] = array(
		'#!ipxe',
		'clear username',
		'clear password',
		'login',
		'params',
		'param username ${username}',
		'param password ${password}',
		'chain ${boot-url}/service/ipxe/advanced.php##params',
	);
	parseMe($Send);
	unset($_REQUEST['login']);
}
if ($_REQUEST['username'])
{
	if ($FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password']))
	{
		$Send['loginsuccess'] = array(
			'#!ipxe',
			'set userID ${username}',
			'chain ${boot-url}/service/ipxe/advanced.php',
		);
	}
	else
	{
		$Send['loginfail'] = array(
			'#!ipxe',
			'clear username',
			'clear password',
			'echo Invalid login!',
			'sleep 3',
			'chain -ar ${boot-url}/service/ipxe/advanced.php',
		);
		parseMe($Send);
		unset($_REQUEST['username'],$_REQUEST['password']);
	}
}
print "#!ipxe\n";
print $FOGCore->getSetting('FOG_PXE_ADVANCED');
