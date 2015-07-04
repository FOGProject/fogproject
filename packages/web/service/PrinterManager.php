<?php
require_once('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem();
    // ???? three separate levels of enabling/disabling ????
    $level = $Host->get(printerLevel);
    if ($level > 2 || $level <= 0) $level = 0;
    $Datatosendlevel = "#!mg=$level";
    if ($level > 0) {
        // Get all the printers set for this host.
        $index = 0;
        foreach ($FOGCore->getClass(PrinterManager)->find(array('id' => $Host->get(printers))) AS &$Printer) {
            // need this part, to ensure printer only sends it's needed data, not all data set in printer.
            if ($Printer->get(type) == 'Network') $Datatosendprint[] = '|||'.$Printer->get(name).'||'.($Host->getDefault($Printer->get(id))?'1':'0');
            else if ($Printer->get(type) == 'iPrint') $Datatosendprint[] = $Printer->get(port).'|||'.$Printer->get(name).'||'.($Host->getDefault($Printer->get(id))?'1':'0');
            else $Datatosendprint[] = $Printer->get(port).'|'.$Printer->get('file').'|'.$Printer->get(model).'|'.$Printer->get(name).'|'.$Printer->get(ip).'|'.($Host->getDefault($Printer->get(id))?'1':'0');
            $Datatosendprinter[] = base64_encode($Datatosendprint[0]);
            unset($Datatosendprint);
        }
        unset($Printer);
        $Datatosendprint = implode("\n",(array)$Datatosendprinter);
    }
    $Datatosend = base64_encode($Datatosendlevel)."\n".$Datatosendprint;
    $FOGCore->sendData($Datatosend);
} catch(Exception $e) {
    print $e->getMessage();
    exit;
}
