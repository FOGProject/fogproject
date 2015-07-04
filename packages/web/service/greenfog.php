<?php
require_once('../commons/base.inc.php');
try {
    $index = 0;
    if ($FOGCore->getClass(GreenFogManager)->count()) {
        foreach($FOGCore->getClass(GreenFogManager)->find() AS &$gf) {
            $Datatosend .= ($_REQUEST[newService] ? ($index == 0 ? "#!ok\n" : '')."#task$index=".$gf->get(hour).'@'.$gf->get('min').'@'.$gf->get(action) : base64_encode($gf->get(hour).'@'.$gf->get('min').'@'.$gf->get(action)))."\n";
            $index++;
        }
        unset($gf);
    } else $Datatosend = '#!na';
    $FOGCore->sendData($Datatosend);
} catch (Exception $e) {
    print $e->getMessage();
    exit;
}
