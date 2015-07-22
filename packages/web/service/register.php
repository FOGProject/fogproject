<?php
require_once('../commons/base.inc.php');
try {
    if (!$_REQUEST[newService] && $_REQUEST[version] != 2) throw new Exception('#!er:Invalid Version Number, please update this module.');
    // The total number of pending macs that can be used.
    $maxPending = $FOGCore->getSetting(FOG_QUICKREG_MAX_PENDING_MACS);
    // Get the actual host (if it is registered)
    $MACs = $FOGCore->getHostItem(true,false,false,true);
    $Host = $FOGCore->getHostItem(true,false,true,false,true);
    $Hosts = $this->getClass(HostManager)->find(array(name=>$_REQUEST[hostname]));
    if (!($Host instanceof Host && $Host->isValid())) $Host = @array_shift($Hosts);
    if (!($Host instanceof Host && $Host->isValid() && !$Host->get(pending)) && $_REQUEST[newService]) {
        if (!$FOGCore->getClass(Host)->isHostnameSafe($_REQUEST[hostname])) throw new Exception('#!ih');
        foreach ($FOGCore->getClass(HostManager)->find(array(name=>$_REQUEST[hostname])) AS $i => &$Host) if ($Host->isValid()) break;
        unset($Host);
        if (!($Host instanceof Host && $Host->isValid())) {
            $ModuleIDs = $FOGCore->getClass(ModuleManager)->find(array(isDefault => 1),'','','','','','',id);
            $PriMAC = array_shift($MACs);
            $Host = $FOGCore->getClass(Host)
                ->set(name, $_REQUEST[hostname])
                ->set(description,'Pending Registration created by FOG_CLIENT')
                ->set(pending,1)
                ->addModule($ModuleIDs)
                ->addPriMAC($PriMAC)
                ->addAddMAC($MACs);
            if (!$Host->save()) throw new Exception("#!ih\n");
            throw new Exception("#!ok\n");
        }
    }
    // Check if count is okay.
    if (count($MACs) > $maxPending + 1) throw new Exception('#!er:Too many MACs');
    // Cycle the MACs
    foreach($MACs AS $MAC) $AllMacs[] = strtolower($MAC);
    // Cycle the already known macs
    $KnownMacs = $FOGCore->getClass(Host)->getMyMacs(false);
    $MACs = array_unique(array_diff((array)$AllMacs,(array)$KnownMacs));
    if (count($MACs)) {
        $Host->addPendMAC($MACs);
        if ($Host->save()) $Datatosend = "#!ok\n";
        else throw new Exception('#!er: Error adding MACs');
    } else $Datatosend = "#!ig\n";
    $FOGCore->sendData($Datatosend);
} catch (Exception $e) {
    print $e->getMessage();
    exit;
}
