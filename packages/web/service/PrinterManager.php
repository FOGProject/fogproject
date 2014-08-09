<?php
require_once('../commons/base.inc.php');
try
{
	$HostManager = new HostManager();
	$MACs = HostManager::parseMacList($_REQUEST['mac']);
	if (!$MACs)
		throw new Exception('#!im');
	// Get the Host
	$Host = $HostManager->getHostByMacAddresses($MACs);
	if (!$Host->isValid())
		throw new Exception('#ih');
	// get and eval level
	// ???? three separate levels of enabling/disabling ????
	$level = $Host->get('printerLevel');
	if (empty($level) || $level == 0 || $level > 2)
		$level = 0;
	$Datatosendlevel = $level;
	if ($level > 0)
	{
		// Get all the printers set for this host.
		$Printers = $FOGCore->getClass('PrinterAssociationManager')->find(array('hostID' => $Host->get('id')));
		foreach ($Printers AS $Printer)
		{
			$Printers[] = new Printer($Printer->get('printerID'));
		}
		foreach ($Printers AS $Printer)
		{
			// Send the printer based on the type.
			if ($Printer->get('type') == 'Network')
				$Datatosendprint = '|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0');
			else if ($Printer->get('type') == 'iPrint')
				$Datatosendprint = $Printer->get('port').'|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0');
			else
				$Datatosendprint = $Printer->get('port').'|'.$Printer->get('file').'|'.$Printer->get('model').'|'.$Printer->get('name').'|'.$Printer->get('ip').'|'.($Host->getDefault($Printer->get('id'))?'1':'0');
		}
	}
}
catch(Exception $e)
{
	$Datatosenderror = '#!er:'.$e->getMessage();
}
if ($Datatosenderror)
	$Datatosend = $Datatosenderror;
else
	$Datatosend = '#!mg='.($_REQUEST['newService'] ? $Datatosendlevel."\n".$Datatosendprint : base64_encode($Datatosendlevel)."\n".base64_encode($Datatosendprint));
if ($FOGCore->getSetting('FOG_AES_ENCRYPT'))
	print "#!ok\n#en=".$FOGCore->aesencrypt($Datatosend,$FOGCore->getSetting('FOG_AES_PASS_ENCRYPT_KEY'));
else
	print $Datatosend;
