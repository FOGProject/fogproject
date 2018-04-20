<?php
/**
 * Returns a listing of all groups in the system.
 *
 * PHP version 5
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
    Route::names('group');
    $groupnames = json_decode(
        Route::getData()
    );
    if (count($groupnames ?: []) < 1) {
        throw new Exception(
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
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
