<?php
require_once('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem();
    // Make system wait ten seconds before sending data
    // Send the information.
    $Datatosend = $_REQUEST[newService] ? "#!ok\n#hostname=".$Host->get(name)."\n" : '#!ok='.$Host->get(name)."\n";
    $Datatosend .= '#AD='.$Host->get(useAD)."\n";
    $Datatosend .= '#ADDom='.($Host->get(useAD) ? $Host->get(ADDomain) : '')."\n";
    $Datatosend .= '#ADOU='.($Host->get(useAD) ? $Host->get(ADOU) : '')."\n";
    $Datatosend .= '#ADUser='.($Host->get(useAD) ? (strpos($Host->get(ADUser),"\\") || strpos($Host->get('ADUser'),'@') ? $Host->get(ADUser) : $Host->get(ADDomain)."\\".$Host->get(ADUser)) : '')."\n";
    $Datatosend .= '#ADPass='.($Host->get(useAD) ? ($_REQUEST[newService] ? $FOGCore->aesdecrypt($Host->get(ADPass)) : $Host->get(ADPass)) : '');
    if (trim(base64_decode($Host->get(productKey)))) $Datatosend .= "\n#Key=".base64_decode($Host->get(productKey));
    if ($_REQUEST[newService]) $Host->setAD();
    $FOGCore->sendData($Datatosend);
} catch (Exception $e) {
    print $e->getMessage();
    exit;
}
