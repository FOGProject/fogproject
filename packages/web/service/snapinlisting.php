<?php
require('../commons/base.inc.php');
try {
    if (FOGCore::getClass('SnapinManager')->count(array('isEnabled'=>1)) < 1) {
        throw new Exception(_('There are no snapins on this server.'));
    }
    array_map(function (&$Snapin) {
        if (!$Snapin->isValid()) {
            return;
        }
        printf('\tID# %d\t-\t%s\n', $Snapin->get('id'), $Snapin->get('name'));
        unset($Snapin);
    }, (array)FOGCore::getClass('SnapinManager')->find(array('isEnabled'=>1)));
} catch (Exception $e) {
    echo $e->getMessage();
}
