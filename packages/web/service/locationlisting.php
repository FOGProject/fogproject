<?php
require('../commons/base.inc.php');
try {
    if (FOGCore::getClass('LocationManager')->count() < 1) {
        throw new Exception(_('There are no locations on this server.'));
    }
    array_map(function (&$Location) {
        if (!$Location->isValid()) {
            return;
        }
        printf('\tID# %d\t-\t%s\n', $Location->get('id'), $Location->get('name'));
        unset($Location);
    }, (array)FOGCore::getClass('LocationManager')->find());
} catch (Exception $e) {
    echo $e->getMessage();
}
