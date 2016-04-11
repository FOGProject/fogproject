<?php
class PushbulletManager extends FOGManagerController {
    public function install($name) {
        $this->uninstall();
        $sql = "CREATE TABLE `pushbullet`
            (`pID` INTEGER NOT NULL AUTO_INCREMENT,
            `pToken` VARCHAR(250) NOT NULL,
            `pName` VARCHAR(250) NOT NULL,
            `pEmail` VARCHAR(250) NOT NULL,
            PRIMARY KEY(`pID`),
        KEY new_index (`pToken`))
        ENGINE = MyISAM";
        return self::$DB->query($sql)->fetch()->get();
    }
    public function uninstall() {
        return self::$DB->query("DROP TABLE IF EXISTS `pushbullet`")->fetch()->get();
    }
}
