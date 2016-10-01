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
    $locationCount = FOGCore::getClass('LocationManager')
        ->count();
    if ($locationCount < 0) {
        throw new Exception(
            _('There are no locations on this server')
        );
    }
    $locationids = FOGCore::getSubObjectIDs('Location');
    $locationnames = FOGCore::getSubObjectIDs(
        'Location',
        array('id' => $locationids),
        'name'
    );
    foreach ((array)$locationids as $index => $locationid) {
        printf(
            '\tID# %d\t-\t%s\n',
            $locationid,
            $locationnames[$index]
        );
        unset(
            $locationid,
            $locationnames[$index],
            $locationids[$index]
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
