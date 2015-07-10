<?php
class PushbulletManager extends FOGManagerController {
    /**	install($name)
        Method that installs the relevant plugin.

        $name just sends the plugin name.  Useful
        for schema adding.
     */
    public function install($name) {
        $sql = "CREATE TABLE pushbullet
            (pID INTEGER NOT NULL AUTO_INCREMENT,
            pToken VARCHAR(250) NOT NULL,
            pName VARCHAR(250) NOT NULL,
            pEmail VARCHAR(250) NOT NULL,
            PRIMARY KEY(pID),
        KEY new_index (pToken))
        ENGINE = MyISAM";
        if (!$this->DB->query($sql)) return false;
        return true;
    }
    public function uninstall() {
        if (!$this->DB->query("DROP TABLE pushbullet")) return false;
        return true;
    }
}
