<?php
require_once('../commons/base.inc.php');
try {
    // Just list all the images available.
    $Images = $FOGCore->getClass(ImageManager)->find();
    if (!$Images) throw new Exception(_('There are no images on this server.'));
    foreach ($Images AS &$Image) printf('\tID# %d\t-\t%s\n',$Image->get(id),$Image->get(name));
    unset($Image);
} catch (Exception $e) {
    print $e->getMessage();
}
