<?php
require('../../commons/base.inc.php');
/**
 * parseMe($Send)
 * @param $Send the data to be sent.
 * @return void
 */
$parseMe = function($Send) {
	foreach($Send AS $ipxe => &$val) {
        printf("%s\n",implode("\n",(array)$val));
        unset($val);
    }
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
