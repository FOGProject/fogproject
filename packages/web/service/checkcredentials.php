<?php
require('../commons/base.inc.php');
try
{
	$username = base64_decode(trim($_REQUEST['username']));
	$password = base64_decode(trim($_REQUEST['password']));
	if ($FOGCore->attemptLogin($username, $password))
		print "#!ok";
	else
		throw new Exception('#!il');
}
catch (Exception $e)
{
	print $e->getMessage();
}
