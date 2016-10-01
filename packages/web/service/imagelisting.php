<?php
/**
 * Returns a listing of all images in the system.
 *
 * PHP version 5
 *
 * @category Imagelisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all images in the system.
 *
 * @category Imagelisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $imageCount = FOGCore::getClass('ImageManager')
        ->count();
    if ($imageCount < 1) {
        throw new Exception(
            _('There are no images on this server')
        );
    }
    $imageids = FOGCore::getSubObjectIDs('Image');
    $imagenames = FOGCore::getSubObjectIDs(
        'Image',
        array('id' => $imageids),
        'name'
    );
    foreach ((array)$imageids as $index => $imageid) {
        printf(
            '\tID# %d\t-\t%s\n',
            $imageid,
            $imagenames[$index]
        );
        unset(
            $imageid,
            $imagenames[$index],
            $imageids[$index]
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
