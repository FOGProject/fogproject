<?php
require('../commons/base.inc.php');
try {
    if (!$FOGCore->getClass('ImageManager')->count()) throw new Exception(_('There are no images on this server.'));
    foreach ((array)$FOGCore->getClass('ImageManager')->find() AS $i => &$Image) {
        if (!$Image->isValid()) continue;
        printf('\tID# %d\t-\t%s\n',$Image->get('id'),$Image->get('name'));
        unset($Image);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
