<?php
require('../commons/base.inc.php');
try {
    if (FOGCore::getClass('ImageManager')->count(array('isEnabled'=>1)) < 1) throw new Exception(_('There are no images on this server.'));
    array_map(function(&$Image) {
        if (!$Image->isValid()) return;
        printf('\tID# %d\t-\t%s\n',$Image->get('id'),$Image->get('name'));
        unset($Image);
    },(array)FOGCore::getClass('ImageManager')->find(array('isEnabled'=>1)));
} catch (Exception $e) {
    echo $e->getMessage();
}
