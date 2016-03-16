<?php
require('../commons/base.inc.php');
try {
    $MACs = $FOGCore->getHostItem(true,true,true,true);
    $Host = FOGCore::getClass('HostManager')->getHostByMacAddresses($MACs);
    if ($Host && $Host->isValid()) throw new Exception(sprintf('%s: %s',_('This Machine is already registered as'),$Host->get('name')));
    echo "#!ok";
} catch (Exception $e) {
    echo $e->getMessage();
}
