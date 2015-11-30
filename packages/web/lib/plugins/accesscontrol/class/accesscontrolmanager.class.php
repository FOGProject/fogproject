<?php
class AccesscontrolManager extends FOGManagerController {
    public function install($name) {
        $this->uninstall();
        $sql = "CREATE TABLE `accessControls`
            (`acID` INTEGER NOT NULL AUTO_INCREMENT,
            `acName` VARCHAR(250) NOT NULL,
            `acDesc` longtext NOT NULL,
            `acOther` VARCHAR(250) NOT NULL,
            `acUserID` INTEGER NOT NULL,
            `acGroupID` INTEGER NOT NULL,
            PRIMARY KEY(`acID`),
        INDEX new_index (`acUserID`),
        INDEX new_index2 (`acGroupID`))
        ENGINE = MyISAM";
        return $this->DB->query($sql)->fetch()->get();
    }
    public function uninstall() {
        return $this->DB->query("DROP TABLE IF EXISTS `accessControls`")->fetch()->get();
    }
}
