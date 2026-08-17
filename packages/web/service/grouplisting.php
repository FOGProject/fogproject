<?php
/**
 * Returns a listing of all groups in the system.
 *
 * PHP version 7.4+
 *
 * @category Grouplisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Returns a listing of all groups in the system.
 *
 * @category Grouplisting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    // asValue(): names() has no wrapper, and its payload is a bare list with
    // no envelope to unwrap. What this buys is that a router failure raises
    // into the catch below rather than reaching breakHead()'s exit. See
    // ADR 0011.
    $groupnames = Route::asValue(
        function () {
            Route::names('group');
        }
    );
    if (count((array)$groupnames) < 1) {
        throw new \Exception(
            _('There are no groups on this server')
        );
    }
    foreach ($groupnames as $group) {
        printf(
            '\tID# %d\t-\t%s\n',
            $group->id,
            $group->name
        );
        unset($group);
    }
} catch (\Exception $e) {
    echo $e->getMessage();
}
exit;
