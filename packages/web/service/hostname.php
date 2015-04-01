<?php
require_once('../commons/base.inc.php');
try
{
	$Host = $FOGCore->getHostItem();
	if ($Host->get('ADPass') && $_REQUEST['newService'])
	{
		$encdat = $Host->get('ADPass');
		$decrypt = $FOGCore->aesdecrypt($encdat);
		if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
			$password = $FOGCore->aesencrypt($decrypt);
		else
			$password = $Host->get('ADPass');
		$Host->set('ADPass',trim($password))->save();
	}
	// Make system wait ten seconds before sending data
	sleep(10);
	// Send the information.
	$Datatosend = $_REQUEST['newService'] ? "#!ok\n#hostname=".$Host->get('name')."\n" : '#!ok='.$Host->get('name')."\n";
	$Datatosend .= '#AD='.$Host->get('useAD')."\n";
	$Datatosend .= '#ADDom='.($Host->get('useAD') ? $Host->get('ADDomain') : '')."\n";
	$Datatosend .= '#ADOU='.($Host->get('useAD') ? $Host->get('ADOU') : '')."\n";
	$Datatosend .= '#ADUser='.($Host->get('useAD') ? (strpos($Host->get('ADUser'),"\\") || strpos($Host->get('ADUser'),'@') ? $Host->get('ADUser') : $Host->get('ADDomain')."\\".$Host->get('ADUser')) : '')."\n";
	$Datatosend .= '#ADPass='.($Host->get('useAD') ? ($_REQUEST['newService'] ? $FOGCore->aesdecrypt($Host->get('ADPass')) : $Host->get('ADPass')) : '');
	if (trim(base64_decode($Host->get('productKey'))))
		$Datatosend .= "\n#Key=".base64_decode($Host->get('productKey'));
	if ($_REQUEST['newService'])
	{
		$pass = $Host->get('ADPass');
		$decrypt = $FOGCore->aesdecrypt($pass);
		if ($decrypt && mb_detect_encoding($decrypt,'UTF-8',true))
			$pass = $FOGCore->aesencrypt($decrypt);
		else
			$pass = $FOGCore->aesencrypt($pass);
		$Host->set('ADPass',$pass)->save();
	}
	$FOGCore->sendData($Datatosend);
}
catch (Exception $e)
{
	print $e->getMessage();
	exit;
}
