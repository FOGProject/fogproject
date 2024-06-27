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
    Route::ids(
        'snapintask',
        $find,
        'snapinID'
    );
    $snapins = json_decode(
        Route::getData()
    );
    $snapinIDs = [];
    foreach ($snapins as $snapin) {
        $snapinIDs[] = $snapin->snapinID;
    }
    if (isset($_REQUEST['getSnapnames'])) {
        Route::ids(
            'snapin',
            ['id' => $snapinIDs],
            'name'
        );
        $snapins = json_decode(
            Route::getData()
        );
        $snapinnames = [];
        foreach ($snapins as $snapin) {
            $snapinnames[] = $snapin->name;
        }
    } elseif (isset($_REQUEST['getSnapargs'])) {
        Route::ids(
            'snapin',
            ['id' => $snapinIDs],
            'args'
        );
        $snapins = json_decode(
            Route::getData()
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
