<?php
/**
 * Returns a listing of all locations in the system.
 *
 * PHP version 5
 *
 * @category Locationlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all locations in the system.
 *
 * @category Locationlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    Route::names('location');
    $locationnames = json_decode(
        Route::getData()
    );
    if (count($locationnames ?: []) < 1) {
        throw new Exception(
            _('There are no locations on this server')
        );
    }
    foreach ($locationnames as &$location) {
        printf(
            '\tID# %d\t-\t%s\n',
            $location->id,
            $location->name
        );
        unset($location);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
