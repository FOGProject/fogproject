<?php
require_once('../commons/base.inc.php');
try {
    // Just list all the images available.
    $val = '';
    if (!$FOGCore->getClass(ImageManager)->count()) throw new Exception(_('There are no images on this server.'));
    $Images = $FOGCore->getClass(ImageManager)->find();
    foreach ($Images AS $i => &$Image) $val .= sprintf('\tID# %d\t-\t%s\n',$Image->get(id),$Image->get(name));
    unset($Image);
} catch (Exception $e) {
    $val = $e->getMessage();
}
echo $val;
