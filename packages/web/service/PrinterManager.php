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
	print base64_encode('#!mg='.$level)."\n";
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
				print base64_encode('|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0'))."\n";
			else if ($Printer->get('type') == 'iPrint')
				print base64_encode($Printer->get('port').'|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0'))."\n";
			else
				print base64_encode($Printer->get('port').'|'.$Printer->get('file').'|'.$Printer->get('model').'|'.$Printer->get('name').'|'.$Printer->get('ip').'|'.($Host->getDefault($Printer->get('id'))?'1':'0')."\n");
		}
	}
}
catch(Exception $e)
{
	print base64_encode('#!er:'.$e->getMessage());
}
