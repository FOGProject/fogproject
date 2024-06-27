<?php
/**
 * Inventory, stores the host inventory.
 *
 * PHP version 5
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Inventory, stores the host inventory.
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
FOGCore::stripAndDecode($_REQUEST);
try {
    FOGCore::getHostItem(
        false,
        false,
        false,
        false,
        false,
        $_REQUEST['mac']
    );
    $Inventory = FOGCore::$Host->get('inventory');
    if (!$Inventory instanceof Inventory
        || !$Inventory->isValid()
    ) {
        $Inventory = FOGCore::getClass('Inventory')
            ->set('hostID', FOGCore::$Host->get('id'));
    }
    foreach ($_REQUEST as $var => $val) {
        if ($var == 'hdinfo') {
            preg_match(
                '#model=(.*?),#i',
                $val,
                $hdmodel
            );
            preg_match(
                '#fwrev=(.*?),#i',
                $val,
                $hdfirmware
            );
            preg_match(
                '#serialno=.*#i',
                $val,
                $hdserial
            );
            $hdmodel = (
                count($hdmodel) > 1 ?
                trim($hdmodel[1]) :
                ''
            );
            $hdfirmware = (
                count($hdfirmware) > 1 ?
                trim($hdfirmware[1]) :
                ''
            );
            $hdserial = (
                count($hdserial) ?
                trim(
                    str_ireplace(
                        'serialno=',
                        '',
                        trim($hdserial[0])
                    )
                ) :
                ''
            );
            $Inventory
                ->set('hdmodel', $hdmodel)
                ->set('hdfirmware', $hdfirmware)
                ->set('hdserial', $hdserial);
            continue;
        }
        $Inventory->set($var, $val);
    }
    if (!$Inventory->save()) {
        throw new Exception(
            _('Failed to create inventory for this host')
        );
    }
    echo _('Done');
} catch (Exception $e) {
    echo $e->getMessage();
}
exit;
