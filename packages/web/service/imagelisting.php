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
    Route::names('image');
    $imagenames = json_decode(
        Route::getData()
    );
    if (count($imagenames ?: []) < 1) {
        throw new Exception(
            _('There are no images on this server')
        );
    }
    foreach ($imagenames as &$image) {
        printf(
            '\tID# %d\t-\t%s\n',
            $image->id,
            $image->name
        );
        unset($image);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
