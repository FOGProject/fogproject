<?php
class WolbroadcastManager extends FOGManagerController {
    public function install($name) {
        $sql = "CREATE TABLE wolbroadcast
            (wbID INTEGER NOT NULL AUTO_INCREMENT,
            wbName VARCHAR(250) NOT NULL,
            wbDesc longtext NOT NULL,
            wbBroadcast VARCHAR(16) NOT NULL,
            PRIMARY KEY(wbID),
        INDEX new_index (wbID))
        ENGINE = MyISAM";
        if (!$this->DB->query($sql)) return false;
        return true;
    }
    public function uninstall() {
        if (!$this->DB->query("DROP TABLE wolbroadcast")) return false;
        return true;
    }
}
