<?php
require('../commons/base.inc.php');
try {
    if (FOGCore::getClass('GroupManager')->count() < 1) {
        throw new Exception(_('There are no groups on this server.'));
    }
    array_map(function (&$Group) {
        if (!$Group->isValid()) {
            return;
        }
        printf('\tID# %d\t-\t%s\n', $Group->get('id'), $Group->get('name'));
        unset($Group);
    }, (array)FOGCore::getClass('GroupManager')->find());
} catch (Exception $e) {
    echo $e->getMessage();
}
