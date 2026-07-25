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
    $snapinIDs = $snapins;
    if (isset($_REQUEST['getSnapnames'])) {
        $snapins = Route::getIds(
            'snapin',
            ['id' => $snapinIDs],
            'name'
        );
        $snapinnames = $snapins;
    } elseif (isset($_REQUEST['getSnapargs'])) {
        $snapins = Route::getIds(
            'snapin',
            ['id' => $snapinIDs],
            'args'
        );
        $snapinnames = $snapins;
    } else {
        $snapinnames = [count($snapins ?: []) ? 1 : 0];
    }
    echo implode(' ', $snapinnames);
} catch (Exception $e) {
    // Aisle 021: escape the error text at the sink. Today the reflected MAC is
    // already neutralised upstream by stripAndDecodeItem(), so this is inert --
    // but that makes the safety of this echo an accident of a shared helper.
    // Escaping here keeps the sink safe if that helper is ever "fixed" for the
    // cosmetic double-encoding. Only the catch path: the success echo above
    // carries snapin names/args (quotes, ampersands) and must stay raw.
    echo Initiator::e($e->getMessage());
}
exit;
