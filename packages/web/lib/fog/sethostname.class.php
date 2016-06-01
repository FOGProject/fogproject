<?php
class SetHostName extends FOGBase {
    protected $macSimple;
    protected $newName;
    protected $oldName;

    public function __construct($check = false) {
        parent::__construct();

        self::stripAndDecode($_REQUEST);
        $this->macSimple = strtolower(str_replace(array(':','-'),':',substr($_REQUEST['mac'],0,20)));
        $this->newName = substr(trim($_REQUEST['newname']," \t\n\r\0"),0,20);
        $this->oldName = substr(trim($_REQUEST['oldname']," \t\n\r\0"),0,20);

        ob_start();
        header('Content-Type: text/plain');
        header('Connection: close');

        if ((strlen($this->newName) > 3) & (strlen($this->oldName) > 0)) {
            $query = sprintf("UPDATE hosts JOIN hostMAC ON (hostMAC.hmHostID = hosts.hostID) SET hostName='%s' WHERE ( (hostMAC.hmMAC='%s') AND (hostName LIKE '%s') );", $this->newName, $this->macSimple, $this->oldName);

            #echo $query;
            self::$DB->query($query);
            echo "OK";

        } else {
            echo "Fail";
        }
        flush();
        ob_flush();
        ob_end_flush();
    }
}
﻿
