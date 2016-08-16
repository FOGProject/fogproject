<?php
require('../commons/base.inc.php');
FOGCore::stripAndDecode($_REQUEST);
try {
    $Host = $FOGCore->getHostItem(false, false);
    $Inventory = $Host->get('inventory')
        ->set('hostID', $Host->get('id'));
    foreach ($_REQUEST as $var => &$val) {
        if ($var == 'hdinfo') {
            preg_match('#model=(.*?),#i', $val, $hdmodel);
            preg_match('#fwrev=(.*?),#i', $val, $hdfirmware);
            preg_match('#serialno=.*#i', $val, $hdserial);
            $hdmodel = count($hdmodel) > 1 ? trim($hdmodel[1]) : '';
            $hdfirmware = count($hdfirmware) > 1 ? trim($hdfirmware[1]) : '';
            $hdserial = count($hdserial) ? trim(str_ireplace('serialno=', '', trim($hdserial[0]))) : '';
            $Inventory
                ->set('hdmodel', $hdmodel)
                ->set('hdfirmware', $hdfirmware)
                ->set('hdserial', $hdserial);
            unset($var, $val);
            continue;
        }
        $Inventory->set($var, $val);
        unset($var, $val);
    }
    if (!$Inventory->save()) {
        throw new Exception(_('Failed to create inventory for this host!'));
    }
    echo _('Done');
} catch (Exception $e) {
    echo $e->getMessage();
}
