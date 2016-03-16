<?php
require('../commons/base.inc.php');
try {
    if (!FOGCore::getClass('ImageManager')->count(array('isEnabled'=>1))) throw new Exception(_('There are no images on this server.'));
    foreach ((array)FOGCore::getClass('ImageManager')->find(array('isEnabled'=>1)) AS $i => &$Image) {
        if (!$Image->isValid()) continue;
        printf('\tID# %d\t-\t%s\n',$Image->get('id'),$Image->get('name'));
        unset($Image);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
