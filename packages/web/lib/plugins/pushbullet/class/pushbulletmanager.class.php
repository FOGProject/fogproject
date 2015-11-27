<?php
class PushbulletManager extends FOGManagerController {
    public function install($name) {
        $sql = "CREATE TABLE pushbullet
            (pID INTEGER NOT NULL AUTO_INCREMENT,
            pToken VARCHAR(250) NOT NULL,
            pName VARCHAR(250) NOT NULL,
            pEmail VARCHAR(250) NOT NULL,
            PRIMARY KEY(pID),
        KEY new_index (pToken))
        ENGINE = MyISAM";
        return $this->DB->query($sql)->fetch()->get();
    }
    public function uninstall() {
        return $this->DB->query("DROP TABLE pushbullet")->fetch()->get();
    }
}
