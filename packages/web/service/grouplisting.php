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
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../commons/base.inc.php';
try {
    // getNames(): names() answers with its rows under a `data` envelope, and
    // this wants the rows. It also raises a router failure into the catch
    // below rather than reaching breakHead()'s exit. See ADR 0011.
    $groupnames = Route::getNames('group');
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
