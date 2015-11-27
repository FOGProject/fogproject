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
        return $this->DB->query($sql)->fetch()->get();
    }
    public function uninstall() {
        return $this->DB->query("DROP TABLE wolbroadcast")->fetch()->get();
    }
}
