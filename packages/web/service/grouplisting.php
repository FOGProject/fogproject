<?php
require('../commons/base.inc.php');
try {
    if ($FOGCore::getClass('GroupManager')->count()) throw new Exception(_('There are no groups on this server.'));
    foreach ((array)$FOGCore::getClass('GroupManager')->find() AS $i => &$Group) {
        if (!$Group->isValid()) continue;
        printf('\tID# %d\t-\t%s\n',$Group->get('id'),$Group->get('name'));
        unset($Group);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
