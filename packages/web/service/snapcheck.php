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
    $Host = $FOGCore
        ->getHostItem(false);
    if (!$Host->isValid()) {
        throw new Exception('#!ih');
    }
    $SnapinJob = $Host
        ->get('snapinjob');
    if (!$SnapinJob->isValid()) {
        throw new Exception(
            _('Invalid Snapin Job')
        );
    }
    $snapinids = FOGCore::getSubObjectIDs(
        'SnapinTask',
        array(
            'stateID' => $FOGCore->getQUeuedStates(),
            'jobID' => $SnapinJob->get('id')
        ),
        'snapinID'
    );
    if ($_REQUEST['getSnapnames']) {
        $snapins = FOGCore::getSubObjectIDs(
            'Snapin',
            array('id' => $snapinids),
            'name'
        );
    } elseif ($_REQUEST['getSnapargs']) {
        $snapins = FOGCore::getSubObjectIDs(
            'Snapin',
            array('id' => $snapinids),
            'args'
        );
    } else {
        $Snapins = (
            FOGCore::getClass('SnapinTaskManager')
            ->count(
                array(
                    'stateID' => $FOGCore->getQueuedStates(),
                    'jobID' => $SnapinJob->get('id')
                )
            ) ?
            1 :
            0
        );
    }
    echo implode(' ', (array)$Snapins);
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
