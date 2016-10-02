<?php
/**
 * Returns a listing of all snapins in the system.
 *
 * PHP version 5
 *
 * @category Snapinlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all snapins in the system.
 *
 * @category Snapinlisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    $snapinCount = FOGCore::getClass('SnapinManager')
        ->count();
    if ($snapinCount < 1) {
        throw new Exception(
            _('There are no snapins on this server')
        );
    }
    $snapinids = FOGCore::getSubObjectIDs('Snapin');
    $snapinnames = FOGCore::getSubObjectIDs(
        'Snapin',
        array('id' => $snapinids),
        'name'
    );
    foreach ((array)$snapinids as $index => $snapinid) {
        printf(
            '\tID# %d\t-\t%s\n',
            $snapinid,
            $snapinnames[$index]
        );
        unset(
            $snapinid,
            $snapinnames[$index],
            $snapinids[$index]
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
