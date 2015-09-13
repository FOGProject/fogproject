<?php
require_once('../commons/base.inc.php');
try {
    // Get the mode.
    if (trim($_REQUEST[mode]) != array('q','s')) throw new Exception(_('Invalid operational mode'));
    // Get the info
    $string = explode(':',base64_decode($_REQUEST['string']));
    $vInfo = explode(' ',trim($string[1]));
    $Virus = $FOGCore->getClass(Virus)
        ->set(name,$vInfo[0])
        ->set(hostMAC,strtolower($FOGCore->getHostItem(false)->get(mac)))
        ->set('file',$string[0])
        ->set('date',$FOGCore->formatTime('now','Y-m-d H:i:s'))
        ->set(mode,$_REQUEST[mode]);
    if ($Virus->save()) throw new Exception(_('Accepted'));
    else throw new Exception(_('Failed'));
} catch (Exception $e) {
    echo $e->getMessage();
}
