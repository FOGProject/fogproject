<?php
require('../commons/base.inc.php');
try {
    $hostname = trim(base64_decode(trim($_REQUEST['host'])));
    foreach ($FOGCore::getClass('HostManager')->find(array('name' => $hostname)) AS &$Host) {
        if (!$Host->isValid()) break;
        throw new Exception(sprintf("\t%s\n%s: %s",_('A hostname with that name already exists.'),_('The MAC Address associated with this is'),$Host->get('mac')));
        unset($Host);
    }
    throw new Exception('#!ok');
} catch (Exception $e) {
    echo $e->getMessage();
}
