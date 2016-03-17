<?php
class SlackManager extends FOGManagerController {
    public function install($name) {
        $this->uninstall();
        $sql = "CREATE TABLE `slack`
            (`sID` INTEGER NOT NULL AUTO_INCREMENT,
            `sToken` VARCHAR(250) NOT NULL,
            `sUsername` VARCHAR(250) NOT NULL,
            PRIMARY KEY(`sID`),
        KEY new_index (`sToken`))
        ENGINE = MyISAM";
        return self::$DB->query($sql)->fetch()->get();
    }
    public function uninstall() {
        return self::$DB->query("DROP TABLE IF EXISTS `slack`")->fetch()->get();
    }
}
