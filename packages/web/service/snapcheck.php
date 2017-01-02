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
    $Host = FOGCore::getHostItem(false);
    if (!$Host->isValid()) {
        throw new Exception('#!ih');
    }
    $SnapinJob = $Host
        ->get('snapinjob');
    if (!$SnapinJob->isValid()) {
        throw new Exception(0);
    }
    $snapinids = FOGCore::getSubObjectIDs(
        'SnapinTask',
        array(
            'stateID' => $FOGCore->getQUeuedStates(),
            'jobID' => $SnapinJob->get('id')
        ),
        'snapinID'
    );
    if (isset($_REQUEST['getSnapnames'])) {
        $snapins = FOGCore::getSubObjectIDs(
            'Snapin',
            array('id' => $snapinids),
            'name'
        );
    } elseif (isset($_REQUEST['getSnapargs'])) {
        $snapins = FOGCore::getSubObjectIDs(
            'Snapin',
            array('id' => $snapinids),
            'args'
        );
    } else {
        $snapins = (
            FOGCore::getClass('SnapinTaskManager')
            ->count(
                array(
                    'stateID' => FOGCore::getQueuedStates(),
                    'jobID' => $SnapinJob->get('id')
                )
            ) ?
            1 :
            0
        );
    }
    echo implode(' ', (array)$snapins);
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
