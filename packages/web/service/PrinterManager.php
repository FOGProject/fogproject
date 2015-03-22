<?php
require_once('../commons/base.inc.php');
try
{
	$Host = $FOGCore->getHostItem();
	// ???? three separate levels of enabling/disabling ????
	$level = $Host->get('printerLevel');
	if (empty($level) || $level == 0 || $level > 2)
		$level = 0;
	$Datatosendlevel = ($_REQUEST['newService'] ? '#level=' : '#!mg=').$level;
	if ($level > 0)
	{
		// Get all the printers set for this host.
		$index = 0;
		foreach ($Host->get('printers') AS $Printer)
		{
			// need this part, to ensure printer only sends it's needed data, not all data set in printer.
			if ($Printer->get('type') == 'Network')
				$Datatosendprint[] = '|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0');
			else if ($Printer->get('type') == 'iPrint')
				$Datatosendprint[] = $Printer->get('port').'|||'.$Printer->get('name').'||'.($Host->getDefault($Printer->get('id'))?'1':'0');
			else
				$Datatosendprint[] = $Printer->get('port').'|'.$Printer->get('file').'|'.$Printer->get('model').'|'.$Printer->get('name').'|'.$Printer->get('ip').'|'.($Host->getDefault($Printer->get('id'))?'1':'0');
			if ($_REQUEST['newService'])
				$Datatosendprinter[] = "#printer$index=".base64_encode($Datatosendprint[0]);
			else
				$Datatosendprinter[] = base64_encode($Datatosendprint[0]);
			$index++;
			unset($Datatosendprint);
		}
		$Datatosendprint = implode("\n",(array)$Datatosendprinter);
	}
	$Datatosend = ($_REQUEST['newService'] ? "#!ok\n" : '').($_REQUEST['newService'] ? $Datatosendlevel."\n".$Datatosendprint : base64_encode($Datatosendlevel)."\n".$Datatosendprint);
	$FOGCore->sendData($Datatosend);
}
catch(Exception $e)
{
	print $e->getMessage();
	exit;
}
