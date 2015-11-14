<?php
require_once('../commons/base.inc.php');
try {
    if (!$FOGCore->getClass('LocationManager')->count()) throw new Exception(_('There are no locations on this server.'));
    foreach ((array)$FOGCore->getClass('LocationManager')->find() AS $i => &$Location) {
        if (!$Location->isValid()) continue;
        printf('\tID# %s\t-\t%s\n',$Location->get('id'),$Location->get('name'));
        unset($Location);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
