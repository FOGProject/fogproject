<?php
require('../commons/base.inc.php');
try {
    if (!FOGCore::getClass('SnapinManager')->count(array('isEnabled'=>1))) throw new Exception(_('There are no snapins on this server.'));
    foreach ((array)FOGCore::getClass('SnapinManager')->find(array('isEnabled'=>1)) AS $i => &$Snapin) {
        if (!$Snapin->isValid()) continue;
        printf('\tID# %s\t-\t%s\n',$Snapin->get('id'),$Snapin->get('name'));
        unset($Snapin);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
