<?php
header("Content-type: text/plain");
require_once('../../commons/base.inc.php');
if ($_REQUEST['login'] == 1)
{
	print "#!ipxe\n";
	print "login\n";
	print "params\n";
	print "param username \${username}\n";
	print "param password \${password}\n";
	print "chain \${boot-url}/service/ipxe/advanced.php##params\n";
	unset($_REQUEST['login']);
}
if ($_REQUEST['username'])
{
	if ($FOGCore->attemptLogin($_REQUEST['username'],$_REQUEST['password']))
	{
		print "#!ipxe\n";
		print "set userID \${username}\n";
		print "chain \${boot-url}/service/ipxe/advanced.php\n";
	}
	else
	{
		print "#!ipxe\n";
		print "clear \${username}\n";
		print "clear \${password}\n";
		unset($_REQUEST['username'],$_REQUEST['password']);
		print "echo Invalid login!\n";
		print "sleep 3\n";
		print "chain -ar \${boot-url}/service/ipxe/advanced.php\n";
	}
}
print "#!ipxe\n";
print "set fog-ip ".$FOGCore->getSetting('FOG_WEB_HOST')."\n";
print "set fog-webroot ".basename($FOGCore->getSetting('FOG_WEB_ROOT'))."\n";
print "set boot-url http://\${fog-ip}/\${fog-webroot}\n";
print $FOGCore->getSetting('FOG_PXE_ADVANCED');
