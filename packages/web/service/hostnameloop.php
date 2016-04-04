<?php
require_once('../commons/base.inc.php');
try {
    $hostname = trim(base64_decode(trim($_REQUEST['host'])));
    $mac = array_filter((array)$FOGCore->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$FOGCore->getSubObjectIDs('Host',array('name'=>$hostname))),'mac'));
    if (count($mac)) throw new Exception(sprintf("\t%s\n%s: %s",_('A hostname with that name already exists.'),_('The MAC Address associated with this is'),array_shift($mac)));
    throw new Exception('#!ok');
} catch (Exception $e) {
    echo $e->getMessage();
}
