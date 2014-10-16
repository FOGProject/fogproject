<<<<<<< HEAD
<?php
require_once((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
try
{
	$ping = $_GET['ping'];
	// Error checking
	if (!$_SESSION['AllowAJAXTasks'])
		throw new Exception(_('FOG session invalid'));
	if (empty($ping) || $ping == 'undefined')
		throw new Exception(_('Undefined host to ping'));
	if (!HostManager::isHostnameSafe($ping))
		throw new Exception(_('Invalid hostname'));
	if (is_numeric($ping))
	{	
		// ping is a host id
		$Host = new Host($ping);
		$ping = $Host->get('name');
	}
	// Resolve hostname
	$ip = gethostbyname($ping);
	// Did the hostname resolve correctly?
	if ($ip == $ping)
		throw new Exception(_('Unable to resolve hostname'));
	// Ping IP Address
	$result = $FOGCore->getClass('Ping', $ip)->execute();
	// Show error message if not successful
	if ($result !== true)
		throw new Exception($result);
	// Success
	print '1';
}
catch (Exception $e)
{
	print $e->getMessage();
}
=======
<?php
require_once((defined('BASEPATH') ? BASEPATH . '/commons/base.inc.php' : '../../commons/base.inc.php'));
try
{
	$ping = $_GET['ping'];
	// Error checking
	if (!$_SESSION['AllowAJAXTasks'])
		throw new Exception(_('FOG session invalid'));
	if (empty($ping) || $ping == 'undefined')
		throw new Exception(_('Undefined host to ping'));
	if (!HostManager::isHostnameSafe($ping))
		throw new Exception(_('Invalid hostname'));
	if (is_numeric($ping))
	{	
		// ping is a host id
		$Host = new Host($ping);
		$ping = $Host->get('name');
	}
	// Resolve hostname
	$ip = gethostbyname($ping);

	// Did the hostname resolve correctly?
	if ($ip == $ping)
		throw new Exception(_('Unable to resolve hostname'));
	// Ping IP Address
	$result = $FOGCore->getClass('Ping', $ip)->execute();
	// Show error message if not successful
	if ($result !== true)
		throw new Exception($result);
	// Success
	print '1';
}
catch (Exception $e)
{
	print $e->getMessage();
}
>>>>>>> svn
