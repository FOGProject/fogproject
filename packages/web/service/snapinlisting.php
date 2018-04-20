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
    Route::names('snapin');
    $snapinnames = json_decode(
        Route::getData()
    );
    if (count($snapinnames ?: []) < 1) {
        throw new Exception(
            _('There are no snapins on this server')
        );
    }
    foreach ($snapinnames as &$snapin) {
        printf(
            '\tID# %d\t-\t%s\n',
            $snapin->id,
            $snapin->name
        );
        unset($snapin);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
