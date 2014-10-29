<?php
require('../commons/base.inc.php');
try
{
	// Just for checking if the host exists
	// Sends back if it does or doesn't.
	$HostMan = new HostManager();
	$hostname = trim(base64_decode(trim($_REQUEST['host'])));
	if($HostMan->exists($hostname))
	{
		$Host = current($HostMan->find(array('name' => $hostname)));
		throw new Exception("\tA hostname with that name already exists.\nThe MAC address associated with this host is:".$Host->get('mac'));
	}
	throw new Exception('#!ok');
}
catch (Exception $e)
{
	print $e->getMessage();
}
