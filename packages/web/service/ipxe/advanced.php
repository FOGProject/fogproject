<?php
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: deny');
header('Cache-Control: no-cache');
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
/**
 * parseMe($Send)
 * @param $Send the data to be sent.
 * @return void
 */
$parseMe = function($Send) {
	foreach($Send AS $ipxe => $val)
		echo implode("\n",$val)."\n";
};
if (isset($_REQUEST['login'])) {
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
	$parseMe($Send);
	unset($_REQUEST['login']);
}
if (isset($_REQUEST['username'])) {
	if ($FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password'])) {
		$Send['loginsuccess'] = array(
			'#!ipxe',
			'set userID ${username}',
			'chain ${boot-url}/service/ipxe/advanced.php',
		);
	} else {
		$Send['loginfail'] = array(
			'#!ipxe',
			'clear username',
			'clear password',
			'echo Invalid login!',
			'sleep 3',
			'chain -ar ${boot-url}/service/ipxe/advanced.php',
		);
		$parseMe($Send);
		unset($_REQUEST['username'],$_REQUEST['password']);
	}
}
echo "#!ipxe\n{$FOGCore->getSetting(FOG_PXE_ADVANCED)}";
