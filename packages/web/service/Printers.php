

<?php
require_once('../commons/base.inc.php');
try {
        $Host = $FOGCore->getHostItem();
        // Error checking why send anything if nothing to send
        $modes = array(
                '0',
                'a',
                'ar',
        );
        $mode = $modes[$Host->get(printerLevel)];
        if (!$FOGCore->getClass(PrinterAssociationManager)->count(array('hostID' => $Host->get(id)))) throw new Exception("#!np\n#mode=$mode\n");
        $Datatosend = '';
        if (!isset($_REQUEST[id])) {
                // Only send mode if no management is selected
                if (!$mode) throw new Exception("#mode=$mode");
                $Datatosend .= "#mode=$mode\n";
                $index = 0;
                foreach ($FOGCore->getClass(PrinterManager)->find(array('id' => $Host->get(printers))) AS $Printer) $Printertosend .= '#printer'.$index++.'='.$Printer->get(id)."\n";
                $Datatosend .= $Printertosend;
        } else {
                $Printer = $FOGCore->getClass(Printer,$_REQUEST[id]);
                if (!$Printer->isValid()) throw new Exception('#!nvp');
                // Ensure the Printer belongs with the host
                if (!in_array($_REQUEST[id],(array)$FOGCore->getClass(PrinterAssociationManager)->find(array('hostID' => $Host->get(id),'printerID' => $_REQUEST[id]),'','','','','','','printerID'))) throw new Exception('#!ph');
                $Datatosend .= "#type={$Printer->get(config)}\n";
                $Datatosend .= "#port={$Printer->get(port)}\n";
                $Datatosend .= "#file={$Printer->get(file)}\n";
                $Datatosend .= "#model={$Printer->get(model)}\n";
                $Datatosend .= "#name={$Printer->get(name)}\n";
                $Datatosend .= "#ip={$Printer->get(ip)}\n";
                $Datatosend .= "#default={$Host->getDefault($Printer->get(id))}\n";
        }
        $FOGCore->sendData("#!ok\n$Datatosend");
} catch (Exception $e) {
        print $e->getMessage();
        exit;
}
