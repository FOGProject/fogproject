<?php
class HostInfo extends FOGBase {
    protected $macSimple;
    protected $repFields = array(
        'hostName' => 'hostname',
        'hostDesc' => 'hostdesc',
        'imageOSID' => 'imageosid',
        'imagePath' => 'imagepath',
        'hostUseAD' => 'hostusead',
        'hostADDomain' => 'hostaddomain',
        'hostADOU' => 'hostadou',
        'hostProductKey' => 'hostproductkey',
        'iPrimaryUser' => 'primaryuser',
        'iOtherTag' => 'othertag',
        'iOtherTag1' => 'othertag1',
        'lName' => 'location',
        'iSysman' => 'sysman',
        'iSysproduct' => 'sysproduct',
        'iSysserial' => 'sysserial',
        'iMbman' => 'mbman',
        'iMbserial' => 'mbserial',
        'iMbasset' => 'mbasset',
        'iMbproductname' => 'mbproductname',
        'iCaseman' => 'caseman',
        'iCaseserial' => 'caseserial',
        'iCaseasset' => 'caseasset',
    );

    public function __construct($check = false) {
        parent::__construct();

        self::stripAndDecode($_REQUEST);
        $this->macSimple = strtolower(str_replace(array(':','-'),':',substr($_REQUEST['mac'],0,20)));

        $query = sprintf("SELECT hostName,hostDesc,imageOSID,imagePath,hostUseAD,hostADDomain,hostADOU,hostProductKey,iPrimaryUser,iOtherTag,iOtherTag1,lName,iSysman,iSysproduct,iSysserial,iMbman,iMbserial,iMbasset,iMbproductname,iCaseman,iCaseserial,iCaseasset FROM (((hostMAC INNER JOIN (hosts LEFT JOIN images ON hosts.hostImage = images.imageID) ON hostMAC.hmHostID = hosts.hostID) LEFT JOIN inventory ON hosts.hostID = inventory.iHostID) LEFT JOIN locationAssoc ON hosts.hostID = locationAssoc.laHostID) LEFT JOIN location ON locationAssoc.laLocationID = location.lID WHERE (hostMAC.hmMAC='%s');", $this->macSimple);

        $tmp = (array)self::$DB->query($query)->fetch('','fetch_all')->get();

        ob_start();
        header('Content-Type: text/plain');
        header('Connection: close');

        foreach ((array)$tmp AS $i => &$DataRow) {
            foreach ((array)$DataRow AS $j => &$DataField) {
                echo  "export " . $this->repFields[$j] . "=\"" . $DataField . "\"\n";
                unset($DataField);
            }
            unset($DataRow);
        };
        flush();
        ob_flush();
        ob_end_flush();
    }
}
﻿
