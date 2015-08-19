<?php
require_once('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false,true);
    $sysman=trim(base64_decode($_REQUEST[sysman]));
    $sysproduct=trim(base64_decode($_REQUEST[sysproduct]));
    $sysversion=trim(base64_decode($_REQUEST[sysversion]));
    $sysserial=trim(base64_decode($_REQUEST[sysserial]));
    $systype=trim(base64_decode($_REQUEST[systype]));
    $biosversion=trim(base64_decode($_REQUEST[biosversion]));
    $biosvendor=trim(base64_decode($_REQUEST[biosvendor]));
    $biosdate=trim(base64_decode($_REQUEST[biosdate]));
    $mbman=trim(base64_decode($_REQUEST[mbman]));
    $mbproductname=trim(base64_decode($_REQUEST[mbproductname]));
    $mbversion=trim(base64_decode($_REQUEST[mbversion]));
    $mbserial=trim(base64_decode($_REQUEST[mbserial]));
    $mbasset=trim(base64_decode($_REQUEST[mbasset]));
    $cpuman=trim(base64_decode($_REQUEST[cpuman]));
    $cpuversion=trim(base64_decode($_REQUEST[cpuversion]));
    $cpucurrent=trim(base64_decode($_REQUEST[cpucurrent]));
    $cpumax=trim(base64_decode($_REQUEST[cpumax]));
    $mem=trim(base64_decode($_REQUEST[mem]));
    $hdinfo=trim(base64_decode($_REQUEST[hdinfo]));
    if ($hdinfo != null) {
        $arHd = explode(',',$hdinfo);
        $hdmodel = trim(str_replace("Model=","",trim($arHd[0])));
        $hdfirmware = trim(str_replace("FwRev=","",trim($arHd[1])));
        $hdserial = trim(str_replace("SerialNo=","",trim($arHd[2])));
    } else {
        $hdmodel = '';
        $hdfirmware = '';
        $hdserial = '';
    }
    $caseman=trim(base64_decode($_REQUEST[caseman]));
    $casever=trim(base64_decode($_REQUEST[casever]));
    $caseserial=trim(base64_decode($_REQUEST[caseserial]));
    $casesasset=trim(base64_decode($_REQUEST[casesasset]));
    $Inventory = $Host->get(inventory)
        ->set(hostID,$Host->get(id))
        ->set(sysman,$sysman)
        ->set(sysproduct,$sysproduct)
        ->set(sysversion,$sysversion)
        ->set(sysserial,$sysserial)
        ->set(systype,$systype)
        ->set(biosversion,$biosversion)
        ->set(biosvendor,$biosvendor)
        ->set(biosdate,$biosdate)
        ->set(mbman,$mbman)
        ->set(mbproductname,$mbproductname)
        ->set(mbversion,$mbversion)
        ->set(mbserial,$mbserial)
        ->set(mbasset,$mbasset)
        ->set(cpuman,$cpuman)
        ->set(cpuversion,$cpuversion)
        ->set(cpucurrent,$cpucurrent)
        ->set(cpumax,$cpumax)
        ->set(mem,$mem)
        ->set(hdmodel,$hdmodel)
        ->set(hdfirmware,$hdfirmware)
        ->set(hdserial,$hdserial)
        ->set(caseman,$caseman)
        ->set(casever,$casever)
        ->set(caseserial,$caseserial)
        ->set(caseasset,$casesasset);
    if (!$Inventory->save()) throw new Exception(_('Failed to create inventory for this host!'));
    print _('Done');
} catch (Exception $e) {
    print $e->getMessage();
}
