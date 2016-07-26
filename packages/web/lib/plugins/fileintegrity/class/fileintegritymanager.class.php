<?php
class FileIntegrityManager extends FOGManagerController {
    public function install($name) {
        $this->uninstall();
        $sql = "CREATE TABLE `fileChecksums` (
            `fcsID` INTEGER NOT NULL AUTO_INCREMENT,
            `fcsStorageNodeID` INTEGER NOT NULL,
            `fcsFileModTime` DATETIME NOT NULL,
            `fcsFileChecksum` VARCHAR(255) NOT NULL,
            `fcsFilePath` VARCHAR(255) NOT NULL,
            `fcsStatus` ENUM('0','1','2') NOT NULL,
            PRIMARY KEY(`fcsID`),
            UNIQUE INDEX `nodeFiles` (`fcsStorageNodeID`,`fcsFilePath`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC";
        return self::$DB->query($sql);
    }
    public function uninstall() {
        return self::$DB->query("DROP TABLE IF EXISTS `fileChecksums`");
    }
}
