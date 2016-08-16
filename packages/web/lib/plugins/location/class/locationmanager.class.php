<?php
class LocationManager extends FOGManagerController
{
    public function install($name)
    {
        $this->uninstall();
        $sql = "CREATE TABLE `location`
            (`lID` INTEGER NOT NULL AUTO_INCREMENT,
            `lName` VARCHAR(250) NOT NULL,
            `lDesc` longtext NOT NULL,
            `lStorageGroupID` INTEGER NOT NULL,
            `lStorageNodeID` INTEGER NOT NULL,
            `lCreatedBy` VARCHAR(30) NOT NULL,
            `lTftpEnabled` VARCHAR(1) NOT NULL,
            `lCreatedTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(`lID`),
        KEY new_index (`lName`),
        KEY new_index1 (`lStorageGroupID`))
        ENGINE = MyISAM";
        if (!self::$DB->query($sql)) {
            return false;
        }
        $sql = "CREATE TABLE `locationAssoc`
            (`laID` INTEGER NOT NULL AUTO_INCREMENT,
            `laLocationID` INTEGER NOT NULL,
            `laHostID` INTEGER NOT NULL,
            PRIMARY KEY (`laID`),
            KEY new_index (`laHostID`))
            ENGINE=MyISAM";
        return self::$DB->query($sql);
    }
    public function uninstall()
    {
        $res = true;
        self::getClass('Service')->set('name', 'FOG_SNAPIN_LOCATION_SEND_ENABLED')->load('name')->destroy();
        if (!self::$DB->query("DROP TABLE IF EXISTS `locationAssoc`")) {
            $res = false;
        }
        if (!self::$DB->query("DROP TABLE IF EXISTS `location`")) {
            $res = false;
        }
        return $res;
    }
}
