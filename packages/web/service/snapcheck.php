<?php
/**
 * Checks the snapin.
 *
 * PHP version 5
 *
 * @category SnapinCheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Checks the snapin.
 *
 * @category SnapinCheck
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
try {
    FOGCore::getHostItem(false);
    if (!FOGCore::$Host->isValid()) {
        throw new Exception('#!ih');
    }
    $SnapinJob = FOGCore::$Host->get('snapinjob');
    if (!$SnapinJob->isValid()) {
        throw new Exception(0);
    }
    $find = [
        'stateID' => $FOGCore->getQueuedStates(),
        'jobID' => $SnapinJob->get('id')
    ];
    $snapins = Route::getIds(
        'snapintask',
        $find,
        'snapinID'
    );
    $snapinIDs = [];
    foreach ($snapins as $snapin) {
        $snapinIDs[] = $snapin->snapinID;
    }
    if (isset($_REQUEST['getSnapnames'])) {
        $snapins = Route::getIds(
            'snapin',
            ['id' => $snapinIDs],
            'name'
        );
        $snapinnames = [];
        foreach ($snapins as $snapin) {
            $snapinnames[] = $snapin->name;
        }
    } elseif (isset($_REQUEST['getSnapargs'])) {
        $snapins = Route::getIds(
            'snapin',
            ['id' => $snapinIDs],
            'args'
        );
        $snapinnames = [];
        foreach ($snapins as $snapin) {
            $snapinnames[] = $snapin->args;
        }
    } else {
        $snapinnames = [count($snapins ?: []) ? 1 : 0];
    }
    echo implode(' ', $snapinnames);
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
