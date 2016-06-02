<?php
require('../commons/base.inc.php');
header('Content-Type: text/plain');
header('Connection: close');
$Host = $FOGCore->getHostItem(false);
$Image = $Host->getImage();
$Inventory = $Host->get('inventory');
$repFields = array(
    'hostname' => $Host->get('name'),
    'hostdesc' => $Host->get('description'),
    'imageosid' => $Image->getOS()->get('id'),
    'imagepath' => $Image->get('path'),
    'hostusead' => $Host->get('useAD'),
    'hostaddomain' => $Host->get('ADDomain'),
    'hostadou' => $Host->get('ADOU'),
    'hostproductkey' => $Host->get('productKey'),
    'primaryuser' => $Inventory->get('primaryuser'),
    'othertag' => $Inventory->get('other'),
    'othertag1' => $Inventory->get('other1'),
    'sysman' => $Inventory->get('sysman'),
    'sysproduct' => $Inventory->get('sysproduct'),
    'sysserial' => $Inventory->get('sysserial'),
    'mbman' => $Inventory->get('mbman'),
    'mbserial' => $Inventory->get('mbserial'),
    'mbasset' => $Inventory->get('mbasset'),
    'mbproductname' => $Inventory->get('mbproductname'),
    'caseman' => $Inventory->get('caseman'),
    'caseserial' => $Inventory->get('caseserial'),
    'caseasset' => $Inventory->get('caseasset'),
);
$HookManager->processEvent('HOST_INFO_EXPOSE',array('repFields'=>&$repFields,'Host'=>&$Host));
array_walk($repFields,function(&$val,$key) {
    printf("export %s=\"%s\"\n",$key,$val);
    unset($val,$key);
});
