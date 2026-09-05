<?php
/**
 * Schema layout for creating the database.
 *
 * PHP version 7.4+
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Audit\Audit;
use FOG\Db\DatabaseManager;
use FOG\Items\Image;
use FOG\Items\OS;
use FOG\Items\Schema;
use FOG\Items\TaskLog;

/**
 * Schema layout for creating the database.
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$tmpSchema = new Schema();
self::$DB->query(Schema::useDatabaseQuery());
/**
 * GH-529: FOG_WEB_ROOT used to be seeded with a literal '/fog/' regardless of
 * where the installer was actually told to put the web files, so a custom
 * -W/--webroot left the database pointing somewhere the app did not live --
 * which is what broke PXE booting. WEB_ROOT is written into config.class.php
 * by the installer alongside WEB_HOST.
 *
 * The guard is for config.class.php files generated before that constant
 * existed: the installer rewrites the file on every run, but the schema page
 * can be reached without re-running it.
 */
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', '/fog/');
}
// 0
$this->schema[] = [
    Schema::createDatabaseQuery(),
    Schema::useDatabaseQuery(),
    'CREATE TABLE `groupMembers` ('
    . '`gmID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`gmHostID` INT(11) NOT NULL,'
    . '`gmGroupID` INT(11) NOT NULL,'
    . 'PRIMARY KEY (`gmID`),'
    . 'UNIQUE KEY `gmHostID` (`gmHostID`,`gmGroupID`),'
    . 'UNIQUE KEY `gmGroupID` (`gmHostID`,`gmGroupID`),'
    . 'KEY `new_index` (`gmHostID`),'
    . 'KEY `new_index1` (`gmGroupID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `groups` ('
    . '`groupID` INT(11) NOT NULL auto_increment,'
    . '`groupName` VARCHAR(50) NOT NULL,'
    . '`groupDesc` LONGTEXT NOT NULL,'
    . '`groupDateTime` DATETIME NOT NULL,'
    . '`groupCreateBy` VARCHAR(50) NOT NULL,'
    . '`groupBuilding` INT(11) NOT NULL,'
    . 'PRIMARY KEY (`groupID`),'
    . 'KEY `new_index` (`groupName`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `history` ('
    . '`hID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`hText` LONGTEXT NOT NULL,'
    . '`hUser` VARCHAR(200) NOT NULL,'
    . '`hTime` DATETIME NOT NULL,'
    . '`hIP` VARCHAR(50) NOT NULL,'
    . 'PRIMARY KEY (`hID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `hosts` ('
    . '`hostID` int(11) NOT NULL auto_increment,'
    . '`hostName` varchar(16) NOT NULL,'
    . '`hostDesc` longtext NOT NULL,'
    . '`hostIP` varchar(25) NOT NULL,'
    . '`hostImage` int(11) NOT NULL,'
    . '`hostBuilding` int(11) NOT NULL,'
    . '`hostCreateDate` datetime NOT NULL,'
    . '`hostCreateBy` varchar(50) NOT NULL,'
    . '`hostMAC` varchar(20) NOT NULL,'
    . '`hostOS` int(10) unsigned NOT NULL,'
    . 'PRIMARY KEY  (`hostID`),'
    . 'KEY `new_index` (`hostName`),'
    . 'KEY `new_index1` (`hostIP`),'
    . 'KEY `new_index2` (`hostMAC`),'
    . 'KEY `new_index3` (`hostOS`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `images` ('
    . '`imageID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`imageName` VARCHAR(40) NOT NULL,'
    . '`imageDesc` LONGTEXT NOT NULL,'
    . '`imagePath` LONGTEXT NOT NULL,'
    . '`imageDateTime` DATETIME NOT NULL,'
    . '`imageCreateBy` VARCHAR(50) NOT NULL,'
    . '`imageBuilding` int(11) NOT NULL,'
    . '`imageSize` VARCHAR(200) NOT NULL,'
    . 'PRIMARY KEY  (`imageID`),'
    . 'KEY `new_index` (`imageName`),'
    . 'KEY `new_index1` (`imageBuilding`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `schemaVersion` ('
    . '`vID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`vValue` INT(11) NOT NULL,'
    . 'PRIMARY KEY  (`vID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `supportedOS` ('
    . '`osID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
    . '`osName` VARCHAR(150) NOT NULL,'
    . '`osValue` int(10) unsigned NOT NULL,'
    . 'PRIMARY KEY  (`osID`),'
    . 'KEY `new_index` (`osValue`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE  `tasks` ('
    . '`taskID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`taskName` VARCHAR(250) NOT NULL,'
    . '`taskCreateTime` DATETIME NOT NULL,'
    . '`taskCheckIn` DATETIME NOT NULL,'
    . '`taskHostID` INT(11) NOT NULL,'
    . '`taskState` INT(11) NOT NULL,'
    . '`taskCreateBy` VARCHAR(200) NOT NULL,'
    . '`taskForce` VARCHAR(1) NOT NULL,'
    . '`taskScheduledStartTime` DATETIME NOT NULL,'
    . '`taskType` VARCHAR(1) NOT NULL,'
    . '`taskPCT` INT(10) UNSIGNED zerofill NOT NULL,'
    . 'PRIMARY KEY (`taskID`),'
    . 'KEY `new_index` (`taskHostID`),'
    . 'KEY `new_index1` (`taskCheckIn`),'
    . 'KEY `new_index2` (`taskState`),'
    . 'KEY `new_index3` (`taskForce`),'
    . 'KEY `new_index4` (`taskType`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `users` ('
    . '`uId` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`uName` VARCHAR(40) NOT NULL,'
    . '`uPass` VARCHAR(50) NOT NULL,'
    . '`uCreateDate` DATETIME NOT NULL,'
    . '`uCreateBy` VARCHAR(40) NOT NULL,'
    . 'PRIMARY KEY (`uId`),'
    . 'KEY `new_index` (`uName`),'
    . 'KEY `new_index1` (`uPass`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `users` VALUES ('','fog', MD5('password'), NOW(), '')",
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Windows XP', '1')",
    "INSERT IGNORE INTO `schemaVersion` VALUES ('', '1')"
];
// 2
$this->schema[] = [
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Windows Vista', '2')",
    "UPDATE `schemaVersion` SET vValue='2'",
];
// 3
$this->schema[] = [
    'ALTER TABLE `hosts`'
    . 'ADD COLUMN `hostUseAD` CHAR NOT NULL AFTER `hostOS`,'
    . 'ADD COLUMN `hostADDomain` VARCHAR(250) NOT NULL AFTER `hostUseAD`,'
    . 'ADD COLUMN `hostADOU` LONGTEXT NOT NULL AFTER `hostADDomain`,'
    . 'ADD COLUMN `hostADUser` VARCHAR(250) NOT NULL AFTER `hostADOU`,'
    . 'ADD COLUMN `hostADPass` VARCHAR(250) NOT NULL AFTER `hostADUser`,'
    . 'ADD COLUMN `hostAnon1` VARCHAR(250) NOT NULL AFTER `hostADPass`,'
    . 'ADD COLUMN `hostAnon2` VARCHAR(250) NOT NULL AFTER `hostAnon1`,'
    . 'ADD COLUMN `hostAnon3` VARCHAR(250) NOT NULL AFTER `hostAnon2`,'
    . 'ADD COLUMN `hostAnon4` VARCHAR(250) NOT NULL AFTER `hostAnon3`,'
    . 'ADD INDEX `new_index4` (`hostUseAD`)',
    'CREATE TABLE `snapinAssoc` ('
    . '`saID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`saHostID` INT(11) NOT NULL,'
    . '`saSnapinID` INT(11) NOT NULL,'
    . 'PRIMARY KEY  (`saID`),'
    . 'KEY `new_index` (`saHostID`),'
    . 'KEY `new_index1` (`saSnapinID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `snapinJobs` ('
    . '`sjID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`sjHostID` INT(11) NOT NULL,'
    . '`sjCreateTime` DATETIME NOT NULL,'
    . 'PRIMARY KEY (`sjID`),'
    . 'KEY `new_index` (`sjHostID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `snapinTasks` ('
    . '`stID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`stJobID` INT(11) NOT NULL,'
    . '`stState` INT(11) NOT NULL,'
    . '`stCheckinDate` DATETIME NOT NULL,'
    . '`stCompleteDate` DATETIME NOT NULL,'
    . '`stSnapinID` INT(11) NOT NULL,'
    . 'PRIMARY KEY (`stID`),'
    . 'KEY `new_index` (`stJobID`),'
    . 'KEY `new_index1` (`stState`),'
    . 'KEY `new_index2` (`stSnapinID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `snapins` ('
    . '`sID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`sName` VARCHAR(200) NOT NULL,'
    . '`sDesc` LONGTEXT NOT NULL,'
    . '`sFilePath` LONGTEXT NOT NULL,'
    . '`sArgs` LONGTEXT NOT NULL,'
    . '`sCreateDate` DATETIME NOT NULL,'
    . '`sCreator` VARCHAR(200) NOT NULL,'
    . '`sReboot` VARCHAR(1) NOT NULL,'
    . '`sAnon1` VARCHAR(45) NOT NULL,'
    . '`sAnon2` VARCHAR(45) NOT NULL,'
    . '`sAnon3` VARCHAR(45) NOT NULL,'
    . 'PRIMARY KEY (`sID`),'
    . 'KEY `new_index` (`sName`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET vValue='3'",
];
// 4
$this->schema[] = [
    'CREATE TABLE `multicastSessions` ('
    . '`msID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`msName` VARCHAR(250) NOT NULL,'
    . '`msBasePort` INT(11) NOT NULL,'
    . '`msLogPath` LONGTEXT NOT NULL,'
    . '`msImage` LONGTEXT NOT NULL,'
    . '`msClients` INT(11) NOT NULL,'
    . '`msInterface` VARCHAR(250) NOT NULL,'
    . '`msStartDateTime` DATETIME NOT NULL,'
    . '`msPercent` INT(11) NOT NULL,'
    . '`msState` INT(11) NOT NULL,'
    . '`msCompleteDateTime` DATETIME NOT NULL,'
    . '`msAnon1` VARCHAR(250) NOT NULL,'
    . '`msAnon2` VARCHAR(250) NOT NULL,'
    . '`msAnon3` VARCHAR(250) NOT NULL,'
    . '`msAnon4` VARCHAR(250) NOT NULL,'
    . '`msAnon5` VARCHAR(250) NOT NULL,'
    . 'PRIMARY KEY (`msID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `multicastSessionsAssoc` ('
    . '`msaID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`msID` INT(11) NOT NULL,'
    . '`tID` INT(11) NOT NULL,'
    . 'PRIMARY KEY  (`msaID`),'
    . 'KEY `new_index` (`msID`),'
    . 'KEY `new_index1` (`tID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` set vValue='4'",
];
// 5
$this->schema[] = [
    'ALTER TABLE `images`'
    . 'ADD COLUMN `imageDD` VARCHAR(1) NOT NULL AFTER `imageSize`,'
    . 'ADD INDEX `new_index2` (`imageDD`)',
    "UPDATE `supportedOS` SET `osName`='Windows 2000/XP' WHERE `osValue`='1'",
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Other', '99')",
    'ALTER TABLE `multicastSessions`'
    . 'CHANGE `msAnon1` `msIsDD` VARCHAR(1) NOT NULL',
    "UPDATE `schemaVersion` SET vValue='5'",
];
// 7
$this->schema[] = [
    'CREATE TABLE `virus` ('
    . '`vID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`vName` VARCHAR(250) NOT NULL,'
    . '`vHostMAC` VARCHAR(50) NOT NULL,'
    . '`vOrigFile` LONGTEXT NOT NULL,'
    . '`vDateTime` DATETIME NOT NULL,'
    . '`vMode` VARCHAR(5) NOT NULL,'
    . '`vAnon2` VARCHAR(50) NOT NULL,'
    . 'PRIMARY KEY (`vID`),'
    . 'INDEX `new_index` (`vHostMAC`),'
    . 'INDEX `new_index2`(`vDateTime`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET `vValue`='6'",
];
// 8
$this->schema[] = [
    'CREATE TABLE `userTracking` ('
    . '`utID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`utHostID` INTEGER NOT NULL,'
    . '`utUserName` VARCHAR(50) NOT NULL,'
    . '`utAction` VARCHAR(2) NOT NULL,'
    . '`utDateTime` DATETIME NOT NULL,'
    . '`utDesc` VARCHAR(250) NOT NULL,'
    . '`utDate` DATE NOT NULL,'
    . '`utAnon3` VARCHAR(2) NOT NULL,'
    . 'PRIMARY KEY (`utID`),'
    . 'INDEX `new_index` (`utHostID`),'
    . 'INDEX `new_index1` (`utUserName`),'
    . 'INDEX `new_index2` (`utAction`),'
    . 'INDEX `new_index3` (`utDateTime`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'ALTER TABLE `hosts`'
    . 'CHANGE `hostAnon1` `hostPrinterLevel` VARCHAR(2)'
    . 'CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
    'CREATE TABLE `printers` ('
    . '`pID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`pPort` LONGTEXT NOT NULL,'
    . '`pDefFile` LONGTEXT NOT NULL,'
    . '`pModel` VARCHAR(250) NOT NULL,'
    . '`pAlias` VARCHAR(250) NOT NULL,'
    . '`pConfig` VARCHAR(10) NOT NULL,'
    . '`pIP` VARCHAR(255) NOT NULL,'
    . '`pAnon2` VARCHAR(10) NOT NULL,'
    . '`pAnon3` VARCHAR(10) NOT NULL,'
    . '`pAnon4` VARCHAR(10) NOT NULL,'
    . '`pAnon5` VARCHAR(10) NOT NULL,'
    . 'PRIMARY KEY (`pID`),'
    . 'INDEX `new_index1`(`pModel`),'
    . 'INDEX `new_index2`(`pAlias`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `printerAssoc` ('
    . '`paID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`paHostID` INTEGER NOT NULL,'
    . '`paPrinterID` INTEGER NOT NULL,'
    . '`paIsDefault` VARCHAR(2) NOT NULL,'
    . '`paAnon1` VARCHAR(2) NOT NULL,'
    . '`paAnon2` VARCHAR(2) NOT NULL,'
    . '`paAnon3` VARCHAR(2) NOT NULL,'
    . '`paAnon4` VARCHAR(2) NOT NULL,'
    . '`paAnon5` VARCHAR(2) NOT NULL,'
    . 'PRIMARY KEY (`paID`),'
    . 'INDEX `new_index1` (`paHostID`),'
    . 'INDEX `new_index2` (`paPrinterID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `inventory` ('
    . '`iID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`iHostID` INT(11) NOT NULL,'
    . '`iPrimaryUser` VARCHAR(50) NOT NULL,'
    . '`iOtherTag` VARCHAR(50) NOT NULL,'
    . '`iOtherTag1` VARCHAR(50) NOT NULL,'
    . '`iCreateDate` DATETIME NOT NULL,'
    . '`iSysman` VARCHAR(250) NOT NULL,'
    . '`iSysproduct` VARCHAR(250) NOT NULL,'
    . '`iSysversion` VARCHAR(250) NOT NULL,'
    . '`iSysserial` VARCHAR(250) NOT NULL,'
    . '`iSystype` VARCHAR(250) NOT NULL,'
    . '`iBiosversion` VARCHAR(250) NOT NULL,'
    . '`iBiosvendor` VARCHAR(250) NOT NULL,'
    . '`iBiosdate` VARCHAR(250) NOT NULL,'
    . '`iMbman` VARCHAR(250) NOT NULL,'
    . '`iMbproductname` VARCHAR(250) NOT NULL,'
    . '`iMbversion` VARCHAR(250) NOT NULL,'
    . '`iMbserial` VARCHAR(250) NOT NULL,'
    . '`iMbasset` VARCHAR(250) NOT NULL,'
    . '`iCpuman` VARCHAR(250) NOT NULL,'
    . '`iCpuversion` VARCHAR(250) NOT NULL,'
    . '`iCpucurrent` VARCHAR(250) NOT NULL,'
    . '`iCpumax` VARCHAR(250) NOT NULL,'
    . '`iMem` VARCHAR(250) NOT NULL,'
    . '`iHdmodel` VARCHAR(250) NOT NULL,'
    . '`iHdfirmware` VARCHAR(250) NOT NULL,'
    . '`iHdserial` VARCHAR(250) NOT NULL,'
    . '`iCaseman` VARCHAR(250) NOT NULL,'
    . '`iCasever` VARCHAR(250) NOT NULL,'
    . '`iCaseserial` VARCHAR(250) NOT NULL,'
    . '`iCaseasset` VARCHAR(250) NOT NULL,'
    . 'PRIMARY KEY (`iID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `clientUpdates` ('
    . '`cuID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`cuName` VARCHAR(200) NOT NULL,'
    . '`cuMD5` VARCHAR(100) NOT NULL,'
    . '`cuType` VARCHAR(3) NOT NULL,'
    . '`cuFile` LONGBLOB NOT NULL,'
    . 'PRIMARY KEY (`cuID`),'
    . 'INDEX `new_index` (`cuName`),'
    . 'INDEX `new_index1`(`cuType`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET vValue='7'",
];
// 8
$this->schema[] = [
    "INSERT IGNORE INTO `supportedOS` (`osName`, `osValue`) VALUES "
    . "('Windows 98','3'),"
    . "('Windows (other)','4'),"
    . "('Linux','50')",
    "ALTER TABLE `multicastSessions` MODIFY COLUMN `msIsDD` INTEGER NOT NULL",
    "UPDATE `schemaVersion` SET vValue='8'",
];
// 9
$this->schema[] = [
    'CREATE TABLE `globalSettings` ('
    . '`settingID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`settingKey` VARCHAR(254) NOT NULL,'
    . '`settingDesc` LONGTEXT NOT NULL,'
    . '`settingValue` VARCHAR(254) NOT NULL,'
    . '`settingCategory` VARCHAR(254) NOT NULL,'
    . 'PRIMARY KEY (`settingID`),'
    . 'INDEX `new_index` (`settingKey`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'INSERT IGNORE INTO `globalSettings`'
    . '(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)'
    . 'VALUES'
    . "('FOG_TFTP_HOST','Hostname or IP address of the TFTP Server.','"
    . TFTP_HOST
    . "','TFTP Server'),"
    . "('FOG_TFTP_FTP_USERNAME','Username used to access the tftp server via ftp.','"
    . TFTP_FTP_USERNAME
    . "','TFTP Server'),"
    . "('FOG_TFTP_FTP_PASSWORD','Password used to access the tftp server via ftp.','"
    . TFTP_FTP_PASSWORD
    . "','TFTP Server'),"
    . "('FOG_TFTP_PXE_KERNEL_DIR','Location of kernel files on the PXE server.','"
    . TFTP_PXE_KERNEL_DIR
    . "','TFTP Server'),"
    . "('FOG_TFTP_PXE_KERNEL','Location of kernel file on the PXE server,"
    . "this should point to the kernel itself.','"
    . PXE_KERNEL
    . "','TFTP Server'),"
    . "('FOG_KERNEL_RAMDISK_SIZE','This setting defines the amount of physical "
    . "memory (in KB) you want to use for the boot image. This setting needs "
    . "to be larger than the boot image and smaller that the total physical "
    . "memory on the client.','"
    . PXE_KERNEL_RAMDISK
    . "','TFTP Server'),"
    . "('FOG_USE_SLOPPY_NAME_LOOKUPS','The settings was added to workaround "
    . "a partial implementation of DHCP in the boot image. The boot image "
    . "is unable to obtain a DNS server address from the DHCP server, "
    . "so what this setting will do is resolve any hostnames to IP "
    . "address on the FOG server before writing the config files.','"
    . USE_SLOPPY_NAME_LOOKUPS
    . "','General Settings'),"
    . "('FOG_MEMTEST_KERNEL', 'The settings defines where the memtest boot "
    . "image/kernel is located.','"
    . MEMTEST_KERNEL
    . "','General Settings'),"
    . "('FOG_PXE_BOOT_IMAGE','The settings defines where the fog boot file "
    . "system image is located.','"
    . PXE_IMAGE
    . "','TFTP Server'),"
    . "('FOG_NFS_HOST','This setting defines the hostname or ip address "
    . "of the NFS server used with FOG.','"
    . STORAGE_HOST
    . "','NFS Server'),"
    . "('FOG_NFS_FTP_USERNAME','This setting defines the username "
    . "used to access files on the nfs server used with FOG.','"
    . STORAGE_FTP_USERNAME
    . "','NFS Server'),"
    . "('FOG_NFS_FTP_PASSWORD','This setting defines the password "
    . "used to access flies on the nfs server used with FOG.','"
    . STORAGE_FTP_PASSWORD
    . "','NFS Server'),"
    . "('FOG_NFS_DATADIR','This setting defines the directory on "
    . "the NFS server where images are stored.','"
    . STORAGE_DATADIR
    . "','NFS Server'),"
    . "('FOG_NFS_DATADIR_CAPTURE','This setting defines the directory "
    . "on the NFS server where images are captured too.','"
    . STORAGE_DATADIR_CAPTURE
    . "','NFS Server'),"
    . "('FOG_NFS_BANDWIDTHPATH','This setting defines the web page "
    . "used to acquire the bandwidth used by the nfs server.','"
    . STORAGE_BANDWIDTHPATH
    . "','NFS Server'),"
    . "('FOG_CAPTURERESIZEPCT','This setting defines the amount of "
    . "padding applied to a partition before attempting resize the "
    . "ntfs volume and capturing it.','"
    . CAPTURERESIZEPCT
    . "','General Settings'),"
    . "('FOG_WEB_HOST','This setting defines the hostname or ip "
    . "address of the web server used with fog.','"
    . WEB_HOST
    . "','Web Server'),"
    . "('FOG_WEB_ROOT','This setting defines the path to the "
    . "fog webserver\'s root directory.','"
    . WEB_ROOT
    . "','Web Server'),"
    . "('FOG_WOL_HOST','This setting defines the ip address "
    . "of hostname for the server hosting the Wake-on-lan service.','"
    . WOL_HOST
    . "','General Settings'),"
    . "('FOG_WOL_PATH','This setting defines the path to the files "
    . "performing the WOL tasks.','"
    . WOL_PATH
    . "','General Settings'),"
    . "('FOG_WOL_INTERFACE','This setting defines the network interface "
    . "used in the WOL process.','"
    . WOL_INTERFACE
    . "','General Settings'),"
    . "('FOG_SNAPINDIR','This setting defines the location of the "
    . "snapin files. These files must be hosted on the web server.','"
    . SNAPINDIR
    . "','Web Server'),"
    . "('FOG_CHECKIN_TIMEOUT','This setting defines the amount "
    . "of time before a client check-in when waiting to start (imaging) expires. "
    . "AKA if they are active clients waiting to start imaging. "
    . "If a check-in time has passed this many seconds, the check-in is expired "
    . "and they are skipped over in line, they keep their spot if they return (based on taskID). "
    . "Default is 600 seconds (10 minutes), it\'s best to set this to a little "
    . "more than your average imaging task time (check your imaging log), so the "
    . "check-in expiration is close to when new slots to start imaging open "
    . "which avoids unnecessary queue changes. "
    . "DO NOT set below 180 (3 minutes) to avoid breaking the queue system','"
    . CHECKIN_TIMEOUT
    . "','General Settings'),"
    . "('FOG_USER_MINPASSLENGTH','This setting defines the "
    . "minimum number of characters in a user\'s password.','"
    . USER_MINPASSLENGTH
    . "','User Management'),"
    . "('FOG_NFS_ETH_MONITOR','This setting defines which "
    . "interface is monitored for traffic summaries.','"
    . NFS_ETH_MONITOR
    . "','NFS Server'),"
    . "('FOG_UDPCAST_INTERFACE', 'This setting defines the "
    . "interface used in multicast communications.','"
    . UDPCAST_INTERFACE
    . "','Multicast Settings'),"
    . "('FOG_UDPCAST_STARTINGPORT','This setting defines the "
    . "starting port number used in multicast communications. "
    . "This starting port number must be an even number.','"
    . UDPCAST_STARTINGPORT
    . "','Multicast Settings'),"
    . "('FOG_MULTICAST_MAX_SESSIONS','This setting defines "
    . "the maximum number of multicast sessions that can be "
    . "running at one time.','"
    . FOG_MULTICAST_MAX_SESSIONS
    . "', 'Multicast Settings'),"
    . "('FOG_JPGRAPH_VERSION','This setting defines jpgraph version to use.','"
    . FOG_JPGRAPH_VERSION
    . "', 'Web Server'),"
    . "('FOG_REPORT_DIR','This setting defines the location on the "
    . "web server of the FOG reports.','"
    . FOG_REPORT_DIR
    . "','Web Server'),"
    . "('FOG_THEME','This setting defines what css style "
    . "sheet and theme to use for FOG.','"
    . "default/fog.css"
    . "','Web Server'),"
    . "('FOG_CAPTUREIGNOREPAGEHIBER','This setting defines if you would "
    . "like to remove hibernate and swap files before capturing a "
    . "Windows image.','"
    . FOG_CAPTUREIGNOREPAGEHIBER
    . "','General Settings'),"
    . "('FOG_CLIENT_DIRECTORYCLEANER_ENABLED','This setting defines if "
    . "the Windows Service module directory cleaner should be enabled "
    . "on client computers. This service is clean out the contents of "
    . "a directory on when a user logs out of the workstation. "
    . "(Valid values: 0 or 1).','1', 'FOG Client - Directory Cleaner')",
    'CREATE TABLE `moduleStatusByHost` ('
    . '`msID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`msHostID` integer NOT NULL,'
    . '`msModuleID` VARCHAR(50) NOT NULL,'
    . '`msState` VARCHAR(1)  NOT NULL,'
    . 'PRIMARY KEY (`msID`),'
    . 'INDEX `new_index`(`msHostID`),'
    . 'INDEX `new_index2`(`msModuleID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `dirCleaner` ('
    . '`dcID` INTEGER  NOT NULL AUTO_INCREMENT,'
    . '`dcPath` longtext  NOT NULL,'
    . 'PRIMARY KEY (`dcID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'INSERT IGNORE INTO `globalSettings`'
    . '(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)'
    . 'VALUES'
    . "('FOG_USE_ANIMATION_EFFECTS','This setting defines if the "
    . "FOG management portal uses animation effects on it. "
    . "Valid values are 0 or 1', '1', 'General Settings'),"
    . "('FOG_CLIENT_USERCLEANUP_ENABLED','This setting defines if "
    . "user cleanup should be enabled. The User Cleanup module "
    . "will remove all local windows users from the workstation "
    . "on log off accept for users that are whitelisted. (Valid "
    . "values are 0 or 1)','0','FOG Client - User Cleanup')",
    'CREATE TABLE `userCleanup` ('
    . '`ucID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`ucName` VARCHAR(254) NOT NULL,'
    . 'PRIMARY KEY (`ucID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `userCleanup` (`ucName`)"
    . 'VALUES'
    . "('admin'),"
    . "('guest'),"
    . "('administrator'),"
    . "('HelpAssistant'),"
    . "('ASPNET'),"
    . "('SUPPORT_')",
    'INSERT IGNORE INTO `globalSettings`'
    . ' (`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)'
    . 'VALUES'
    . " ('FOG_CLIENT_GREENFOG_ENABLED','This setting defines if the green "
    . "fog module should be enabled. The green fog module will shutdown "
    . "or restart a computer at a set time. (Valid values are 0 or 1)'"
    . ",'1','FOG Client - Green Fog'),"
    . "('FOG_CLIENT_AUTOLOGOFF_ENABLED','This setting defines if the "
    . "auto log off module should be enabled. This module will log "
    . "off any active user after X minutes of inactivity."
    . "(Valid values are 0 or 1)','1','FOG Client - Auto Log Off'),"
    . "('FOG_CLIENT_DISPLAYMANAGER_ENABLED','This setting defines "
    . "if the fog display manager should be active. The fog display "
    . "manager will reset the clients screen resolution to a fixed "
    . "size on log off and on computer start up."
    . "(Valid values are 0 or 1)','0','FOG Client - Display Manager'),"
    . "('FOG_CLIENT_DISPLAYMANAGER_X','This setting defines the default "
    . "width in pixels to reset the computer display to with the fog "
    . "display manager service.','1024','FOG Client - Display Manager'),"
    . "('FOG_CLIENT_DISPLAYMANAGER_Y','This setting defines the "
    . "default height in pixels to reset the computer display to "
    . "with the fog display manager service.','768','FOG Client - Display Manager'),"
    . "('FOG_CLIENT_DISPLAYMANAGER_R','This setting defines the "
    . "default refresh rate to reset the computer display to with "
    . "the fog display manager service.','60','FOG Client - Display Manager'),"
    . "('FOG_CLIENT_AUTOLOGOFF_BGIMAGE','This setting defines the "
    . "location of the background image used in the auto log off "
    . "module. The image should be 300px x 300px. This image can "
    . "be located locally (such as c:\\\\images\\\\myimage.jpg) "
    . "or on a web server (such as http://freeghost.sf.net/images/image.jpg)',"
    . "'c:\\\\program files\\\\fog\\\\images\\\\alo-bg.jpg',"
    . "'FOG Client - Auto Log Off'),"
    . "('FOG_CLIENT_AUTOLOGOFF_MIN','This setting defines the number of "
    . "minutes to wait before logging a user off of a PC."
    . "(Value of 0 will disable this module.)','0', 'FOG Client - Auto Log Off'),"
    . "('FOG_KEYMAP','This setting defines the keymap used on "
    . "the client boot image.','','General Settings')",
    "CREATE TABLE `hostScreenSettings` ("
    . '`hssID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`hssHostID` INTEGER  NOT NULL,'
    . '`hssWidth` INTEGER NOT NULL,'
    . '`hssHeight` INTEGER NOT NULL,'
    . '`hssRefresh` INTEGER NOT NULL,'
    . '`hssOrientation` INTEGER NOT NULL,'
    . '`hssOther1` INTEGER NOT NULL,'
    . '`hssOther2` INTEGER NOT NULL,'
    . 'PRIMARY KEY (`hssID`),'
    . 'INDEX `new_index`(`hssHostID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `hostAutoLogOut` ('
    . '`haloID` INTEGER  NOT NULL AUTO_INCREMENT,'
    . '`haloHostID` INTEGER  NOT NULL,'
    . '`haloTime` VARCHAR(10) NOT NULL,'
    . 'PRIMARY KEY (`haloID`),'
    . 'INDEX `new_index`(`haloHostID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `greenFog` ('
    . '`gfID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`gfHostID` INTEGER NOT NULL,'
    . '`gfHour` INTEGER NOT NULL,'
    . '`gfMin` INTEGER NOT NULL,'
    . '`gfAction` varchar(2) NOT NULL,'
    . '`gfDays` varchar(25) NOT NULL,'
    . 'PRIMARY KEY (`gfID`),'
    . 'INDEX `new_index`(`gfHostID`)'
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_HOSTNAMECHANGER_ENABLED','This setting defines if the fog "
    . "hostname changer should be globally active. (Valid values are 0 or 1)',"
    . "'1', 'FOG Client - Hostname Changer')",
    "CREATE TABLE `aloLog` ("
    . "`alID` INTEGER  NOT NULL AUTO_INCREMENT,"
    . "`alUserName` VARCHAR(254) NOT NULL,"
    . "`alHostID` INTEGER NOT NULL,"
    . "`alDateTime` DATETIME NOT NULL,"
    . "`alAnon1` VARCHAR(254) NOT NULL,"
    . "`alAnon2` VARCHAR(254) NOT NULL,"
    . "`alAnon3` VARCHAR(254) NOT NULL,"
    . "PRIMARY KEY (`alID`),"
    . "INDEX `new_index`(`alUserName`),"
    . "INDEX `new_index2`(`alHostID`),"
    . "INDEX `new_index3`(`alDateTime`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` set vValue = '9'",
];
// 10
$this->schema[] = [
    "CREATE TABLE `imagingLog` ("
    . "`ilID` INTEGER NOT NULL AUTO_INCREMENT,"
    . "`ilHostID` INTEGER NOT NULL,"
    . "`ilStartTime` DATETIME NOT NULL,"
    . "`ilFinishTime` DATETIME NOT NULL,"
    . "`ilImageName` VARCHAR(64) NOT NULL,"
    . "PRIMARY KEY (`ilID`),"
    . "INDEX `new_index`(`ilHostID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_SNAPIN_ENABLED','This setting defines if the "
    . "fog snapin installer should be globally active. (Valid values are 0 or 1)'"
    . ",'1', 'FOG Client - Snapins')",
    "ALTER TABLE `snapins` CHANGE `sAnon1` `sRunWith` VARCHAR(245) NOT NULL",
    "ALTER TABLE `snapinTasks` ADD COLUMN `stReturnCode` "
    . "INTEGER NOT NULL AFTER `stSnapinID`,ADD COLUMN "
    . "`stReturnDetails` varchar(250)  NOT NULL AFTER `stReturnCode`",
    "ALTER TABLE `snapins` CHANGE `sAnon2` `sRunWithArgs` "
    . "VARCHAR(200)  CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL",
    "UPDATE `schemaVersion` set vValue = '10'",
];
// 11
$this->schema[] = [
    "ALTER TABLE `hosts` CHANGE `hostAnon2` "
    . "`hostKernelArgs` VARCHAR(250) CHARACTER "
    . "SET utf8 COLLATE utf8_general_ci NOT NULL",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_KERNEL_ARGS', 'This setting allows you to add additional "
    . "kernel arguments to the client boot image. This setting is global "
    . "for all hosts.','', 'General Settings')",
    "UPDATE `schemaVersion` set vValue = '11'",
];
// 12
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_CLIENTUPDATER_ENABLED','This setting defines if "
    . "the fog client updater should be globally active. "
    . "(Valid values are 0 or 1)','1','FOG Client - Client Updater'),"
    . "('FOG_CLIENT_HOSTREGISTER_ENABLED','This setting defines if the "
    . "fog host register should be globally active. "
    . "(Valid values are 0 or 1)','1','FOG Client - Host Register'),"
    . "('FOG_CLIENT_PRINTERMANAGER_ENABLED','This setting defines if the "
    . "fog printer maanger should be globally active. "
    . "(Valid values are 0 or 1)','1','FOG Client - Printer Manager'),"
    . "('FOG_CLIENT_TASKREBOOT_ENABLED','This setting defines if the fog "
    . "task reboot should be globally active. "
    . "(Valid values are 0 or 1)','1','FOG Client - Task Reboot'),"
    . "('FOG_CLIENT_USERTRACKER_ENABLED','This setting defines if the fog "
    . "user tracker should be globally active. "
    . "(Valid values are 0 or 1)','1','FOG Client - User Tracker')",
    "UPDATE `schemaVersion` set vValue = '12'",
];
// 13
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_AD_DEFAULT_DOMAINNAME','This setting defines the default "
    . "value to populate the host\'s Active Directory domain name value.',"
    . "'','Active Directory Defaults'),"
    . "('FOG_AD_DEFAULT_OU','This setting defines the default value to "
    . "populate the host\'s Active Directory OU value.',"
    . "'','Active Directory Defaults'),"
    . "('FOG_AD_DEFAULT_USER','This setting defines the default value to "
    . "populate the host\'s Active Directory user name value.',"
    . "'', 'Active Directory Defaults'),"
    . "('FOG_AD_DEFAULT_PASSWORD','This setting defines the default value "
    . "to populate the host\'s Active Directory password value. This "
    . "settings must be encrypted.','','Active Directory Defaults')",
    "UPDATE `schemaVersion` set vValue = '13'",
];
// 14
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_UTIL_DIR','This setting defines the location of the fog "
    . "utility directory.','/opt/fog/utils','FOG Utils')",
    "ALTER TABLE `users` ADD COLUMN `uType` VARCHAR(2) NOT NULL AFTER `uCreateBy`",
    "UPDATE `schemaVersion` set vValue = '14'",
];
// 15
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PLUGINSYS_ENABLED', 'This setting defines if the fog plugin "
    . "system should be enabled.','0','Plugin System'),"
    . "('FOG_PLUGINSYS_DIR','This setting defines the base location "
    . "of fog plugins.','./plugins','Plugin System')",
    "CREATE TABLE `plugins` ("
    . "`pID` INTEGER  NOT NULL AUTO_INCREMENT,"
    . "`pName` VARCHAR(100) NOT NULL,"
    . "`pState` CHAR NOT NULL,"
    . "`pInstalled` CHAR NOT NULL,"
    . "`pVersion` VARCHAR(100) NOT NULL,"
    . "`pAnon1` VARCHAR(100) NOT NULL,"
    . "`pAnon2` VARCHAR(100) NOT NULL,"
    . "`pAnon3` VARCHAR(100) NOT NULL,"
    . "`pAnon4` VARCHAR(100) NOT NULL,"
    . "`pAnon5` VARCHAR(100) NOT NULL,"
    . "PRIMARY KEY (`pID`),"
    . "INDEX `new_index`(`pName`),"
    . "INDEX `new_index1`(`pState`),"
    . "INDEX `new_index2`(`pInstalled`),"
    . "INDEX `new_index3`(`pVersion`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "ALTER TABLE `hosts` CHANGE `hostAnon3` `hostKernel` VARCHAR(250) "
    . "CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,"
    . "CHANGE `hostAnon4` `hostDevice` VARCHAR(250) CHARACTER "
    . "SET utf8 COLLATE utf8_general_ci NOT NULL",
    "UPDATE `schemaVersion` set vValue = '15'",
];
// 16
$this->schema[] = [
    "ALTER TABLE `tasks` ADD COLUMN `taskBPM` varchar(250) NOT NULL AFTER "
    . "`taskPCT`, ADD COLUMN `taskTimeElapsed` varchar(250) NOT NULL AFTER "
    . "`taskBPM`, ADD COLUMN `taskTimeRemaining` varchar(250) NOT NULL AFTER "
    . "`taskTimeElapsed`, ADD COLUMN `taskDataCopied` varchar(250) NOT NULL "
    . "AFTER `taskTimeRemaining`, ADD COLUMN `taskPercentText` varchar(250) NOT "
    . "NULL AFTER `taskDataCopied`, ADD COLUMN `taskDataTotal` VARCHAR(250) NOT "
    . "NULL AFTER `taskPercentText`",
    "CREATE TABLE `nfsGroups` ("
    . "`ngID` integer NOT NULL AUTO_INCREMENT,"
    . "`ngName` varchar(250) NOT NULL,"
    . "`ngDesc` longtext NOT NULL,"
    . "PRIMARY KEY (`ngID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "CREATE TABLE `nfsGroupMembers` ("
    . "`ngmID` integer NOT NULL AUTO_INCREMENT,"
    . "`ngmMemberName` varchar(250) NOT NULL,"
    . "`ngmMemberDescription` longtext NOT NULL,"
    . "`ngmIsMasterNode` char NOT NULL,"
    . "`ngmGroupID` integer NOT NULL,"
    . "`ngmRootPath` longtext NOT NULL,"
    . "`ngmIsEnabled` char NOT NULL,"
    . "`ngmHostname` varchar(250) NOT NULL,"
    . "`ngmMaxClients` integer NOT NULL,"
    . "`ngmUser` varchar(250) NOT NULL,"
    . "`ngmPass` varchar(250) NOT NULL,"
    . "`ngmKey` varchar(250) NOT NULL,"
    . " PRIMARY KEY (`ngmID`),"
    . "INDEX `new_index`(`ngmMemberName`),"
    . "INDEX `new_index2`(`ngmIsMasterNode`),"
    . "INDEX `new_index3`(`ngmGroupID`),"
    . "INDEX `new_index4`(`ngmIsEnabled`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "ALTER TABLE `images` ADD COLUMN `imageNFSGroupID` integer "
    . "NOT NULL AFTER `imageDD`,"
    . "ADD INDEX `new_index3`(`imageNFSGroupID`)",
    "ALTER TABLE `tasks` ADD COLUMN `taskNFSGroupID` integer "
    . "NOT NULL AFTER `taskDataTotal`,"
    . "ADD COLUMN `taskNFSMemberID` integer NOT NULL AFTER `taskNFSGroupID`,"
    . "ADD COLUMN `taskNFSFailures` char NOT NULL AFTER `taskNFSMemberID`,"
    . "ADD COLUMN `taskLastMemberID` integer NOT NULL AFTER `taskNFSFailures`,"
    . "ADD INDEX `new_index5`(`taskNFSGroupID`),"
    . "ADD INDEX `new_index6`(`taskNFSMemberID`),"
    . "ADD INDEX `new_index7`(`taskNFSFailures`),"
    . "ADD INDEX `new_index8`(`taskLastMemberID`)",
    "CREATE TABLE `nfsFailures` ("
    . "`nfID` integer NOT NULL AUTO_INCREMENT,"
    . "`nfNodeID` integer NOT NULL,"
    . "`nfTaskID` integer NOT NULL,"
    . "`nfHostID` integer NOT NULL,"
    . "`nfGroupID` integer NOT NULL,"
    . "`nfDateTime` integer NOT NULL,"
    . "PRIMARY KEY (`nfID`),"
    . "INDEX `new_index`(`nfNodeID`),"
    . "INDEX `new_index1`(`nfTaskID`),"
    . "INDEX `new_index2`(`nfHostID`),"
    . "INDEX `new_index3`(`nfGroupID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "ALTER TABLE `nfsFailures` MODIFY COLUMN `nfDateTime` datetime NOT NULL,"
    . "ADD INDEX `new_index4`(`nfDateTime`)",
    "ALTER TABLE `multicastSessions` CHANGE `msAnon2` `msNFSGroupID` integer "
    . "NOT NULL, ADD INDEX `new_index`(`msNFSGroupID`)",
    "INSERT IGNORE INTO `nfsGroups` "
    . "(`ngName`,`ngDesc`) "
    . "VALUES "
    . "('default','Auto generated fog nfs group')",
    "INSERT IGNORE INTO `nfsGroupMembers` "
    . "(`ngmMemberName`,`ngmMemberDescription`,`ngmIsMasterNode`,"
    . "`ngmGroupID`,`ngmRootPath`,`ngmIsEnabled`,`ngmHostname`,"
    . "`ngmMaxClients`,`ngmUser`,`ngmPass`) "
    . "VALUES "
    . "('DefaultMember','Auto generated fog nfs group member','1',"
    . "'1','/images','1','"
    . STORAGE_HOST
    . "','10','"
    . STORAGE_FTP_USERNAME
    . "','"
    . STORAGE_FTP_PASSWORD
    . "')",
    "UPDATE `images` set imageNFSGroupID = '1'",
    "DELETE FROM `globalSettings` WHERE `settingKey` IN "
    . "('FOG_NFS_HOST','FOG_NFS_FTP_USERNAME','FOG_NFS_FTP_PASSWORD',"
    . "'FOG_NFS_DATADIR','FOG_NFS_DATADIR_CAPTURE')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_STORAGENODE_MYSQLUSER','This setting defines the username "
    . "the storage nodes should use to connect to the fog server.',"
    . "'fogstorage','FOG Storage Nodes')",
    "UPDATE `schemaVersion` set `vValue`='16'",
];
// 17
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_SSH_USERNAME','This setting defines the username used "
    . "for the ssh client.','root','SSH Client'),"
    . "('FOG_SSH_PORT','This setting defines the port to use for the ssh client.',"
    . "'22','SSH Client'),"
    . "('FOG_VIEW_DEFAULT_SCREEN','This setting defines how many rows a "
    . "management list shows to a user who has not yet saved a layout of "
    . "their own. Column order, visibility, sort and row count are "
    . "remembered per user once changed, so this is the starting default "
    . "rather than a fixed limit.','25','FOG View Settings')",
    "UPDATE `schemaVersion` set vValue = '17'",
];
// 18
$this->schema[] = [
    "INSERT IGNORE INTO `supportedOS` "
    . "(`osName`,`osValue`) "
    . "VALUES "
    . "('Windows 7','5'),"
    . "('Windows 8','6')",
    "UPDATE `schemaVersion` set `vValue`='18'",
];
// 19
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PXE_MENU_TIMEOUT','This setting defines the default value "
    . "for the pxe menu timeout.','3','FOG PXE Settings'),"
    . "('FOG_PROXY_IP','This setting defines the proxy ip address to use.',"
    . "'','General Settings'),"
    . "('FOG_PROXY_PORT','This setting defines the proxy port address to use.',"
    . "'','General Settings')",
    "CREATE TABLE `scheduledTasks` ("
    . "`stID` integer NOT NULL AUTO_INCREMENT,"
    . "`stName` varchar(240) NOT NULL,"
    . "`stDesc` longtext NOT NULL,"
    . "`stType` varchar(24) NOT NULL,"
    . "`stTaskType` varchar(24) NOT NULL,"
    . "`stMinute` varchar(240) NOT NULL,"
    . "`stHour` varchar(240) NOT NULL,"
    . "`stDOM` varchar(240) NOT NULL,"
    . "`stMonth` varchar(240) NOT NULL,"
    . "`stDOW` varchar(240) NOT NULL,"
    . "`stIsGroup` varchar(2) NOT NULL,"
    . "`stGroupHostID` integer NOT NULL,"
    . "`stShutDown` varchar(2) NOT NULL,"
    . "`stOther1` varchar(240) NOT NULL,"
    . "`stOther2` varchar(240) NOT NULL,"
    . "`stOther3` varchar(240) NOT NULL,"
    . "`stOther4` varchar(240) NOT NULL,"
    . "`stOther5` varchar(240) NOT NULL,"
    . "`stDateTime` BIGINT UNSIGNED NOT NULL DEFAULT 0,"
    . "`stActive` varchar(2) NOT NULL DEFAULT 1,"
    . "PRIMARY KEY (`stID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_UTIL_BASE','This setting defines the location of util base, "
    . "which is typically /opt/fog/','/opt/fog/','FOG Utils')",
    "UPDATE `schemaVersion` set vValue = '19'",
];
// 20
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PXE_MENU_HIDDEN','This setting defines if you would like the "
    . "FOG pxe menu hidden or displayed','0','FOG PXE Settings'),"
    . "('FOG_PXE_ADVANCED','This setting defines if you would like to "
    . "append any settings to the end of your PXE default file.','',"
    . "'FOG PXE Settings'),"
    . "('FOG_USE_LEGACY_TASKLIST','This setting defines if you would like to "
    . "use the legacy active tasks window. Note: The legacy screen will no "
    . "longer be updated.','0','General Settings')",
    "ALTER TABLE `globalSettings` MODIFY COLUMN `settingValue` LONGTEXT "
    . "CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL",
    "UPDATE `schemaVersion` set vValue = '20'",
];
// 21
$this->schema[] = [
    "CREATE TABLE `hostMAC` ("
    . "`hmID` integer NOT NULL AUTO_INCREMENT,"
    . "`hmHostID` integer NOT NULL,"
    . "`hmMAC` varchar(18) NOT NULL,"
    . "`hmDesc` longtext NOT NULL,"
    . "PRIMARY KEY (`hmID`),"
    . "INDEX `idxHostID`(`hmHostID`),"
    . "INDEX `idxMac`(`hmMAC`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "CREATE TABLE `oui` ("
    . "`ouiID` int(11) NOT NULL AUTO_INCREMENT,"
    . "`ouiMACPrefix` varchar(8) NOT NULL,"
    . "`ouiMan` varchar(254) NOT NULL,"
    . "PRIMARY KEY (`ouiID`),"
    . "KEY `idxMac` (`ouiMACPrefix`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_AUTOPOP','Enable FOG Quick Registration auto "
    . "population feature (0 = disabled, 1=enabled). If this feature "
    . "is enabled, FOG will auto populate the host settings and "
    . "automatically image the computer without any user intervention.',"
    . "'0','FOG Quick Registration'),"
    . "('FOG_QUICKREG_IMG_ID','FOG Quick Registration Image ID.',"
    . "'-1', 'FOG Quick Registration'),"
    . "('FOG_QUICKREG_OS_ID','FOG Quick Registration OS ID.',"
    . "'-1', 'FOG Quick Registration'),"
    . "('FOG_QUICKREG_SYS_NAME','FOG Quick Registration system name template. "
    . "Use * for the autonumber feature.', 'PC-*', 'FOG Quick Registration'),"
    . "('FOG_QUICKREG_SYS_NUMBER','FOG Quick Registration system name auto number.',"
    . "'1','FOG Quick Registration'),"
    . "('FOG_DEFAULT_LOCALE','Default language code to use for FOG.',"
    . "'en', 'General Settings'),"
    . "('FOG_HOST_LOCKUP','Should FOG attempt to see if a host is active "
    . "and display it as part of the UI?','1','General Settings'),"
    . "('FOG_UUID','This is a unique ID that is used to identify your "
    . "installation. In most cases you do not want to change this value.',"
    . "'"
    . uniqid("", true)
    . "','General Settings')",
    "CREATE TABLE `pendingMACS` ("
    . "`pmID` INTEGER  NOT NULL AUTO_INCREMENT,"
    . "`pmAddress` varchar(18)  NOT NULL,"
    . "`pmHostID` INTEGER  NOT NULL,"
    . "PRIMARY KEY (`pmID`),"
    . "INDEX `idx_mc`(`pmAddress`),"
    . "INDEX `idx_host`(`pmHostID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_MAX_PENDING_MACS','This setting defines how many mac "
    . "addresses will be stored in the pending mac address table for each host.',"
    . "'4', 'FOG Client - Host Register'),"
    . "('FOG_QUICKREG_PENDING_MAC_FILTER','This is a list of MAC address "
    . "fragments that is used to filter out pending mac address requests. "
    . "For example, if you don\'t want to see pending mac address requests "
    . "for VMWare NICs then you could filter by 00:05:69. This filter is "
    . "comma seperated, and is used like a *starts with* filter.',"
    . "'','FOG Client - Host Register'),"
    . "('FOG_ADVANCED_STATISTICS','Enable the collection and display of "
    . "advanced statistics. This information WILL be sent to a remote "
    . "server! This information is used by the FOG team to see how "
    . "FOG is being used. The information that will be sent includes "
    . "the server\'s UUID value, the number of hosts present in FOG, "
    . "and number of images on your FOG server and well as total "
    . "image space used. (0 = disabled, 1 = enabled).',"
    . "'0', 'General Settings')",
    "UPDATE `schemaVersion` set vValue = '21'",
];
// 22
$this->schema[] = [
    "ALTER TABLE `inventory` ADD INDEX (`iHostID`)",
    "UPDATE `globalSettings` set `settingKey`='FOG_HOST_LOOKUP' "
    . "WHERE `settingKey`='FOG_HOST_LOCKUP'",
    "UPDATE `schemaVersion` set `vValue`='22'",
];
// 23
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_DISABLE_CHKDSK','This is an experimental feature that will "
    . "can be used to not set the dirty flag on a NTFS partition after "
    . "resizing it. It is recommended to you run chkdsk. "
    . "(0 = runs chkdsk, 1 = disables chkdsk).','1','General Settings'),"
    . "('FOG_CHANGE_HOSTNAME_EARLY','This is an experimental feature that "
    . "will can be used to change the computers hostname right after "
    . "imaging the box, without the need for the FOG service. "
    . "(1 = enabled, 0 = disabled).','1','General Settings')",
    "UPDATE `schemaVersion` set `vValue`='23'",
];
// 24
$this->schema[] = [
    "ALTER TABLE `groups` ADD `groupKernel` VARCHAR(255) NOT NULL",
    "ALTER TABLE `groups` ADD `groupKernelArgs` VARCHAR(255) NOT NULL",
    "ALTER TABLE `groups` ADD `groupPrimaryDisk` VARCHAR(255) NOT NULL",
    "UPDATE `schemaVersion` set `vValue`='24'",
];
// 25
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `os` ("
    . "`osID` mediumint(9) NOT NULL AUTO_INCREMENT,"
    . "`osName` varchar(30) NOT NULL,"
    . "`osDescription` text NOT NULL,"
    . "PRIMARY KEY (`osID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "(1, 'Windows 2000/XP', ''),"
    . "(2, 'Windows Vista', ''),"
    . "(3, 'Windows 98', ''),"
    . "(4, 'Windows Other', ''),"
    . "(5, 'Windows 7', ''),"
    . "(50, 'Linux', ''),"
    . "(99, 'Other', '')",
    "ALTER TABLE `images` ADD `imageOSID` MEDIUMINT NOT NULL ",
    "ALTER TABLE `hosts` ADD UNIQUE (`hostMAC`)",
    "UPDATE `schemaVersion` set `vValue`='25'",
];
// 26
$this->schema[] = [
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` MEDIUMINT NOT NULL",
    "ALTER TABLE `nfsGroupMembers` ADD `ngmInterface` VARCHAR(25) NOT NULL DEFAULT '"
    . STORAGE_INTERFACE
    . "'",
    "ALTER TABLE `nfsGroupMembers` ADD `ngmGraphEnabled` "
    . "ENUM('0','1') NOT NULL DEFAULT '1'",
    "UPDATE `schemaVersion` set `vValue`='26'",
];
// 27
$this->schema[] = [
    "ALTER TABLE `tasks` CHANGE `taskCreateTime` `taskCreateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `groups` CHANGE `groupDateTime` `groupDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `hosts` CHANGE `hostCreateDate` `hostCreateDate` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `history` CHANGE `hTime` `hTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `aloLog` CHANGE `alDateTime` `alDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `images` CHANGE `imageDateTime` `imageDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `inventory` CHANGE `iCreateDate` `iCreateDate` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `nfsFailures` CHANGE `nfDateTime` `nfDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `snapinJobs` CHANGE `sjCreateTime` `sjCreateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `snapins` CHANGE `sCreateDate` `sCreateDate` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `snapinTasks` CHANGE `stCheckinDate` `stCheckinDate` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `users` CHANGE `uCreateDate` `uCreateDate` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `userTracking` CHANGE `utDateTime` `utDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE `virus` CHANGE `vDateTime` `vDateTime` "
    . "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "UPDATE `schemaVersion` set `vValue`='27'",
];
// 28
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `imageTypes` ("
    . "`imageTypeID` mediumint(9) NOT NULL auto_increment,"
    . "`imageTypeName` varchar(100) NOT NULL,"
    . "PRIMARY KEY  (`imageTypeID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `imageTypes` "
    . "(`imageTypeID`, `imageTypeName`) "
    . "VALUES "
    . "(1, 'Single Partition (NTFS Only, Resizable)'),"
    . "(2, 'Multiple Partition Image - Single Disk (Not Resizable)'),"
    . "(3, 'Multiple Partition Image - All Disks  (Not Resizable)'),"
    . "(4, 'Raw Image (Sector By Sector, DD, Slow)')",
    "UPDATE `schemaVersion` set `vValue`='28'",
];
// 29
if (FOG_SCHEMA < $tmpSchema->get('value')) {
    self::$DB->query(
        "SELECT DISTINCT `hostImage`,`hostOS` FROM `hosts` WHERE hostImage > 0"
    );
    while ($Host = self::$DB->fetch()->get()) {
        $allImageID[$Host['hostImage']] = $Host['hostOS'];
    }
    foreach ((array)$allImageID as $imageID => $osID) {
        $Image = new Image($imageID);
        if (!$Image->isValid()) {
            continue;
        }
        $OS = new OS($osID);
        if (!$OS->isValid()) {
            continue;
        }
        if (!$Image->set('osID', $osID)->save()) {
            $errors[] = sprintf(
                '<div>Failed updating the osID of imageID: %s, osID: %s</div>',
                $imageID,
                $osID
            );
        }
    }
}
// 29
$this->schema[] = [
    "UPDATE `schemaVersion` SET `vValue`=29",
];
// 30
$this->schema[] = [
    "ALTER TABLE `imageTypes` ADD `imageTypeValue` VARCHAR(10) NOT NULL",
    "UPDATE `imageTypes` SET `imageTypeValue`='n' "
    . "WHERE `imageTypes`.`imageTypeID`=1",
    "UPDATE `imageTypes` SET `imageTypeValue`='mps' "
    . "WHERE `imageTypes`.`imageTypeID`=2",
    "UPDATE `imageTypes` SET `imageTypeValue`='mpa' "
    . "WHERE `imageTypes`.`imageTypeID`=3",
    "UPDATE `imageTypes` SET `imageTypeValue`='dd' "
    . "WHERE `imageTypes`.`imageTypeID`=4",
    "UPDATE `images` SET `imageDD`='4' WHERE `imageDD`='3'",
    "UPDATE `images` SET `imageDD`='3' WHERE `imageDD`='2'",
    "UPDATE `images` SET `imageDD`='2' WHERE `imageDD`='1'",
    "UPDATE `images` SET `imageDD`='1' WHERE `imageDD`='0'",
    "ALTER TABLE `images` CHANGE `imageDD` `imageTypeID` MEDIUMINT NOT NULL",
    "UPDATE `schemaVersion` set `vValue`='30'",
];
// 31
$this->schema[] = [
    "ALTER TABLE `scheduledTasks` CHANGE `stIsGroup` `stIsGroup` VARCHAR(2) "
    . "CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0'",
    "UPDATE `schemaVersion` set `vValue`='31'",
];
// 32
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `taskStates` ("
    . "`tsID` int(11) NOT NULL,"
    . "`tsName` varchar(30) NOT NULL,"
    . "`tsDescription` text NOT NULL,"
    . "`tsOrder` tinyint(4) NOT NULL DEFAULT '0',"
    . "PRIMARY KEY (`tsID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `taskStates` "
    . "(`tsID`, `tsName`, `tsDescription`, `tsOrder`) VALUES "
    . "(1,'Queued','Task has been created and FOG is "
    . "waiting for the Host to check-in.', '1'),"
    . "(2, 'In-Progress', 'Host is currently Imaging.', '2'),"
    . "(3, 'Complete', 'Imaging has been completed.', '3')",
    "ALTER TABLE `tasks` CHANGE `taskState` `taskStateID` INT( 11 ) NOT NULL",
    "UPDATE `tasks` SET `taskType` = '1' WHERE `taskType`='d'",
    "UPDATE `tasks` SET `taskType` = '2' WHERE `taskType`='u'",
    "UPDATE `tasks` SET `taskType` = '3' WHERE `taskType`='x'",
    "UPDATE `tasks` SET `taskType` = '4' WHERE `taskType`='w'",
    "UPDATE `tasks` SET `taskType` = '5' WHERE `taskType`='m'",
    "UPDATE `tasks` SET `taskType` = '6' WHERE `taskType`='t'",
    "UPDATE `tasks` SET `taskType` = '7' WHERE `taskType`='r'",
    "UPDATE `tasks` SET `taskType` = '8' WHERE `taskType`='c'",
    "UPDATE `tasks` SET `taskType` = '9' WHERE `taskType`='v'",
    "UPDATE `tasks` SET `taskType` = '10' WHERE `taskType`='i'",
    "UPDATE `tasks` SET `taskType` = '11' WHERE `taskType`='j'",
    "UPDATE `tasks` SET `taskType` = '12' WHERE `taskType`='s'",
    "UPDATE `tasks` SET `taskType` = '13' WHERE `taskType`='l'",
    "UPDATE `tasks` SET `taskType` = '14' WHERE `taskType`='o'",
    "ALTER TABLE `tasks` CHANGE `taskType` `taskTypeID` MEDIUMINT NOT NULL ",
    "CREATE TABLE IF NOT EXISTS `taskTypes` ("
    . "`ttID` mediumint(9) NOT NULL AUTO_INCREMENT,"
    . "`ttName` varchar(30) NOT NULL,"
    . "`ttDescription` text NOT NULL,"
    . "`ttIcon` varchar(30) NOT NULL,"
    . "`ttKernelTemplate` text NOT NULL,"
    . "`ttType` enum('fog','user') NOT NULL DEFAULT 'user',"
    . "`ttIsAdvanced` enum('0','1') NOT NULL DEFAULT '0',"
    . "`ttIsAccess` enum('both','host','group') NOT NULL DEFAULT 'both',"
    . "PRIMARY KEY (`ttID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`,`ttName`,`ttDescription`,`ttIcon`,"
    . "`ttKernelTemplate`,`ttType`,`ttIsAdvanced`,`ttIsAccess`) "
    . "VALUES "
    . "(1,'Deploy','Deploy action will send an image saved on the "
    . "FOG server to the client computer with all included snapins.',"
    . "'senddebug.png', 'type=down', 'fog', '0', 'both'),"
    . "(2,'Capture','Capture will pull an image from a client computer "
    . "that will be saved on the server.','restoredebug.png',"
    . "'type=up','fog','0','host'),"
    . "(3,'Debug','Debug mode will load the boot image and load a prompt "
    . "so you can run any commands you wish. When you are done, you must "
    . "remember to remove the PXE file, by clicking on \"Active Tasks\" "
    . "and clicking on the \"Kill Task\" button.', 'debug.png',"
    . "'type=down mode=debug', 'fog', '1', 'host'),"
    . "(5, 'Memtest86+', 'Memtest86+ loads Memtest86+ on the client computer "
    . "and will have it continue to run until stopped. When you are done, "
    . "you must remember to remove the PXE file, by clicking on "
    . "\"Active Tasks\" and clicking on the \"Kill Task\" button.', "
    . "'memtest.png', '', 'fog', '1', 'both'),"
    . "(6, 'Disk Surface Test', 'Disk Surface Test checks the hard "
    . "drives surface sector by sector for any errors and reports "
    . "back if errors are present.', 'surfacetest.png', '',"
    . "'fog', '1', 'both'),"
    . "(7, 'Recover', 'Recover loads the photorec utility that can "
    . "be used to recover lost files from a hard disk. When "
    . "recovering files, make sure you save them to your "
    . "NFS volume (ie: /images).', 'recover.png', '', "
    . "'fog', '1', 'both'),"
    . "(8, 'Multi-Cast', 'Deploy action will send an image saved on the "
    . "FOG server to the client computer with all included snapins.', "
    . "'senddebug.png', '', 'fog', '0', 'group'),"
    . "(9, 'Virus Scan', 'Anti-Virus loads Clam AV on the client boot "
    . "image, updates the scanner and then scans the Windows partition.',"
    . "'clam.png', '', 'fog', '1', 'both'),"
    . "(10, 'Hardware Inventory', 'The hardware inventory task will "
    . "boot the client computer and pull basic hardware information "
    . "from it and report it back to the FOG server.', 'inventory.png', "
    . "'', 'fog', '1', 'both'),"
    . "(11, 'Password Reset', 'Password reset will blank out a "
    . "Windows user password that may have been lost or forgotten.', "
    . "'winpass.png', '', 'fog', '1', 'both'),"
    . "(12, 'All Snapins', 'This option allows you to send all the "
    . "snapins to host without imaging the computer. (Requires FOG "
    . "Client to be installed on client)', 'snap.png', '', 'fog', "
    . "'1', 'both'),"
    . "(13, 'Single Snapin', 'This option allows you to send "
    . "a single snapin to a host. (Requires FOG Client to be "
    . "installed on client)', 'snap.png', '', 'fog', "
    . "'1', 'both'),"
    . "(14, 'Wake-Up', 'Wake Up will attempt to send the "
    . "Wake-On-LAN packet to the computer to turn the computer "
    . "on. In switched environments, you typically need to "
    . "configure your hardware to allow for this (iphelper).', "
    . "'wake.png', '', 'fog', '1', 'both'),"
    . "(15, 'Deploy - Debug', 'Deploy - Debug mode allows FOG to "
    . "setup the environment to allow you send a specific image "
    . "to a computer, but instead of sending the image, FOG "
    . "will leave you at a prompt right before sending. If "
    . "you actually wish to send the image all you need to "
    . "do is type \"fog\" and hit enter.', 'senddebug.png', "
    . "'type=down mode=debug', 'fog', '1', 'host'),"
    . "(16, 'Capture - Debug', 'mode allows FOG to setup the "
    . "environment to allow you capture a specific image to a "
    . "computer, but instead of capturing the image, FOG will "
    . "leave you at a prompt right before restoring. If you "
    . "actually wish to capture the image all you need to do is "
    . "type \"fog\" and hit enter.', 'restoredebug.png', "
    . "'type=up mode=debug', 'fog', '1', 'host'),"
    . "(17, 'Deploy without Snapins', 'Deploy without snapins "
    . "allows FOG to image the workstation, but after the task "
    . "is complete any snapins linked to the host or group will "
    . "NOT be sent.', 'sendnosnapin.png', '', 'fog', '1', 'both'),"
    . "(18, 'Fast Wipe', 'Full Wipe will boot the client computer "
    . "and perform a full disk wipe. This method writes a few passes "
    . "of random data to the hard disk.', 'veryfastwipe.png', "
    . "'', 'fog', '1', 'both'),"
    . "(19, 'Normal Wipe', 'Normal Wipe will boot the client "
    . "computer and perform a simple disk wipe. This method "
    . "writes one pass of zero''s to the hard disk.',"
    . "'quickwipe.png', '', 'fog', '1', 'both'),"
    . "(20, 'Full Wipe', 'Full Wipe will boot the client computer "
    . "and perform a full disk wipe. This method writes a few "
    . "passes of random data to the hard disk.', 'fullwipe.png',"
    . "'', 'fog', '1', 'both')",
    "UPDATE `scheduledTasks` SET `stTaskType`='1' WHERE `stTaskType`='d'",
    "UPDATE `scheduledTasks` SET `stTaskType`='2' WHERE `stTaskType`='u'",
    "UPDATE `scheduledTasks` SET `stTaskType`='3' WHERE `stTaskType`='x'",
    "UPDATE `scheduledTasks` SET `stTaskType`='4' WHERE `stTaskType`='w'",
    "UPDATE `scheduledTasks` SET `stTaskType`='5' WHERE `stTaskType`='m'",
    "UPDATE `scheduledTasks` SET `stTaskType`='6' WHERE `stTaskType`='t'",
    "UPDATE `scheduledTasks` SET `stTaskType`='7' WHERE `stTaskType`='r'",
    "UPDATE `scheduledTasks` SET `stTaskType`='8' WHERE `stTaskType`='c'",
    "UPDATE `scheduledTasks` SET `stTaskType`='9' WHERE `stTaskType`='v'",
    "UPDATE `scheduledTasks` SET `stTaskType`='10' WHERE `stTaskType`='i'",
    "UPDATE `scheduledTasks` SET `stTaskType`='11' WHERE `stTaskType`='j'",
    "UPDATE `scheduledTasks` SET `stTaskType`='12' WHERE `stTaskType`='s'",
    "UPDATE `scheduledTasks` SET `stTaskType`='13' WHERE `stTaskType`='l'",
    "UPDATE `scheduledTasks` SET `stTaskType`='14' WHERE `stTaskType`='o'",
    "ALTER TABLE `scheduledTasks` CHANGE `stTaskType` "
    . "`stTaskTypeID` MEDIUMINT NOT NULL",
    "UPDATE `schemaVersion` set `vValue`='32'",
];
// 33
$this->schema[] = [
    "ALTER TABLE `taskTypes` CHANGE `ttKernelTemplate` "
    . "`ttKernelArgs` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ",
    "ALTER TABLE `taskTypes` ADD `ttKernel` VARCHAR( 100 ) NOT NULL AFTER `ttIcon`",
    "TRUNCATE `taskTypes`",
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`, `ttName`, `ttDescription`, `ttIcon`,"
    . "`ttKernel`, `ttKernelArgs`, `ttType`, `ttIsAdvanced`, `ttIsAccess`)"
    . "VALUES "
    . "(1, 'Deploy', 'Deploy action will send an image saved on the "
    . "FOG server to the client computer with all included snapins.',"
    . "'senddebug.png', '', 'type=down', 'fog', '0', 'both'),"
    . "(2, 'Capture', 'Capture will pull an image from a client "
    . "computer that will be saved on the server.', 'restoredebug.png', "
    . "'', 'type=up', 'fog', '0', 'host'),"
    . "(3, 'Debug', 'Debug mode will load the boot image and load "
    . "a prompt so you can run any commands you wish. When you are done, "
    . "you must remember to remove the PXE file, by clicking on "
    . "\"Active Tasks\" and clicking on the \"Kill Task\" button.', "
    . "'debug.png', '', 'mode=onlydebug', 'fog', '1', 'host'),"
    . "(4, 'Memtest86+', 'Memtest86+ loads Memtest86+ on the client "
    . "computer and will have it continue to run until stopped. "
    . "When you are done, you must remember to remove the PXE file, "
    . "by clicking on \"Active Tasks\" and clicking on the "
    . "\"Kill Task\" button.', 'memtest.png', 'fog/memtest/memtest', "
    . "'', 'fog', '1', 'both'),"
    . "(5, 'Test Disk', 'Test Disk loads the testdisk utility "
    . "that can be used to check a hard disk and recover lost "
    . "partitions.', 'testdisk.png', '', "
    . "'mode=checkdisk', 'fog', '1', 'both'),"
    . "(6, 'Disk Surface Test', 'Disk Surface Test checks the hard "
    . "drive\'s surface sector by sector for any errors and reports "
    . "back if errors are present.', 'surfacetest.png', '', "
    . "'mode=badblocks', 'fog', '1', 'both'),"
    . "(7, 'Recover', 'Recover loads the photorec utility that can "
    . "be used to recover lost files from a hard disk. When recovering "
    . "files, make sure you save them to your NFS volume "
    . "(ie: /images).', 'recover.png', '', 'mode=photorec', "
    . "'fog', '1', 'both'),"
    . "(8, 'Multi-Cast', 'Deploy action will send an image saved "
    . "on the FOG server to the client computer with all included "
    . "snapins.', 'senddebug.png', '', 'type=down mc=yes', 'fog', "
    . "'0', 'group'),"
    . "(10, 'Hardware Inventory', 'The hardware inventory task will "
    . "boot the client computer and pull basic hardware information "
    . "from it and report it back to the FOG server.', "
    . "'inventory.png', '', 'mac_deployed=\${HOST_MAC} mode=autoreg "
    . "deployed=1', 'fog', '1', 'both'),"
    . "(11, 'Password Reset', 'Password reset will blank out a "
    . "Windows user password that may have been lost or "
    . "forgotten.', 'winpass.png', '', 'mode=winpassreset', "
    . "'fog', '1', 'both'),"
    . "(12, 'All Snapins', 'This option allows you to send all "
    . "the snapins to host without imaging the computer. "
    . "(Requires FOG Client to be installed on client)', "
    . "'snap.png', '', '', 'fog', '1', 'both'),"
    . "(13, 'Single Snapin', 'This option allows you to send "
    . "a single snapin to a host. (Requires FOG Client to be "
    . "installed on client)', 'snap.png', '', '', 'fog', '1', 'both'),"
    . "(14, 'Wake-Up', 'Wake Up will attempt to send the "
    . "Wake-On-LAN packet to the computer to turn the "
    . "computer on. In switched environments, you "
    . "typically need to configure your hardware to "
    . "allow for this (iphelper).', 'wake.png', '', '', "
    . "'fog', '1', 'both'),"
    . "(15, 'Deploy - Debug', 'Deploy - Debug mode allows "
    . "FOG to setup the environment to allow you send a "
    . "specific image to a computer, but instead of "
    . "sending the image, FOG will leave you at a prompt "
    . "right before sending. If you actually wish to send "
    . "the image all you need to do is type \"fog\" and hit "
    . "enter.', 'senddebug.png', '', 'type=down mode=debug', "
    . "'fog', '1', 'host'),"
    . "(16, 'Capture - Debug', 'mode allows FOG to setup the "
    . "environment to allow you capture a specific image to "
    . "a computer, but instead of capturing the image, FOG "
    . "will leave you at a prompt right before restoring. "
    . "If you actually wish to capture the image all you "
    . "need to do is type \"fog\" and hit enter.', "
    . "'restoredebug.png', '', 'type=up mode=debug', "
    . "'fog', '1', 'host'),"
    . "(17, 'Deploy without Snapins', 'Deploy without snapins "
    . "allows FOG to image the workstation, but after the task "
    . "is complete any snapins linked to the host or group will "
    . "NOT be sent.', 'sendnosnapin.png', '', '', 'fog', '1', "
    . "'both'),"
    . "(18, 'Fast Wipe', 'Full Wipe will boot the client "
    . "computer and perform a full disk wipe. This method "
    . "writes a few passes of random data to the hard disk.',"
    . " 'veryfastwipe.png', '', 'mode=wipe wipemode=fast',"
    . "'fog', '1', 'both'),"
    . "(19, 'Normal Wipe', 'Normal Wipe will boot the client "
    . "computer and perform a simple disk wipe. This method "
    . "writes one pass of zero\'s to the hard disk.', "
    . "'quickwipe.png', '', 'mode=wipe wipemode=normal', "
    . "'fog', '1', 'both'),"
    . "(20, 'Full Wipe', 'Full Wipe will boot the client "
    . "computer and perform a full disk wipe. This method "
    . "writes a few passes of random data to the hard disk.',"
    . "'fullwipe.png', '', 'mode=wipe wipemode=full', 'fog',"
    . "'1', 'both'),"
    . "(21, 'Virus Scan', 'Anti-Virus loads Clam AV on the "
    . "client boot image, updates the scanner and then scans "
    . "the Windows partition.', 'clam.png', '', 'mode=clamav "
    . "avmode=s', 'fog', '1', 'both'),"
    . "(22, 'Virus Scan - Quarantine', 'Anti-Virus loads Clam "
    . "AV on the client boot image, updates the scanner and "
    . "then scans the Windows partition.', 'clam.png', '', "
    . "'mode=clamav avmode=q', 'fog', '1', 'both')"
];
// 34
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `modules` ("
    . "`id` mediumint(9) NOT NULL AUTO_INCREMENT, "
    . "`name` varchar(50) NOT NULL, `short_name` "
    . "varchar(30) NOT NULL, `description` text "
    . "NOT NULL, PRIMARY KEY (`id`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `modules` "
    . "(`id`, `name`, `short_name`, `description`) "
    . "VALUES "
    . "(1,'Directory Cleaner','dircleanup','This setting will enable or "
    . "disable the directory cleaner service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(2,'User Cleanup','usercleanup','This setting will enable or "
    . "disable the user cleaner service module on this specific host. If "
    . "the module is globally disabled, this setting is ignored. The user "
    . "clean up service will remove all stale users on the local machine, "
    . "accept for user accounts that are whitelisted. This is typically "
    . "used when dynamic local users is implemented on the workstation.'),"
    . "(3,'Display Manager','displaymanager','This setting will enable or "
    . "disable the display manager service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(4,'Auto Log Out','autologout','This setting will enable or "
    . "disable the auto log out service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(5,'Green FOG','greenfog','This setting will enable or "
    . "disable the green fog service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(6,'Snapins','snapin','This setting will enable or disable "
    . "the snapin service module on this specific host. If the module "
    . "is globally disabled, this setting is ignored.'),"
    . "(7,'Client Updater','clientupdater','This setting will enable or "
    . "disable the client updater service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(8,'Host Registration','hostregister','This setting will enable or "
    . "disable the host register service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(9,'Hostname Changer','hostnamechanger','This setting will enable or "
    . "disable the hostname changer module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(10,'Printer Manager','printermanager','This setting will enable or "
    . "disable the printer manager service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(11,'Task Reboot','taskreboot','This setting will enable or "
    . "disable the task reboot service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.'),"
    . "(12,'User Tracker','usertracker','This setting will enable or "
    . "disable the user tracker service module on this specific host. "
    . "If the module is globally disabled, this setting is ignored.')",
    "DELETE FROM `moduleStatusByHost` WHERE `msState`='0'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='1' WHERE `msModuleID`='dircleanup'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='2' WHERE `msModuleID`='usercleanup'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='3' WHERE `msModuleID`='displaymanager'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='4' WHERE `msModuleID`='autologout'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='5' WHERE `msModuleID`='greenfog'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='6' WHERE `msModuleID`='snapin'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='7' WHERE `msModuleID`='clientupdater'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='8' WHERE `msModuleID`='hostregister'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='9' WHERE `msModuleID`='hostnamechanger'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='10' WHERE `msModuleID`='printermanager'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='11' WHERE `msModuleID`='taskreboot'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='12' WHERE `msModuleID`='usertracker'",
    "ALTER TABLE `moduleStatusByHost` CHANGE "
    . "`msModuleID` `msModuleID` INT NOT NULL",
    "ALTER TABLE `moduleStatusByHost` ADD UNIQUE "
    . "(`msHostID`,`msModuleID`)",
    "ALTER TABLE `snapinAssoc` ADD UNIQUE (`saHostID` ,`saSnapinID`)",
];
// 35
$this->schema[] = [
    "TRUNCATE `taskStates`",
    "INSERT IGNORE INTO `taskStates` "
    . "(`tsID`,`tsName`,`tsDescription`,`tsOrder`) "
    . "VALUES "
    . "(1,'Queued','Task has been created and FOG is waiting for the Host "
    . "to check-in.','1'),"
    . "(2,'Checked In','PC has checked in and is in queue for imaging','2'),"
    . "(3,'In-Progress','Host is currently Imaging.','3'),"
    . "(4,'Complete','Imaging has been completed.','4'),"
    . "(5,'Cancelled','Task was aborted by user','5')"
];
// 36
$this->schema[] = [
    "ALTER TABLE `groups` ADD UNIQUE ( `groupName` )",
];
// 37
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `taskLog` ("
    . "`id` mediumint(9) NOT NULL AUTO_INCREMENT,"
    . "`taskID` mediumtext NOT NULL,"
    . "`taskStateID` mediumint(9) NOT NULL,"
    . "`ip` varchar(15) NOT NULL,"
    . "`createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    . "`createdBy` VARCHAR(30) NOT NULL,"
    . "PRIMARY KEY (`id`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
];
// 38
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD UNIQUE (`ngmMemberName`)",
    "ALTER TABLE `nfsGroups` ADD UNIQUE (`ngName`)"
];
// 39
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`,`osName`,`osDescription`) "
    . "VALUES "
    . "('6','Windows 8','')",
    "ALTER TABLE `hosts` drop column `hostOS`"
];
// 40
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PIGZ_COMP','PIGZ Compression Rating','9','FOG PXE Settings')",
];
// 41
$this->schema[] = [
    "ALTER TABLE `imagingLog` ADD `ilType` VARCHAR(64) NOT NULL"
];
// 42
$this->schema[] = [
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` BIGINT NOT NULL"
];
// 43
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_KEY_SEQUENCE','Key Sequence for boot prompt.','0','FOG Boot Setting')"
];
// 44
$this->schema[] = [
    "CREATE TABLE `keySequence` ("
    . "`ksID` INTEGER NOT NULL AUTO_INCREMENT,"
    . "`ksValue` varchar(25) NOT NULL,"
    . "`ksAscii` varchar(25) NOT NULL,"
    . "PRIMARY KEY (`ksID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
];
$keySequences = [
    'CTRL + A' => '0x01',
    'CTRL + B' => '0x02',
    'CTRL + C' => '0x03',
    'CTRL + D' => '0x04',
    'CTRL + E' => '0x05',
    'CTRL + F' => '0x06',
    'CTRL + G' => '0x07',
    'CTRL + H' => '0x08',
    'CTRL + I' => '0x09',
    'CTRL + J' => '0x0a',
    'CTRL + K' => '0x0b',
    'CTRL + L' => '0x0c',
    'CTRL + M' => '0x0d',
    'CTRL + N' => '0x0e',
    'CTRL + O' => '0x0f',
    'CTRL + P' => '0x10',
    'CTRL + Q' => '0x11',
    'CTRL + R' => '0x12',
    'CTRL + S' => '0x13',
    'CTRL + T' => '0x14',
    'CTRL + U' => '0x15',
    'CTRL + V' => '0x16',
    'CTRL + W' => '0x17',
    'CTRL + X' => '0x18',
    'CTRL + Y' => '0x19',
    'CTRL + Z' => '0x1a',
    'F5' => '0x107e',
    'F6' => '0x127e',
    'F7' => '0x137e',
    'F8' => '0x147e',
    'F9' => '0x157e',
    'F10' => '0x167e',
    'F11' => '0x187e',
    'F12' => '0x197e',
    'ESC' => '0x1b',
];
// 45 - 79 setup
$keys = [];
foreach ($keySequences as $value => $ascii) {
    $this->schema[] = [];
    $keys[] = sprintf(
        "('%s','%s')",
        $value,
        $ascii
    );
}
// 79
$this->schema[count($this->schema ?: []) - 1] = [
    "INSERT IGNORE INTO `keySequence` "
    . "(`ksValue`,`ksAscii`) "
    . "VALUES "
    . implode(',', $keys)
];
// 80
$this->schema[] = [
    "ALTER TABLE `tasks` "
    . "ADD COLUMN `taskShutdown` char "
    . "NOT NULL AFTER `taskLastMemberID`",
];
// 81
$this->schema[] = [
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageLegacy` char NOT NULL AFTER `imageOSID`",
    "UPDATE `images` set imageLegacy = '1' where 1 = 1",
];
// 82
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_LEGACY_FLAG_IN_GUI','This setting allows you to set "
    . "whether or not an image is legacy. "
    . "(Valid values are 0 or 1)','0','General Settings')"
];
// 83
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PROXY_USERNAME','This setting defines the proxy username to use.',"
    . "'','General Settings'),"
    . "('FOG_PROXY_PASSWORD','This setting defines the proxy password to use.',"
    . "'','General Settings')",
    "UPDATE `globalSettings` SET `settingCategory`='Proxy Settings' "
    . "WHERE `globalSettings`.`settingKey` LIKE 'FOG_PROXY%'",
];
// 84
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_NO_MENU','This setting sets the system to no menu, if "
    . "there is no task set, it boots to first device.','','FOG Boot Settings')",
];
// 85
$this->schema[] = [
    "UPDATE `globalSettings` SET `settingCategory`='FOG Boot Settings' "
    . "WHERE `settingCategory`='FOG PXE Settings' OR "
    . "`settingCategory`='FOG Boot Setting'",
];
// 86
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_TFTP_PXE_KERNEL_32','Location of the 32 bit kernel file on "
    . "the PXE server, this should point to the kernel itself.',"
    . "'bzImage32','TFTP Server'),"
    . "('FOG_PXE_BOOT_IMAGE_32','The settings defines where the 32 bit "
    . "fog boot file system image is located.','init_32.xz','TFTP Server')",
];
// 87 - used to be FOG_MINING_ENABLE but was entirely removed.
$this->schema[] = [];
// 88
$this->schema[] = [
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageLastDeploy` DATETIME NOT NULL AFTER `imageLegacy`",
];
// 89
$this->schema[] = [
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostLastDeploy` DATETIME NOT NULL AFTER `hostCreateDate`",
];
// 90
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_BOOT_EXIT_TYPE','The method of booting to the hard drive. "
    . "Most will accept sanboot, but some require exit.','','FOG Boot Settings')",
];
// 91 - used to be FOG_MINING_MAX_CORES but was entirely removed
$this->schema[] = [];
// 92
$this->schema[] = [
    "ALTER TABLE `snapinJobs` "
    . "ADD COLUMN `sjStateID` INT(11) NOT NULL AFTER `sjHostID`",
];
// 93
$this->schema[] = [
    "ALTER TABLE `snapinJobs` CHANGE `sjStateID` `sjStateID` INT(11) NOT NULL",
];
// 94
$this->schema[] = [
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`,`ttName`,`ttDescription`,`ttIcon`,`ttKernel`,"
    . "`ttKernelArgs`,`ttType`,`ttIsAdvanced`,`ttIsAccess`) "
    . "VALUES "
    . "(23,'Donate','This task will run a program to mine "
    . "cryptocurrency that will be donated to the FOG Project.',"
    . "'donate.png','','mode=donate.full','fog','1','both')",
];
// 95 - used to be two FOG_MINING_* settings but were entirely removed.
$this->schema[] = [];
// 96
$this->schema[] = [
    "ALTER TABLE `tasks` ADD COLUMN `taskPassreset` "
    . "varchar(250)  NOT NULL AFTER `taskLastMemberID`",
];
// 97
$this->schema[] = [
    "truncate table `tasks`",
];
// 98
$this->schema[] = [
    "UPDATE `globalSettings` set `settingValue`='bzImage' "
    . "WHERE `settingKey`='FOG_TFTP_PXE_KERNEL'",
    "UPDATE `globalSettings` set `settingValue` = '"
    . BASEPATH
    . DS
    . "service"
    . DS
    . "ipxe"
    . DS
    . "' WHERE settingKey = 'FOG_TFTP_PXE_KERNEL_DIR'",
    "UPDATE `globalSettings` set `settingValue`='init.xz' "
    . "WHERE `settingKey`='FOG_PXE_BOOT_IMAGE'",
    "UPDATE `globalSettings` set `settingValue`='memtest.bin' "
    . "WHERE `settingKey`='FOG_MEMTEST_KERNEL'",
];
// 99 - used to be FOG_MINING_* settings but were entirely removed
$this->schema[] = [];
// 100
$this->schema[] = [
    "UPDATE `imageTypes` SET `imageTypeName`="
    . "'Single Disk (NTFS Only, Resizable)' "
    . "WHERE `imageTypes`.`imageTypeName`='Single Partition (NTFS Only, Resizable)'",
];
// 101
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_DATA_RETURNED','This setting presents the search bar "
    . "if list has more returned than this number. "
    . "(A value of 0 disables it)','0','FOG View Settings')",
];
// 102
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_GROUP_ASSOC','Allows a group to be assigned "
    . "during quick registration. Default is no group "
    . "assigned.','0','FOG Quick Registration')",
];
// 103
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`,`osName`,`osDescription`) "
    . "VALUES "
    . "('7','Windows 8.1','')",
];
// 104
$this->schema[] = [
    "ALTER TABLE `inventory` "
    . "ADD COLUMN `iDeleteDate` datetime NOT NULL AFTER `iCreateDate`",
];
// 105
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`)"
    . "VALUES "
    . "('FOG_ALWAYS_LOGGED_IN','This setting allows user to "
    . "be signed in all the time or not. A value of 0 "
    . "disables it.','0','Login Settings'),"
    . "('FOG_INACTIVITY_TIMEOUT','This setting allows user to "
    . "be signed in all the time or not. Between 1 and 24 by "
    . "hours.','1','Login Settings'),"
    . "('FOG_REGENERATE_TIMEOUT','This setting allows user to "
    . "be signed in all the time or not. Between 0.25 and 24 "
    . "by hours.','0.5','Login Settings')",
];
// 106
$this->schema[] = [
    "ALTER TABLE `images` CHANGE `imageLegacy` `imageFormat` char",
    "UPDATE `globalSettings` SET `settingKey`='FOG_FORMAT_FLAG_IN_GUI' "
    . "WHERE `settingKey`='FOG_LEGACY_FLAG_IN_GUI'",
];
// 107
$this->schema[] = [
    "DELETE FROM `globalSettings` WHERE `settingCategory`='SSH Client'",
    "UPDATE `globalSettings` SET "
    . "`settingCategory`='FOG Client - Snapins' WHERE "
    . "`settingKey`='FOG_SNAPINDIR'",
];
// 108
$this->schema[] = [
    "UPDATE `globalSettings` SET `settingDesc`='This setting defines "
    . "if the fog printer manager should be globally active. "
    . "(Valid values are 0 or 1)' WHERE "
    . "`settingKey`='FOG_CLIENT_PRINTERMANAGER_ENABLED'",
];
// 109
$this->schema[] = [
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageMagnetUri` longtext  NOT NULL AFTER `imagePath`",
];
// 110
$this->schema[] = [
    "UPDATE taskTypes SET ttKernelArgs='type=down' WHERE ttID='17'",
];
// 111
$this->schema[] = [
    "UPDATE `imageTypes` SET `imageTypeName`='Single Disk - Resizable' "
    . "WHERE `imageTypes`.`imageTypeName`='Single Disk (NTFS Only, Resizable)'",
];
// 112
$this->schema[] = [
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostProductKey` varchar(50) NOT NULL AFTER `hostADPass`",
];
// 113
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_ADVANCED_MENU_LOGIN','This setting enforces a login "
    . "parameter to get into the advanced menu.','0','FOG Boot Settings')",
];
// 114
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES ('8', 'Apple Mac OS', '')",
];
// 115
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_TASK_FORCE_REBOOT','This setting enables or disables "
    . "the Force reboot of tasks. This only affects if users are "
    . "logged in. If users are logged in, the host will not "
    . "reboot if this is disabled.','0','FOG Client - Task Reboot')",
];
// 116
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_CHECKIN_TIME','This setting returns the client "
    . "service checkin times to the server.','60','FOG Client')",
    "UPDATE modules SET short_name='snapinclient' WHERE short_name='snapin'",
];
// 117
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_UDPCAST_MAXWAIT','This setting sets the max time to "
    . "wait for other clients before starting the session in "
    . "minutes.','10','Multicast Settings')",
];
// 118
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_AES_ENCRYPT','This setting turns on or off the FOG Client "
    . "on the client machine to send the data encrypted with AES. If "
    . "you select this and you do not have the new FOG Client installed "
    . "on your system, the old client will be broken. This will only be "
    . "relevant if you have the FOG_NEW_CLIENT enabled as well.'"
    . ",'0','FOG Client'),"
    . "('FOG_NEW_CLIENT','This setting turns on or off the new client. "
    . "If this is selected, and the clients do not have the new client "
    . "installed, things should still work unless you also check "
    . "the FOG_AES_ENCRYPT box.','0','FOG Client'),"
    . "('FOG_CLIENT_MAXSIZE','This setting specifies the MAX size of "
    . "the fog.log before it rolls over. It will only work for new "
    . "clients.','204800000','FOG Client'),"
    . "('FOG_AES_PASS_ENCRYPT_KEY','This setting just stores the AES "
    . "Encryption Key. It will only work for new clients. This is the "
    . "key used for encrypting all traffic back and forth between the "
    . "client and server','7NFJUuQTYLZIoea32DsP9V6f0tbWnzMy','FOG Client'),"
    . "('FOG_AES_ADPASS_ENCRYPT_KEY','This setting just stores the AES "
    . "Encryption ADPass encryption key. It will only work for new "
    . "clients. This is the key used for encrypting ADPass in AES "
    . "format. If FOG_NEW_CLIENT is selected, to set the ADPass "
    . "you simply type the plain text password and click update. "
    . "It will automatically encrypt and store the encrypted "
    . "password in the database for you.',"
    . "'jPlUQRw5vLsrz8I1TuZdWDSiMFqXHtcm','FOG Client')",
];
// 119
$column = array_filter((array)DatabaseManager::getColumns('default', 'modules'));
$this->schema[] = count($column ?: []) > 0 ? [] : [
    "ALTER TABLE `modules` ADD COLUMN `default` INT "
    . "DEFAULT 1 NOT NULL AFTER `description`"
];
// 120
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `imagePartitionTypes` ("
    . "`imagePartitionTypeID` mediumint(9) NOT NULL auto_increment,"
    . "`imagePartitionTypeName` varchar(100) NOT NULL,"
    . "`imagePartitionTypeValue` varchar(10) NOT NULL,"
    . "PRIMARY KEY  (`imagePartitionTypeID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `imagePartitionTypes` "
    . "(`imagePartitionTypeID`, `imagePartitionTypeName`,"
    . "`imagePartitionTypeValue`)"
    . "VALUES "
    . "(1, 'Everything', 'all'),"
    . "(2, 'Partition Table and MBR only', 'mbr'),"
    . "(3, 'Partition 1 only', '1'),"
    . "(4, 'Partition 2 only', '2'),"
    . "(5, 'Partition 3 only', '3'),"
    . "(6, 'Partition 4 only', '4'),"
    . "(7, 'Partition 5 only', '5'),"
    . "(8, 'Partition 6 only', '6'),"
    . "(9, 'Partition 7 only', '7'),"
    . "(10, 'Partition 8 only', '8'),"
    . "(11, 'Partition 9 only', '9'),"
    . "(12, 'Partition 10 only', '10')"
];
// 121
$this->schema[] = [
    "ALTER TABLE `images` ADD COLUMN `imagePartitionTypeID` "
    . "mediumint(9) NOT NULL AFTER `imageTypeID`",
    "UPDATE images SET imagePartitionTypeID='1'",
];
// 122
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `pxeMenu` ("
    . "`pxeID` mediumint(9) NOT NULL auto_increment,"
    . "`pxeName` varchar(100) NOT NULL,"
    . "`pxeDesc` longtext  NOT NULL,"
    . "`pxeParams` longtext NOT NULL,"
    . "`pxeRegOnly` INT DEFAULT 0 NOT NULL,"
    . "`pxeArgs` varchar(250) NULL,"
    . "`pxeDefault` INT DEFAULT 0 NOT NULL,"
    . "PRIMARY KEY (`pxeID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `pxeMenu` "
    . "(`pxeID`,`pxeName`,`pxeDesc`,`pxeDefault`,`pxeRegOnly`,`pxeArgs`) "
    . "VALUES "
    . "(1, 'fog.local', 'Boot from hard disk', '1','2',NULL),"
    . "(2, 'fog.memtest', 'Run Memtest86+', '0','2',NULL),"
    . "(3, 'fog.reginput', 'Perform Full Host Registration "
    . "and Inventory','0','0','mode=manreg'),"
    . "(4, 'fog.keyreg', 'Update Product Key', '0','1',NULL),"
    . "(5, 'fog.reg', 'Quick Registration and Inventory', '0','0','mode=autoreg'),"
    . "(6, 'fog.deployimage', 'Deploy Image', '0', '1',NULL),"
    . "(7, 'fog.multijoin', 'Join Multicast Session', '0','1',NULL),"
    . "(8, 'fog.quickdel', 'Quick Host Deletion','0','1',NULL),"
    . "(9, 'fog.sysinfo', 'Client System Information "
    . "(Compatibility)','0','2','mode=sysinfo'),"
    . "(10, 'fog.debug', 'Debug Mode','0','3','mode=onlydebug'),"
    . "(11, 'fog.advanced', 'Advanced Menu','0','4',NULL),"
    . "(12, 'fog.advancedlogin', 'Advanced Menu','0','5',NULL)",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param qihost 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.deployimage'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param delhost 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.quickdel'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param keyreg 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.keyreg'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param debugAccess 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.debug'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param sessionJoin 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.multijoin'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param advLog 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.advancedlogin'",
];
// 123
$this->schema[] = [];
// 124
$this->schema[] = [];
// 125
$this->schema[] = [
    "UPDATE `taskTypes` SET ttKernelArgs='mc=bt type=down' WHERE ttID='24'",
];
// 126
$this->schema[] = [
    "ALTER TABLE `tasks` ADD COLUMN `taskIsDebug` mediumint(9) "
    . "NOT NULL AFTER `taskStateID`",
];
// 127
$this->schema[] = [
    "ALTER TABLE `images` ADD COLUMN `imageProtect` mediumint(9) "
    . "NOT NULL AFTER `imagePath`",
];
// 128
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostPending` mediumint(9) NULL",
];
// 129
$this->schema[] = [
    "INSERT IGNORE INTO `pxeMenu` "
    . "(`pxeID`,`pxeName`,`pxeDesc`,`pxeDefault`,`pxeRegOnly`,`pxeArgs`) "
    . "VALUES "
    . "(13, 'fog.approvehost', 'Approve This Host','0','6',NULL)",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param approveHost 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.approvehost'",
];
// 130
$this->schema[] = self::fastmerge(
    [
        "ALTER TABLE `hostMAC` ADD COLUMN `hmPrimary` INT DEFAULT 0 NOT NULL",
        "ALTER TABLE `hostMAC` ADD COLUMN `hmPending` INT DEFAULT 0 NOT NULL",
        "ALTER TABLE `hostMAC` ADD COLUMN `hmIgnoreClient` INT DEFAULT 0 NOT NULL",
        "ALTER TABLE `hostMAC` ADD COLUMN `hmIgnoreImaging` INT DEFAULT 0 NOT NULL",
        "INSERT IGNORE INTO `hostMAC` "
        . "(`hmHostID`,`hmMAC`,`hmIgnoreClient`,`hmIgnoreImaging`,"
        . "`hmPending`,`hmPrimary`) "
        . "SELECT `hostID`,`hostMAC`,'0','0','0','1' FROM `hosts` "
        . "WHERE `hosts`.`hostMAC` IS NOT NULL",
        "INSERT IGNORE INTO `hostMAC` "
        . "(`hmMAC`,`hmHostID`,`hmPending`) "
        . "SELECT `pmAddress`,`pmHostID`,'1' FROM `pendingMACS` "
        . "WHERE `pmAddress` IS NOT NULL",
        "ALTER TABLE `hosts` DROP COLUMN `hostMAC`",
        "DROP TABLE `pendingMACS`"
    ],
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'hostMAC',
            [
                'hmHostID',
                'hmMAC'
            ]
        ],
        true
    )
);
// 131
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `ipxeTable` ("
    . "`ipxeID` mediumint(9) NOT NULL auto_increment,"
    . "`ipxeProduct` longtext NOT NULL,"
    . "`ipxeManufacturer` longtext NOT NULL,"
    . "`ipxeFilename` longtext NOT NULL,"
    . "`ipxeMAC` VARCHAR(17) NOT NULL,"
    . "`ipxeSuccess` VARCHAR(2) NOT NULL,"
    . "`ipxeFailure` VARCHAR(2) NOT NULL,"
    . "PRIMARY KEY (`ipxeID`)"
    . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_DHCP_BOOTFILENAME','This setting just sets what is "
    . "in use for the boot filename. It is up to the admin to "
    . "ensure this setting is correct for their database to be "
    . "accurate. Default setting is undionly.kpxe',"
    . "'undionly.kpxe','TFTP Server')",
];
// 132
$column = array_filter(
    (array)DatabaseManager::getColumns(
        'ipxeVersion',
        'ipxeTable'
    )
);
$this->schema[] = count($column ?: []) ? [] : [
    "ALTER TABLE `ipxeTable` ADD COLUMN `ipxeVersion` LONGTEXT NOT NULL",
];
// 133
$snapindir = self::getSetting('FOG_SNAPINDIR');
if (!$snapindir) {
    $snapindir = '/opt/fog/snapins';
}
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmSnapinPath` "
    . "LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmSnapinPath`='"
    . $snapindir
    . "'",
];
// 134
$this->schema[] = [
    "ALTER TABLE `snapins` ADD COLUMN `snapinNFSGroupID` INT(11) NOT NULL",
];
// 135
$this->schema[] = [
    "ALTER TABLE `multicastSessions` ADD COLUMN `msSessClients` "
    . "INT(11) NOT NULL AFTER msClients",
];
// 136
$this->schema[] = self::fastmerge(
    [
        "ALTER TABLE `tasks` ADD COLUMN `taskImageID` "
        . "INT(11) NOT NULL AFTER `taskHostID`",
        "CREATE TABLE IF NOT EXISTS `imageGroupAssoc` ("
        . "`igaID` mediumint(9) NOT NULL auto_increment,"
        . "`igaImageID` mediumint(9) NOT NULL,"
        . "`igaStorageGroupID` mediumint(9) NOT NULL,"
        . "`igaPrimary` ENUM('0','1') NOT NULL,"
        . "PRIMARY KEY (`igaID`)"
        . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
        "INSERT IGNORE INTO `imageGroupAssoc` "
        . "(`igaImageID`,`igaStorageGroupID`) "
        . "SELECT `imageID`,`imageNFSGroupID` FROM "
        . "`images` WHERE `imageNFSGroupID` IS NOT NULL",
        "UPDATE `imageGroupAssoc` SET `igaPrimary`='1'",
        "ALTER TABLE `images` DROP COLUMN `imageNFSGroupID`"
    ],
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'imageGroupAssoc',
            [
                'igaImageID',
                'igaImageID'
            ]
        ],
        true
    )
);
// 137
$this->schema[] = [
    "ALTER TABLE `scheduledTasks` ADD COLUMN `stImageID` "
    . "INT(11) NOT NULL AFTER `stGroupHostID`",
];
// 138
$this->schema[] = [
    "ALTER TABLE `imageGroupAssoc` DROP INDEX `igaImageID`",
];
// 139
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MEMORY_LIMIT','Default setting is the memory limit "
    . "set in php.ini.','128','General Settings'),"
    . "('FOG_EMAIL_ACTION','Enables email reports of image "
    . "actions as they\'re completed. Default setting is disabled.',"
    . "'0','FOG Email Settings'),"
    . "('FOG_EMAIL_ADDRESS','Email address(s) to send the reports to. "
    . "Multiple emails just separate by comma "
    . "(e.g. email1@domain.com,email2@domain2.com)','','FOG Email Settings'),"
    . "('FOG_EMAIL_BINARY','Path and arguments to the emailing binary "
    . "php should use for the mail function. Default is "
    . "\'/usr/sbin/sendmail -t -f noreply@\$\{server-name\}.com "
    . "-i\'','/usr/sbin/sendmail -t -f "
    . "noreply@\$\{server-name\}.com -i','FOG Email Settings'),"
    . "('FOG_FROM_EMAIL','Email from address. Default is fogserver. "
    . "\$\{server-name\} is set to the node name.',"
    . "'noreply@\$\{server-name\}.com','FOG Email Settings')",
];
// 140
$this->schema[] = self::fastmerge(
    [
        "CREATE TABLE IF NOT EXISTS `snapinGroupAssoc` ("
        . "`sgaID` mediumint(9) NOT NULL auto_increment,"
        . "`sgaSnapinID` mediumint(9) NOT NULL,"
        . "`sgaStorageGroupID` mediumint(9) NOT NULL,"
        . "`sgaPrimary` ENUM('0','1') NOT NULL,"
        . "PRIMARY KEY (`sgaID`)"
        . ') ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC',
        "INSERT IGNORE INTO `snapinGroupAssoc` "
        . "(`sgaSnapinID`,`sgaStorageGroupID`) "
        . "SELECT `sID`,`snapinNFSGroupID` FROM `snapins` "
        . "WHERE `snapinNFSGroupID` IS NOT NULL",
        "UPDATE `snapinGroupAssoc` SET `sgaPrimary`='1'",
        "ALTER TABLE `snapins` DROP COLUMN `snapinNFSGroupID`"
    ],
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'snapinGroupAssoc',
            [
                'sgaSnapinID',
                'sgaSnapinID'
            ],
            'sgaSnapinID'
        ],
        true
    )
);
// 141
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_PXE_HIDDENMENU_TIMEOUT', 'This setting defines the default "
    . "value for the pxe hidden menu timeout.', '3', 'FOG Boot Settings')",
];
// 142
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_USED_TASKS', 'This setting defines tasks to consider "
    . "\'Used\' in the task count. Listing is comma separated, "
    . "using the ID\'s of the tasks.', '1,15,17', 'General Settings')",
];
// 143
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_GRACE_TIMEOUT', 'This setting defines the grace period "
    . "for the reboots and shutdowns. The value is specified in seconds.',"
    . "'60', 'FOG Client')",
];
// 144
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmBandwidthLimit` "
    . "INT(20) NOT NULL AFTER `ngmMaxClients`",
];
// 145
$this->schema[] = [
    "UPDATE `pxeMenu` SET `pxeRegOnly`='2' WHERE pxeID='7'",
];
// 146
$this->schema[] = [
    "UPDATE `pxeMenu` SET `pxeRegOnly`='2' WHERE pxeID='6'",
];
// 147
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostPubKey` LONGTEXT",
];
// 148
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_SNAPIN_LIMIT', 'This setting defines the maximum snapins "
    . "allowed to be assigned to a host. Value of 0 means unlimted.', "
    . "'0', 'General Settings')",
];
// 149
$this->schema[] = [
    "ALTER TABLE `images` ADD COLUMN `imageCompress` INT(11)",
];
// 150
$this->schema[] = [
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_JPGRAPH_VERSION'",
];
// 151
$this->schema[] = [
    "ALTER TABLE `taskTypes` ENGINE=InnoDB",
    "ALTER TABLE `taskStates` ENGINE=InnoDB",
    "ALTER TABLE `taskLog` ENGINE=InnoDB",
    "ALTER TABLE `os` ENGINE=InnoDB",
    "ALTER TABLE `modules` ENGINE=InnoDB",
];
// 152
$this->schema[] = [
    "ALTER TABLE `imageGroupAssoc` ADD UNIQUE(`igaImageID`,`igaStorageGroupID`)",
    "ALTER TABLE `snapinGroupAssoc` ADD UNIQUE(`sgaSnapinID`,`sgaStorageGroupID`)",
];
// 153
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_FTP_IMAGE_SIZE', 'This setting defines the global enabling "
    . "of image on server size. Checkbox on or off is the enabling element. "
    . "Default is off.','0','General Settings')",
];
// 154
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MULTICAST_ADDRESS','This setting defines an alternate "
    . "Multicast Address. Default is 0 which means disabled, value "
    . "will be ip validated if entered.','0','Multicast Settings'),"
    . "('FOG_MULTICAST_PORT_OVERRIDE','This setting defines an "
    . "override multicast port address, which of course remains "
    . "static if set. Valid values are 0 thru 65535 and will be "
    . "checked on save. Default is 0 which is disabled.','0',"
    . "'Multicast Settings')",
];
// 155
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MULTICAST_DUPLEX','This setting defines the duplex value. "
    . "Default is FULL_DUPLEX.','--full-duplex','Multicast Settings')",
];
// 156
$this->schema[] = [
    "UPDATE `globalSettings` SET `settingValue`='default/fog.css' "
    . "WHERE `settingKey`='FOG_THEME'",
];
// 157, doesn't do anything but ensure all currently create tables are InnoDB
$this->schema[] = [];
// 158
$this->schema[] = [];
// 159
$this->schema[] = [];
// 160
$this->schema[] = [];
// 161
$this->schema[] = self::fastmerge(
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'greenFog',
            ['gfHostID']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'groups',
            ['groupName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'hosts',
            ['hostName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'hostScreenSettings',
            ['hssHostID']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'imagePartitionTypes',
            ['imagePartitionTypeName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'imageTypes',
            ['imageTypeValue']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'images',
            ['imageName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'inventory',
            ['iHostID']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'modules',
            ['short_name']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'nfsGroups',
            ['ngName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'os',
            ['osName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'plugins',
            ['pName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'printers',
            ['pAlias']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'snapins',
            ['sName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'supportedOS',
            ['osName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'taskStates',
            ['tsName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'taskTypes',
            ['ttName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'groupMembers',
            [
                'gmHostID',
                'gmGroupID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'hostAutoLogOut',
            ['haloHostID']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'hostMAC',
            ['hmMAC']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'imageGroupAssoc',
            [
                'igaImageID',
                'igaStorageGroupID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'moduleStatusByHost',
            [
                'msHostID',
                'msModuleID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'multicastSessionsAssoc',
            [
                'msID',
                'tID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'nfsFailures',
            [
                'nfNodeID',
                'nfHostID',
                'nfTaskID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'nfsGroupMembers',
            ['ngmMemberName']
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'oui',
            [
                'ouiMACPrefix',
                'ouiMan'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'printerAssoc',
            [
                'paHostID',
                'paPrinterID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'snapinAssoc',
            [
                'saSnapinID',
                'saHostID'
            ]
        ]
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'snapinGroupAssoc',
            [
                'sgaStorageGroupID',
                'sgaSnapinID'
            ]
        ]
    )
);
// 162
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    [
        'snapinTasks',
        [
            'stJobID',
            'stSnapinID'
        ]
    ]
);
// 163
$this->schema[] = [
    "DROP TABLE IF EXISTS `hostFingerprintAssoc`,`queueAssoc`,`nodeJSconfig`",
];
// 164
$this->schema[] = [];
// 165
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_REGISTRATION_ENABLED','This setting enables the capabilities "
    . "to allow registration to occur or not. Default setting is enabled.',"
    . "'1','FOG Boot Settings')",
];
// 166
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_TZ_INFO','This setting allows the user to set the "
    . "system timezone. Default is UTC in the db, but will first "
    . "try the ini set if possible.','UTC','General Settings')",
];
// 167
$this->schema[] = [
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_AES_PASS_ENCRYPT_KEY'",
];
// 168
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_KERNEL_DEBUG','This setting allows the user to have the "
    . "kernel debug flag set. Default is off.','0','FOG Boot Settings')",
];
// 169
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_KERNEL_LOGLEVEL','This setting allows the user to specify "
    . "which loglevel the want. Default is 4.','4','FOG Boot Settings')",
];
// 170
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_FTP_PORT','This setting allows the user to specify the "
    . "ftp port to be used. Default Value is port 21.',"
    . "'21','General Settings'),"
    . "('FOG_FTP_TIMEOUT','This setting allows the user to specify "
    . "the FTP Timeout. This value is entered in seconds. "
    . "Default is 90.','90','General Settings')",
];
// 171
$this->schema[] = [];
// 172
$this->schema[] = [
    "DELETE FROM globalSettings WHERE settingKey='FOG_AES_ADPASS_ENCRYPT_KEY'",
];
// 173
$this->schema[] = [
    "ALTER TABLE `greenFog` DROP INDEX `gfHostID`",
];
// 174
$this->schema[] = [
    "ALTER TABLE `users` DROP KEY new_index1",
    "ALTER TABLE `users` CHANGE `uPass` `uPass` LONGTEXT NOT NULL",
];
// 175
$this->schema[] = [
    "ALTER TABLE `snapins`
    ADD COLUMN `snapinProtect` mediumint(9) NOT NULL",
];
// 176
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_AD_DEFAULT_PASSWORD_LEGACY','This setting defines the "
    . "default value to populate the hosts Active Directory "
    . "password value but only uses the old FOGCrypt method "
    . "of encryption. This setting must be encrypted. The "
    . "FOG_NEW_CLIENT setting will determine if it is going "
    . "to use this or the other value to populate.',"
    . "'','Active Directory Defaults')",
];
// 177
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_NONREG_DEVICE','This setting defines a target disk to "
    . "apply an image to specifically for non-registered hosts. "
    . "If not set, a disk will be selected by the init.',"
    . "'','Non-Registered Host Image')",
];
// 178
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostSecToken` LONGTEXT",
];
// 179
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostSecTime` TIMESTAMP NOT NULL",
];
// 180
$this->schema[] = [
    "UPDATE globalSettings SET settingValue=6 WHERE settingKey='FOG_PIGZ_COMP'",
];
// 181
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "('9', 'Windows 10', '')",
];
// 182
$this->schema[] = [
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param qihost 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.deployimage'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param delhost 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.quickdel'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param keyreg 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.keyreg'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param debugAccess 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.debug'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param sessionJoin 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.multijoin'",
    "UPDATE `pxeMenu` SET `pxeParams`='login\n"
    . "params\n"
    . "param mac0 \${net0/mac}\n"
    . "param arch \${arch}\n"
    . "param username \${username}\n"
    . "param password \${password}\n"
    . "param advLog 1\n"
    . "isset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\n"
    . "isset \${net2/mac} && param mac2 \${net2/mac} || goto bootme' "
    . "WHERE `pxeName`='fog.advancedlogin'",
];
// 183
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` CHANGE `ngmInterface` "
    . "`ngmInterface` VARCHAR (25) CHARACTER SET utf8 "
    . "COLLATE utf8_general_ci NOT NULL DEFAULT '"
    . STORAGE_INTERFACE
    . "'",
];
// 184
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmFTPPath` LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmFTPPath`='"
    . STORAGE_DATADIR
    . "'",
];
// 185
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmMaxBitrate` VARCHAR (25) AFTER `ngmFTPPath`",
];
// 186
$this->schema[] = [
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_NEW_CLIENT'",
    "ALTER TABLE `hosts` ADD COLUMN `hostADPassLegacy` LONGTEXT AFTER `hostADPass`",
    "UPDATE `globalSettings` SET "
    . "`settingDesc`='This setting defines the default value "
    . "to populate the hosts Active Directory password value "
    . "but only uses the old FOGCrypt method of encryption. "
    . "This setting must be encrypted before stored.' "
    . "WHERE `settingKey`='FOG_AD_DEFAULT_PASSWORD_LEGACY'",
    "UPDATE `globalSettings` SET "
    . "`settingDesc`='This setting defines the default value "
    . "to populate the host\'s Active Directory password value. "
    . "This setting will encrypt and store then encrypted value "
    . "of the plain text value entered in this field automatically.' "
    . "WHERE `settingKey`='FOG_AD_DEFAULT_PASSWORD'",
];
// 187
$this->schema[] = [
    "ALTER TABLE `printers` ADD COLUMN `pDesc` LONGTEXT",
];
// 188
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmWebroot` LONGTEXT NOT NULL",
    // GH-529: backfilled every node with a literal '/fog/', which silently
    // moved the nodes of a custom-webroot install to a path that does not
    // exist. There is no per-node value to preserve here -- the column is
    // being created in the line above -- so the server's own webroot is the
    // best guess available.
    "UPDATE `nfsGroupMembers` SET `ngmWebroot`='" . WEB_ROOT . "'",
];
// 189
$this->schema[] = self::fastmerge(
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        [
            'globalSettings',
            [
                'settingKey',
                'settingKey'
            ],
            'settingKey'
        ],
        true
    ),
    [
        "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_PATH'",
        "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_HOST'",
        "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_INTERFACE'"
    ]
);
// 190
$this->schema[] = [
    "ALTER TABLE `hosts` MODIFY `hostADPassLegacy` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostPending` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostPubKey` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostSecToken` LONGTEXT NOT NULL",
];
// 191
$this->schema[] = [
    "UPDATE `taskTypes` set `ttIcon`='download' WHERE `ttID`=1",
    "UPDATE `taskTypes` set `ttIcon`='upload' WHERE `ttID`=2",
    "UPDATE `taskTypes` set `ttIcon`='bug' WHERE `ttID`=3",
    "UPDATE `taskTypes` set `ttIcon`='plus-square-o' WHERE `ttID`=4",
    "UPDATE `taskTypes` set `ttIcon`='hdd-o' WHERE `ttID`=5",
    "UPDATE `taskTypes` set `ttIcon`='user-md' WHERE `ttID`=6",
    "UPDATE `taskTypes` set `ttIcon`='ambulance' WHERE `ttID`=7",
    "UPDATE `taskTypes` set `ttIcon`='share-alt' WHERE `ttID`=8",
    "UPDATE `taskTypes` set `ttIcon`='list-alt' WHERE `ttID`=10",
    "UPDATE `taskTypes` set `ttIcon`='key' WHERE `ttID`=11",
    "UPDATE `taskTypes` set `ttIcon`='cubes' WHERE `ttID`=12",
    "UPDATE `taskTypes` set `ttIcon`='cube' WHERE `ttID`=13",
    "UPDATE `taskTypes` set `ttIcon`='plug' WHERE `ttID`=14",
    "UPDATE `taskTypes` set `ttIcon`='arrow-circle-o-down' WHERE `ttID`=15",
    "UPDATE `taskTypes` set `ttIcon`='arrow-circle-o-up' WHERE `ttID`=16",
    "UPDATE `taskTypes` set `ttIcon`='chevron-circle-down' WHERE `ttID`=17",
    "UPDATE `taskTypes` set `ttIcon`='hourglass-o' WHERE `ttID`=18",
    "UPDATE `taskTypes` set `ttIcon`='hourglass-2' WHERE `ttID`=19",
    "UPDATE `taskTypes` set `ttIcon`='hourglass' WHERE `ttID`=20",
    "UPDATE `taskTypes` set `ttIcon`='exclamation-triangle' WHERE `ttID`=21",
    "UPDATE `taskTypes` set `ttIcon`='flag-o' WHERE `ttID`=22",
    "UPDATE `taskTypes` set `ttIcon`='btc' WHERE `ttID`=23",
    "UPDATE `taskTypes` set `ttIcon`='share-alt-square' WHERE `ttID`=24",
];
// 192
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_EFI_BOOT_EXIT_TYPE','The method (U)EFI uses to boot the "
    . "next boot entry/hard drive. (Default SANBOOT)',"
    . "'sanboot','FOG Boot Settings')",
];
// 193
$this->schema[] = [
    "UPDATE `taskTypes` set `ttName`='Deploy' WHERE `ttID`=1",
    "UPDATE `taskTypes` set `ttName`='Capture' WHERE `ttID`=2",
    "UPDATE `taskTypes` set `ttName`='Deploy - Debug' WHERE `ttID`=15",
    "UPDATE `taskTypes` set `ttName`='Capture - Debug' WHERE `ttID`=16",
    "UPDATE `taskTypes` set `ttName`='Deploy - No Snapins' WHERE `ttID`=17",
];
// 194
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostPingCode` VARCHAR(20)",
];
// 195
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostExitBios` LONGTEXT",
    "ALTER TABLE `hosts` ADD COLUMN `hostExitEfi` LONGTEXT",
];
// 196 this will set all current snapin jobs and taskings to complete
$this->schema[] = [
    "UPDATE `snapinTasks` SET `stState`=4",
    "UPDATE `snapinJobs` SET `sjStateID`=4",
];
// 197
$this->schema[] = [
    "ALTER TABLE`hostMAC` MODIFY `hmMAC` VARCHAR(59) NOT NULL",
];
// 198
$this->schema[] = [];
// 199
$this->schema[] = [
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_AES_ENCRYPT'",
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_DHCP_BOOTFILENAME'",
];
// 200
$this->schema[] = [
    "ALTER TABLE `hosts` MODIFY `hostProductKey` LONGTEXT",
];
// 201
$this->schema[] = [
    "ALTER TABLE `images` ADD `imageEnabled` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `snapins` ADD `sEnabled` ENUM('0','1') NOT NULL DEFAULT '1'",
];
// 202
$this->schema[] = [
    "ALTER TABLE `images` ADD `imageReplicate` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `snapins` ADD `sReplicate` ENUM('0','1') NOT NULL DEFAULT '1'",
];
// 203
$this->schema[] = [
    "ALTER TABLE `taskStates` ADD `tsIcon` varchar(255) NOT NULL",
    "UPDATE `taskStates` SET `tsIcon`='bookmark-o' WHERE `tsID`=1",
    "UPDATE `taskStates` SET `tsIcon`='pause' WHERE `tsID`=2",
    "UPDATE `taskStates` SET `tsIcon`='spinner fa-pulse fa-fw' WHERE `tsID`=3",
    "UPDATE `taskStates` SET `tsIcon`='check-circle' WHERE `tsID`=4",
    "UPDATE `taskStates` SET `tsIcon`='ban' WHERE `tsID`=5",
];
// 204
$this->schema[] = [
    "ALTER TABLE `taskStates` MODIFY `tsID` INT(11) AUTO_INCREMENT",
];
// 205
$this->schema[] = [
    "ALTER TABLE `imagingLog` ADD `ilCreatedBy` VARCHAR(255) NOT NULL"
];
// 206
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('SERVICE_LOG_PATH','The path of which to write logs for the "
    . "linux side fog services. (Default /opt/fog/log/)',"
    . "'/opt/fog/log/','FOG Linux Service Logs'),"
    . "('SERVICE_LOG_SIZE','The maximum size for logs before "
    . "starting new in bytes (Default 1000000)','1000000','FOG Linux Service Logs'),"
    . "('MULTICASTLOGFILENAME','Filename to store the multicast log file to "
    . "(Default multicast.log)','multicast.log','FOG Linux Service Logs'),"
    . "('IMAGEREPLICATORLOGFILENAME','Filename to store the image "
    . "replicator log file to (Default fogreplicator.log)',"
    . "'fogreplicator.log','FOG Linux Service Logs'),"
    . "('SNAPINREPLICATORLOGFILENAME','Filename to store the snapin "
    . "replicator log file to (Default fogsnapinrep.log)',"
    . "'fogsnapinrep.log','FOG Linux Service Logs'),"
    . "('SNAPINHASHLOGFILENAME','Filename to store the snapin hash log "
    . "file to (Default fogsnapinhash.log)','fogsnapinhash.log',"
    . "'FOG Linux Service Logs'),"
    . "('SCHEDULERLOGFILENAME','Filename to store the scheduled "
    . "tasks log file to (Default fogscheduled.log)',"
    . "'fogscheduler.log','FOG Linux Service Logs'),"
    . "('SERVICEMASTERLOGFILENAME','Filename to store "
    . "the service master log file to (Default servicemaster.log)',"
    . "'servicemaster.log','FOG Linux Service Logs'),"
    . "('PINGHOSTLOGFILENAME','Filename to store the ping host log "
    . "file to (Default pinghost.log)','pinghost.log','FOG Linux Service Logs'),"
    . "('PINGHOSTSLEEPTIME','The amount of time between ping host service runs. "
    . "Value is in seconds. (Default 300)','300','FOG Linux Service Sleep Times'),"
    . "('SERVICESLEEPTIME','The amount of time between service master service "
    . "runs. Value is in seconds. This is what restarts failed services. "
    . "(Default 300)','300','FOG Linux Service Sleep Times'),"
    . "('SNAPINREPSLEEPTIME','The amount of time between snapin "
    . "replicator service runs. Value is in seconds. (Default 600)',"
    . "'600','FOG Linux Service Sleep Times'),"
    . "('SNAPINHASHSLEEPTIME','The amount of time between snapin "
    . "hash service runs. Value is in seconds. (Default 1800)',"
    . "'1800','FOG Linux Service Sleep Times'),"
    . "('SCHEDULERSLEEPTIME','The amount of time between task "
    . "scheduler service runs. Value is in seconds. (Default 60)',"
    . "'60','FOG Linux Service Sleep Times'),"
    . "('IMAGEREPSLEEPTIME','The amount of time between image "
    . "replicator service runs. Value is in seconds. (Default 600)',"
    . "'600','FOG Linux Service Sleep Times'),"
    . "('MULTICASTSLEEPTIME','The amount of time between multicast "
    . "service runs. Value is in seconds. (Default 10)',"
    . "'10','FOG Linux Service Sleep Times'),"
    . "('MULTICASTDEVICEOUTPUT','The tty to output to for multicast. "
    . "(Default /dev/tty2)','/dev/tty2','FOG Linux Service TTY Output'),"
    . "('IMAGEREPLICATORDEVICEOUTPUT','The tty to output to for image "
    . "replicator. (Default /dev/tty3)','/dev/tty3',"
    . "'FOG Linux Service TTY Output'),"
    . "('SCHEDULERDEVICEOUTPUT','The tty to output to for task scheduler. "
    . "(Default /dev/tty4)','/dev/tty4','FOG Linux Service TTY Output'),"
    . "('SNAPINREPLICATORDEVICEOUTPUT','The tty to output to for snapin "
    . "replicator. (Default /dev/tty5)','/dev/tty5',"
    . "'FOG Linux Service TTY Output'),"
    . "('SNAPINHASHDEVICEOUTPUT','The tty to output to for snapin "
    . "replicator. (Default /dev/tty5)','/dev/tty6',"
    . "'FOG Linux Service TTY Output'),"
    . "('PINGHOSTDEVICEOUTPUT','The tty to output to for ping hosts. "
    . "(Default /dev/tty6)','/dev/tty6','FOG Linux Service TTY Output')",
];
// 207
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_WIPE_TIMEOUT', 'This setting defines the number of "
    . "seconds to wait for wiping disks. (Default 60)',"
    . "'60', 'FOG Boot Settings')",
];
// 208
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_BANDWIDTH_TIME', 'This setting defines how often to "
    . "refresh the bandwidth chart. Values are in seconds',"
    . "'1','General Settings')",
];
// 209
$this->schema[] = [
    "ALTER TABLE `printers` ADD `pConfigFile` VARCHAR(255) NOT NULL AFTER `pConfig`",
];
// 210
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttDescription`='Fast wipe will boot "
    . "the client computer and wipe the first few sectors of data "
    . "on the hard disk. Data will not be overwritten but the boot "
    . "up of the disk and partition layout will no longer exist.' "
    . "WHERE `ttID`=18",
];
// 211
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "('51', 'Chromium OS', 'Chromium OS')",
];
// 212
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmSSLPath` LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmSSLPath`='/opt/fog/snapins/ssl'",
];
// 213
$this->schema[] = [
    "DROP TABLE IF EXISTS `peer`",
    "DROP TABLE IF EXISTS `peer_torrent`",
    "DROP TABLE IF EXISTS `torrent`",
    "DELETE FROM `globalSettings` WHERE "
    . "`settingKey` IN ('FOG_TORRENT_INTERVAL',"
    . "'FOG_TORRENT_TIMEOUT','FOG_TORRENT_INTERVAL_MIN',"
    . "'FOG_TORRENT_PPR','FOG_TORRENTDIR')",
    "DELETE FROM `taskTypes` WHERE `ttID`=24",
];
// 214
$this->schema[] = [
    "ALTER TABLE `snapins` ADD `sShutdown` ENUM('0','1') NOT NULL DEFAULT '0'",
    "ALTER TABLE `hosts` ADD `hostEnforce` ENUM('0','1') NOT NULL DEFAULT '1'",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_ENFORCE_HOST_CHANGES','This setting only operates with "
    . "the new client. Default value is 1 which allows the new "
    . "client to enforce name changing on every cycle it checks "
    . "in, so any change on FOG will take place on the next cycle. "
    . "If unset (value 0) it will only perform hostname change "
    . "and/or AD Joining on host restart.',1,'Active Directory Defaults')",
];
// 215
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttKernelArgs`='mode=inventory deployed=1' "
    . "WHERE `ttID`=10",
];
// 216
$this->schema[] = [
    "ALTER TABLE `tasks` ADD COLUMN `taskWOL` ENUM('0','1') "
    . "NOT NULL AFTER `taskLastMemberID`",
];
// 217
$this->schema[] = [
    "ALTER TABLE `clientUpdates` CHANGE `cuType` `cuType` VARCHAR(30) NOT NULL",
];
// 218
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_AUTOUPDATE','This setting lets the admin choose "
    . "whether or not the clients on the hosts will be able to auto "
    . "update. Default is enabled.',1,'FOG Client')",
    "UPDATE `globalSettings` SET "
    . "`settingCategory`=REPLACE(`settingCategory`,'FOG Service','FOG Client') "
    . "WHERE `settingCategory` LIKE '%FOG Service%'",
    "UPDATE `globalSettings` SET "
    . "`settingCategory`=REPLACE(`settingCategory`,'FOG Linux Service',"
    . "'FOG Service') WHERE `settingCategory` LIKE '%FOG Linux Service%'",
    "UPDATE `globalSettings` SET "
    . "`settingKey`=REPLACE(`settingKey`,'FOG_SERVICE','FOG_CLIENT') "
    . "WHERE `settingKey` LIKE '%FOG_SERVICE%'",
];
// 219
$this->schema[] = [];
// 220
$this->schema[] = [
    "CREATE TABLE `groupMembers_new` ("
    . "`gmID` int(11) NOT NULL AUTO_INCREMENT,"
    . "`gmHostID` int(11) NOT NULL,"
    . "`gmGroupID` int(11) NOT NULL,"
    . "PRIMARY KEY(`gmID`),"
    . "UNIQUE KEY `gmHostID` (`gmHostID`,`gmGroupID`),"
    . "UNIQUE KEY `gmGroupID` (`gmHostID`,`gmGroupID`),"
    . "KEY `new_index` (`gmHostID`),"
    . "KEY `new_index1` (`gmGroupID`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `groupMembers_new` SELECT * FROM `groupMembers`",
    "DROP TABLE `groupMembers`",
    "RENAME TABLE `groupMembers_new` TO `groupMembers`",
];
// 221
$this->schema[] = $this->schema[count($this->schema ?: [])-1];
// 222
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostInit` LONGTEXT AFTER `hostDevice`",
];
// 223
$this->schema[] = [
    "CREATE TABLE `powerManagement` ("
    . "`pmID` INT NOT NULL AUTO_INCREMENT,"
    . "`pmHostID` INT NOT NULL,"
    . "`pmMin` VARCHAR(50) NOT NULL,"
    . "`pmHour` VARCHAR(50) NOT NULL,"
    . "`pmDom` VARCHAR(50) NOT NULL,"
    . "`pmMonth` VARCHAR(50) NOT NULL,"
    . "`pmDow` VARCHAR(50) NOT NULL,"
    . "`pmAction` ENUM('shutdown','reboot','wol') NOT NULL,"
    . "`pmOndemand` ENUM('0','1') NOT NULL,"
    . "PRIMARY KEY (`pmID`),"
    . "UNIQUE INDEX `cron` "
    . "(`pmHostID`,`pmMin`,`pmHour`,`pmDom`,"
    . "`pmMonth`,`pmDow`,`pmAction`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `modules` "
    . "(`id`, `name`, `short_name`, `description`) "
    . "VALUES "
    . "(13, 'Power Management', 'powermanagement', 'This setting will "
    . "enable or disable the power management service module on this "
    . "specific host. If the module is globally disabled, this "
    . "setting is ignored.')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_POWERMANAGEMENT_ENABLED', 'This setting defines if "
    . "the Windows Service module power management should be enabled "
    . "on client computers. This service allows an on demand "
    . "shutdown/reboot/wol of hosts. It also operates in a "
    . "cron style setup to allow many different schedules of "
    . "shutdowns, restarts, and/or wol. (Valid values: 0 or 1).',"
    . "'1','FOG Client - Power Management')",
];
// 224
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_IPXE_MAIN_COLOURS','This setting allows the admin to "
    . "define their own color (colour) elements for the iPXE "
    . "Boot Menu. Each element must have a new line as a "
    . "separator for multiple items.','colour --rgb 0x00567a 1 "
    . "||\ncolour --rgb 0x00567a 2 ||\ncolour --rgb 0x00567a 4 "
    . "||','FOG Boot Settings'),"
    . "('FOG_IPXE_MAIN_CPAIRS','This setting allows the admin "
    . "to define their own cpair elements for the iPXE Boot Menu. "
    . "Each element must have a new line as a separator for "
    . "multiple items. Fallback will use "
    . "FOG_IPXE_MAIN_FALLBACK_CPAIRS','cpair --foreground 7 "
    . "--background 2 2 ||','FOG Boot Settings'),"
    . "('FOG_IPXE_MAIN_FALLBACK_CPAIRS','This setting allows "
    . "the admin to define their own cpair elements for the "
    . "iPXE Boot Menu. Each element must have a new line as "
    . "a separator for multiple items. This is only called "
    . "in case of failure to load menu with picture.',"
    . "'cpair --background 0 1 ||\ncpair --background 1 2 ||',"
    . "'FOG Boot Settings'),"
    . "('FOG_IPXE_VALID_HOST_COLOURS','This setting allows the "
    . "admin to define their own color (colour) elements "
    . "for the iPXE Boot Menu on how the host text will "
    . "display if the host is registered. Each element "
    . "must have a new line as a separator for multiple "
    . "items.','colour --rgb 0x00567a 0 ||','FOG Boot Settings'),"
    . "('FOG_IPXE_INVALID_HOST_COLOURS','This setting allows the "
    . "admin to define their own color (colour) elements for "
    . "the iPXE Boot Menu on how the host text will display "
    . "if the host is not registered. Each element must have "
    . "a new line as a separator for multiple items.',"
    . "'colour --rgb 0xff0000 0 ||','FOG Boot Settings'),"
    . "('FOG_IPXE_HOST_CPAIRS','This setting allows the admin "
    . "to define their own cpair elements for the iPXE Boot "
    . "Menu of the host information. Each element must have "
    . "a new line as a separator for multiple items.',"
    . "'cpair --foreground 1 1 ||\ncpair --foreground 0 3 "
    . "||\ncpair --foreground 4 4 ||','FOG Boot Settings'),"
    . "('FOG_IPXE_BG_FILE','This setting allows the admin to "
    . "define their own background file. Files will need to "
    . "be in the fog web root under service/ipxe. Default "
    . "file is bg.png.','bg.png','FOG Boot Settings')",
];
// 225
$this->schema[] = [
    "CREATE TABLE `globalSettings_new` (
        `settingID` INT NOT NULL AUTO_INCREMENT,
        `settingKey` VARCHAR(255) NOT NULL,
        `settingDesc` LONGTEXT NOT NULL,
        `settingValue` LONGTEXT NOT NULL,
        `settingCategory` LONGTEXT NOT NULL,
        PRIMARY KEY(`settingID`),
UNIQUE INDEX `settingKey` (`settingKey`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `globalSettings_new` SELECT * FROM `globalSettings`",
    "DROP TABLE `globalSettings`",
    "RENAME TABLE `globalSettings_new` TO `globalSettings`",
];
// 226
$this->schema[] = [
    "ALTER TABLE `snapins` ADD `sHideLog` ENUM('0','1') NOT NULL DEFAULT '0'",
    "ALTER TABLE `snapins` ADD `sTimeout` INTEGER NOT NULL DEFAULT 0",
];
// 227
$this->schema[] = [
    "ALTER TABLE `hosts` CHANGE `hostPending` `hostPending` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmPrimary` `hmPrimary` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmPending` `hmPending` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmIgnoreClient` "
    . "`hmIgnoreClient` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmIgnoreImaging` "
    . "`hmIgnoreImaging` ENUM('0','1') NOT NULL",
];
// 228
$this->schema[] = [
    "TRUNCATE TABLE `history`",
    "ALTER TABLE `history` CHANGE `hText` `hText` VARCHAR(255) NOT NULL",
    "ALTER TABLE `history` ADD UNIQUE INDEX `updateTime` (`hText`,`hTime`)",
];
// 229
$this->schema[] = [
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` VARCHAR(255) NOT NULL",
];
// 230
$this->schema[] = [
    "UPDATE `taskTypes` SET "
    . "`ttDescription`='Deploy action will send an image "
    . "saved on the FOG server to the client computer with "
    . "all included snapins.' WHERE `ttID`=1",
    "UPDATE `taskTypes` SET "
    . "`ttDescription`='Capture will pull an image from a "
    . "client computer that will be saved on the server.' WHERE `ttID`=2",
    "UPDATE `taskTypes` SET "
    . "`ttDescription`='Deploy - Debug mode allows FOG to "
    . "setup the environment to allow you send a specific "
    . "image to a computer, but instead of sending the "
    . "image, FOG will leave you at a prompt right before "
    . "sending. If you actually wish to send the image all "
    . "you need to do is type \"fog\" and hit enter.' WHERE `ttID`=15",
    "UPDATE `taskTypes` SET "
    . "`ttDescription`='Capture - Debug mode allows FOG to "
    . "setup the environment to allow you capture a specific "
    . "image from a computer, but instead of capturing the image, "
    . "FOG will leave you at a prompt right before restoring. "
    . "If you actually wish to capture the image all you need "
    . "to do is type \"fog\" and hit enter.' WHERE `ttID`=16",
    "UPDATE `taskTypes` SET `ttDescription`='Deploy without "
    . "snapins allows FOG to image the workstation, but after "
    . "the task is complete any snapins linked to the host or "
    . "group will NOT be sent.' WHERE `ttID`=17",
    "UPDATE `pxeMenu` SET `pxeName`='fog.deployimage',"
    . "`pxeDesc`='Deploy Image' WHERE `pxeName`='fog.quickimage'"
];
// 231
$this->schema[] = [
    "DELETE FROM `moduleStatusByHost` WHERE `msState`='0'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='1' WHERE `msModuleID`='dircleanup'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='2' WHERE `msModuleID`='usercleanup'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='3' WHERE `msModuleID`='displaymanager'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='4' WHERE `msModuleID`='autologout'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='5' WHERE `msModuleID`='greenfog'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='6' WHERE `msModuleID`='snapin'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='7' WHERE `msModuleID`='clientupdater'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='8' WHERE `msModuleID`='hostregister'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='9' WHERE `msModuleID`='hostnamechanger'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='10' WHERE `msModuleID`='printermanager'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='11' WHERE `msModuleID`='taskreboot'",
    "UPDATE `moduleStatusByHost` SET "
    . "`msModuleID`='12' WHERE `msModuleID`='usertracker'",
    "ALTER TABLE `moduleStatusByHost` CHANGE "
    . "`msModuleID` `msModuleID` INT NOT NULL",
];
// 232
$this->schema[] = [
    "ALTER TABLE `snapins` ADD `sPackType` ENUM('0','1') NOT NULL DEFAULT '0'",
];
// 233
$this->schema[] = [
    "UPDATE `globalSettings` SET "
    . "`settingKey`='FOG_CAPTUREIGNOREPAGEHIBER' "
    . "WHERE `settingKey`='FOG_UPLOADIGNOREPAGEHIBER'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_CAPTURERESIZEPCT' "
    . "WHERE `settingKey`='FOG_UPLOADRESIZEPCT'",
];
// 234
$this->schema[] = [
    "ALTER TABLE `snapins` ADD `sHash` VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE `snapins` ADD `sSize` BIGINT NOT NULL DEFAULT 0",
];
// 235
$this->schema[] = [
    "CREATE TABLE `users_new` ("
    . "`uId` INT NOT NULL AUTO_INCREMENT,"
    . "`uName` VARCHAR(40) NOT NULL,"
    . "`uPass` LONGTEXT NOT NULL,"
    . "`uCreateDate` DATETIME NOT NULL,"
    . "`uCreateBy` VARCHAR(40) NOT NULL,"
    . "`uType` INT NOT NULL,"
    . "PRIMARY KEY(`uId`),"
    . "UNIQUE INDEX `name` (`uName`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `users_new` SELECT * FROM `users`",
    "DROP TABLE `users`",
    "RENAME TABLE `users_new` TO `users`",
];
// 236
$this->schema[] = [
    DatabaseManager::getColumns('multicastSessions', 'msAnon1') > 0 ?
    'ALTER TABLE `multicastSessions`'
    . 'CHANGE `msAnon1` `msIsDD` INTEGER NOT NULL' :
    '',
    "ALTER TABLE `imageGroupAssoc` CHANGE `igaPrimary` `igaPrimary` "
    . "ENUM('0','1') NOT NULL",
    "ALTER TABLE `snapinGroupAssoc` CHANGE `sgaPrimary` `sgaPrimary` "
    . "ENUM('0','1') NOT NULL"
];
// 237
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_URL_AVAILABLE_TIMEOUT', 'This setting defines the available timeout in "
    . "thousandths of a second. (Default is 2000 milliseconds)',"
    . "'2000','General Settings'),"
    . "('FOG_URL_BASE_CONNECT_TIMEOUT', 'This setting defines the available timeout "
    . "to connect to a server to perform real actions.  This is set in seconds. "
    . "(Default is 15 seconds)','15','General Settings'),"
    . "('FOG_URL_BASE_TIMEOUT', 'This setting defines the total timeout to perform "
    . "url based actions, such as download, getting data, etc... This is set in "
    . "seconds. (Default is 86400 seconds -- 1 day)','86400','General Settings')",
];
// 238
$this->schema[] = [
    Schema::dropTable('aloLog')
];
// 239
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('SNAPINHASHLOGFILENAME','Filename to store the snapin hash log "
    . "file to (Default fogsnapinhash.log)','fogsnapinhash.log',"
    . "'FOG Linux Service Logs'),"
    . "('SNAPINHASHSLEEPTIME','The amount of time between snapin "
    . "hash service runs. Value is in seconds. (Default 1800)',"
    . "'1800','FOG Linux Service Sleep Times'),"
    . "('SNAPINHASHDEVICEOUTPUT','The tty to output to for snapin "
    . "replicator. (Default /dev/tty5)','/dev/tty6',"
    . "'FOG Linux Service TTY Output')",
    "UPDATE `globalSettings` SET `settingCategory`="
    . "'FOG Linux Service Logs' WHERE `settingCategory`="
    . "'FOG Service Logs'",
    "UPDATE `globalSettings` SET `settingCategory`="
    . "'FOG Linux Service Sleep Times' WHERE `settingCategory`="
    . "'FOG Service Sleep Times'",
    "UPDATE `globalSettings` SET `settingCategory`="
    . "'FOG Linux Service TTY Output' WHERE `settingCategory`="
    . "'FOG Service TTY Output'"
];
// 240
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_BANNER_IMAGE', 'This setting defines an image for"
    . " the banner on the fog client.','','Rebranding'),"
    . "('FOG_CLIENT_BANNER_SHA', 'This setting stores the sha value of"
    . " the banner to be applied.','','Rebranding'),"
    . "('FOG_COMPANY_NAME', 'This setting defines the name you"
    . " would like presented on the client.','','Rebranding'),"
    . "('FOG_COMPANY_COLOR', 'This setting is the hex color code"
    . " you want progress bar colors to display as.','','Rebranding')"
];
// 241
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_COMPANY_TOS','This allows setting the company terms of service.',"
    . "'', 'Rebranding'),"
    . "('FOG_COMPANY_SUBNAME','This allows setting the company sub unit.',"
    . "'', 'Rebranding')",
    "UPDATE `globalSettings` SET `settingCategory`='Rebranding' WHERE "
    . "`settingKey` IN ('FOG_CLIENT_BANNER_IMAGE','FOG_CLIENT_BANNER_SHA',"
    . "'FOG_COMPANY_NAME','FOG_COMPANY_COLOR')"
];
// 242
$this->schema[] = [
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_NAME' WHERE "
    . "`settingKey`='FOG_COMPANY_NAME'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_SUBNAME',"
    . "`settingDesc`='This allows setting the sub unit, and is only used "
    . " on the Equipment loan report for tracking.' WHERE "
    . "`settingKey`='FOG_COMPANY_SUBNAME'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_COLOR' WHERE "
    . "`settingKey`='FOG_COMPANY_COLOR'",
    "UPDATE `globalSettings` SET `settingDesc`='This setting defines an image "
    . "for the banner on the fog client. The width must be 650 pixels, and "
    . "the height must be 120 pixels.' WHERE `settingKey`='FOG_CLIENT_BANNER_IMAGE'"
];
// 243
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_BANNER_IMAGE', 'This setting defines an image for"
    . " the banner on the fog client.','','Rebranding'),"
    . "('FOG_CLIENT_BANNER_SHA', 'This setting stores the sha value of"
    . " the banner to be applied.','','Rebranding'),"
    . "('FOG_COMPANY_NAME', 'This setting defines the name you"
    . " would like presented on the client.','','Rebranding'),"
    . "('FOG_COMPANY_COLOR', 'This setting is the hex color code"
    . " you want progress bar colors to display as.','','Rebranding')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_COMPANY_TOS','This allows setting the company terms of service.',"
    . "'', 'Rebranding'),"
    . "('FOG_COMPANY_SUBNAME','This allows setting the company sub unit.',"
    . "'', 'Rebranding')",
    "UPDATE `globalSettings` SET `settingCategory`='Rebranding' WHERE "
    . "`settingKey` IN ('FOG_CLIENT_BANNER_IMAGE','FOG_CLIENT_BANNER_SHA',"
    . "'FOG_COMPANY_NAME','FOG_COMPANY_COLOR')",
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_NAME' WHERE "
    . "`settingKey`='FOG_COMPANY_NAME'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_SUBNAME',"
    . "`settingDesc`='This allows setting the sub unit, and is only used "
    . " on the Equipment loan report for tracking.' WHERE "
    . "`settingKey`='FOG_COMPANY_SUBNAME'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_COMPANY_COLOR' WHERE "
    . "`settingKey`='FOG_COMPANY_COLOR'",
    "UPDATE `globalSettings` SET `settingDesc`='This setting defines an image "
    . "for the banner on the fog client. The width must be 650 pixels, and "
    . "the height must be 120 pixels.' WHERE `settingKey`='FOG_CLIENT_BANNER_IMAGE'"
];
// 244
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    [
        'globalSettings',
        [
            'settingKey'
        ]
    ],
    true
);
// 245
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_LOGIN_INFO_DISPLAY', 'This setting defines if the login page"
    . " should or should not display fog version information. (Default is "
    . "on)','1','General Settings')"
];
// 246
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    [
        'hostMAC',
        ['hmMAC']
    ]
);
// 247
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('IMAGEREPLICATORGLOBALENABLED','This setting defines if replication "
    . "of images should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('SNAPINREPLICATORGLOBALENABLED','This setting defines if replication "
    . "of snapins should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('SNAPINHASHGLOBALENABLED','This setting defines if hashing "
    . "of snapins should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('PINGHOSTGLOBALENABLED','This setting defines if ping hosts "
    . "should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('SCHEDULERGLOBALENABLED','This setting defines if scheduler "
    . "service should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('MULTICASTGLOBALENABLED','This setting defines if multicast "
    . "service should occur (Default is enabled)',"
    . "'1','FOG Linux Service Enabled')"
];
// 248
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MULTICAST_RENDEZVOUS', 'This setting defines a rendez-vous"
    . " for multicast tasks. (Default is empty)','','Multicast Settings')"
];
// 249
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_IMG_WHEN_REG','Image upon completion"
    . " of registration. Values are 0 or 1, default is 1."
    . " This will only image clients if the image value is"
    . " defined as well.','0', 'FOG Quick Registration')"
];
// 250
$this->schema[] = [
    "ALTER TABLE `images` ADD `imageServerSize` BIGINT UNSIGNED NOT NULL DEFAULT 0",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('IMAGESIZEGLOBALENABLED','This setting defines if image size should be "
    . "enabled or not. (Default is enabled)',"
    . "'1', 'FOG Linux Service Enabled'),"
    . "('IMAGESIZESLEEPTIME','The amount of time between image "
    . "size service runs. Value is in seconds. (Default 3600)',"
    . "'3600','FOG Linux Service Sleep Times'),"
    . "('IMAGESIZELOGFILENAME','Filename to store the image size log "
    . "file to (Default fogimagesize.log)','fogimagesize.log',"
    . "'FOG Linux Service Logs'),"
    . "('IMAGESIZEDEVICEOUTPUT','The tty to output to for image "
    . "size service. (Default /dev/tty3)','/dev/tty3',"
    . "'FOG Linux Service TTY Output')"
];
// 251
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    [
        'globalSettings',
        ['settingKey']
    ]
);
// 252
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT',"
    . "'Compression Format Setting (Default to Partclone Gzip)',"
    . "'0','General Settings'),"
    . "('FOG_TASKING_ADV_SHUTDOWN_ENABLED',"
    . "'Tasking shutdown element checked (Default is off)',"
    . "'0','General Settings'),"
    . "('FOG_TASKING_ADV_WOL_ENABLED',"
    . "'Tasking wake on lan element checked (Default is on)',"
    . "'1','General Settings'),"
    . "('FOG_TASKING_ADV_DEBUG_ENABLED',"
    . "'Tasking debug element checked (Default is off)',"
    . "'0','General Settings')"
];
// 253
$this->schema[] = [
    "ALTER TABLE `users` ADD `uDisplay` VARCHAR(255) "
    . "NOT NULL AFTER `uType`"
];
// 254
$this->schema[] = [
    "CREATE TABLE `hookEvents` ("
    . "`heID` INT NOT NULL AUTO_INCREMENT,"
    . "`heName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`heID`),"
    . "UNIQUE INDEX `name` (`heName`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE `notifyEvents` ("
    . "`neID` INT NOT NULL AUTO_INCREMENT,"
    . "`neName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`neID`),"
    . "UNIQUE INDEX `name` (`neName`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 255
$this->schema[] = [
    "ALTER TABLE `pxeMenu` ADD `pxeHotKeyEnable` ENUM('0','1') NOT NULL",
    "ALTER TABLE `pxeMenu` ADD `pxeKeySequence` VARCHAR(255) NOT NULL"
];
// 256
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_API_ENABLED',"
    . "'Enables API Access (Defaults to On)',"
    . "'1','API System'),"
    . "('FOG_API_TOKEN',"
    . "'The API Token to use (Randomly generated at install)',"
    . "'"
    . self::createSecToken()
    . "','API System')"
];
// 257
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_IMAGE_LIST_MENU',"
    . "'Enables Image list on boot menu deploy image (Defaults to on)',"
    . "'1','FOG Boot Settings')"
];
// 258
$this->schema[] = [
    "DELETE FROM `taskTypes` WHERE `ttID` IN (23, 24)",
    "ALTER TABLE `taskTypes` auto_increment=1",
    "ALTER TABLE `globalSettings` auto_increment=1"
];
// 259
$this->schema[] = [
    "ALTER TABLE `users` ADD `uAllowAPI` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `users` ADD `uAPIToken` VARCHAR(255) NOT NULL"
];
// 260
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_REAUTH_ON_DELETE',"
    . "'If deleteing an item, require authentication or not. (Defaults to on)',"
    . "'1','General Settings'),"
    . "('FOG_REAUTH_ON_EXPORT',"
    . "'If exporting, require authentication or not. (Defaults to on)',"
    . "'1','General Settings')"
];
// 261
$this->schema[] = [
    "ALTER TABLE `inventory` ADD `iSystemUUID` VARCHAR(255) NOT NULL"
];
// 262
$this->schema[] = [
    "ALTER TABLE `taskTypes` ADD `ttInitrd` LONGTEXT NOT NULL"
];
// 263
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_PROD_KEY_BIOS','Try pulling systems SLIC product key."
    . " Values are 0 or 1, default is 0.'"
    . " ,'0', 'FOG Quick Registration')"
];
// Here begins Divergence of DB so calls here are being replicated later
// this is to ensure these changes make it to the end user.
// They're checking if those fields already exist to ensure we don't cause
// any errors.
// 264
// This is being replicated and checked in 276
$this->schema[] = [
    "ALTER TABLE `groups` ADD COLUMN `groupInit` LONGTEXT NOT NULL AFTER `groupPrimaryDisk`"
];
// 265
// This is being replicated and checked in 276
$this->schema[] = [
    "ALTER TABLE `plugins` CHANGE `pAnon1` `pIcon` LONGTEXT NOT NULL",
    "ALTER TABLE `plugins` CHANGE `pAnon2` `pRunfile` LONGTEXT NOT NULL",
    "ALTER TABLE `plugins` CHANGE `pAnon3` `pLocation` LONGTEXT NOT NULL"
];
// 266
// This is being replicated and checked in 276
$this->schema[] = [
    "ALTER TABLE `plugins` CHANGE `pAnon4` `pDescription` LONGTEXT NOT NULL"
];
// 267
// This is being replicated in 273
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_USER_VALIDPASSHELPMSG','This is just a simple text "
    . "describing the user password requirements. Default: Must be at "
    . "least 4 characters.','Must be at least 4 characters.','User Management')",
    "UPDATE `globalSettings` SET `settingValue` = '4' WHERE "
    . "`settingKey` = 'FOG_USER_MINPASSLENGTH'",
    "UPDATE `globalSettings` SET `settingValue` = '275000' WHERE "
    . "`settingKey` = 'FOG_KERNEL_RAMDISK_SIZE'"
];
// This is where the divergence ends though one change may still be making it.
// For safety pushing into 276
// 268
$this->schema[] = [
    "ALTER TABLE `multicastSessions` CHANGE `msAnon3` `msShutdown` "
    . "ENUM('0','1') NOT NULL DEFAULT '0'",
    "ALTER TABLE `multicastSessions` CHANGE `msAnon4` `msMaxwait` INTEGER NOT NULL"
];
// Please no more diverging.
// 269
// DMI Keys Valid Strings:
$dmiStrings = [
    'bios-vendor',
    'bios-version',
    'bios-release-date',
    'system-manufacturer',
    'system-product-name',
    'system-version',
    'system-serial-number',
    'system-uuid',
    'baseboard-manufacturer',
    'baseboard-product-name',
    'baseboard-version',
    'baseboard-serial-number',
    'baseboard-asset-tag',
    'chassis-manufacturer',
    'chassis-type',
    'chassis-version',
    'chassis-serial-number',
    'chassis-asset-tag',
    'processor-family',
    'processor-manufacturer',
    'processor-version',
    'processor-frequency'
];
$dmiStrings = implode(
    "'),('",
    $dmiStrings
);
$this->schema[] = [
    "CREATE TABLE `dmidecodeKeys` ("
    . "`dkID` INT NOT NULL AUTO_INCREMENT,"
    . "`dkName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`dkID`),"
    . "UNIQUE INDEX `name` (`dkName`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `dmidecodeKeys` (`dkName`) VALUES ('$dmiStrings')"
];
// 270
$this->schema[] = [
    "UPDATE `globalSettings`"
    . " SET `settingDesc` = 'Enables API Access (Defaults to On)'"
    . " WHERE `settingKey` = 'FOG_API_ENABLED'"
];
// 271
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_ENABLE_SHOW_PASSWORDS','Allow Admins the possibility of allowing "
    . "the password fields to be displayed in plain text. Values are 0 or 1, "
    . "Default is 1.','1','General Settings')"
];
// 272
$column = array_filter(
    (array)DatabaseManager::getColumns(
        'nfsGroupMembers',
        'ngmHelloInterval'
    )
);
$this->schema[] = count($column ?: []) ? [] : [
    "ALTER TABLE `nfsGroupMembers` ADD `ngmHelloInterval` VARCHAR(8) AFTER `ngmMaxBitrate`"
];
// 273
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_USER_VALIDPASSCHARS','This is the regex pattern to match for "
    . "valid passwords. Default: (?=.*){4,}','(?=.*){4,}','User Management')",
    "UPDATE `globalSettings` SET `settingValue` = '(?=.*){4,}', `settingDesc` = "
    . "'This is the regex pattern to match for valid passwords. Default: (?=.*){4,}' "
    . "WHERE `settingKey` = 'FOG_USER_VALIDPASSCHARS'"
];
// 274
$this->schema[] = [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmHelloInterval` "
    . "VARCHAR(8) AFTER `ngmMaxBitrate`",
    "UPDATE `nfsGroupMembers` SET `ngmUser` ='"
    . STORAGE_FTP_USERNAME
    . "' WHERE `ngmHostname` = '"
    . STORAGE_HOST
    . "'",
    "UPDATE `globalSettings` SET `settingValue` = '"
    . STORAGE_FTP_USERNAME
    . "' WHERE `settingKey` = 'FOG_TFTP_FTP_USERNAME'",
    "UPDATE `globalSettings` SET `settingValue` = '275000' WHERE "
    . "`settingKey` = 'FOG_KERNEL_RAMDISK_SIZE'"
];
// 275
// Divergence for 268 schema may begin here so should check just
// to be safe.
$columnGraphColor = array_filter(
    (array)DatabaseManager::getColumns(
        'nfsGroupMembers',
        'ngmGraphColor'
    )
);
$this->schema[] = count($columnGraphColor ?: []) ? [] : [
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmGraphColor` "
    . "VARCHAR(6) AFTER `ngmHelloInterval`"
];
// 276
$columngInit = array_filter(
    (array)DatabaseManager::getColumns(
        'groups',
        'groupInit'
    )
);
$columnpAnon1 = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pAnon1'
    )
);
$columnpIcon = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pIcon'
    )
);
$columnpAnon2 = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pAnon2'
    )
);
$columnpRunfile = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pRunfile'
    )
);
$columnpAnon3 = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pAnon3'
    )
);
$columnpLocation = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pLocation'
    )
);
$columnpAnon4 = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pAnon4'
    )
);
$columnpDescription = array_filter(
    (array)DatabaseManager::getColumns(
        'plugins',
        'pDescription'
    )
);
// The following is the secondary checking for 268
$columnmsAnon3 = array_filter(
    (array)DatabaseManager::getColumns(
        'multicastSessions',
        'msAnon3'
    )
);
$columnmsShutdown = array_filter(
    (array)DatabaseManager::getColumns(
        'multicastSessions',
        'msShutdown'
    )
);
$columnmsAnon4 = array_filter(
    (array)DatabaseManager::getColumns(
        'multicastSessions',
        'msAnon4'
    )
);
$columnmsMaxwait = array_filter(
    (array)DatabaseManager::getColumns(
        'multicastSessions',
        'msMaxwait'
    )
);
$picon = (
    count($columnpAnon1 ?: []) ?
    (
        count($columnpIcon ?: []) ?
        '' :
        "ALTER TABLE `plugins` CHANGE `pAnon1` `pIcon` LONGTEXT NOT NULL"
    ) :
    ''
);
$prunfile = (
    count($columnpAnon2 ?: []) ?
    (
        count($columnpRunfile ?: []) ?
        '' :
        "ALTER TABLE `plugins` CHANGE `pAnon2` `pRunfile` LONGTEXT NOT NULL"
    ) :
    ''
);
$plocation = (
    count($columnpAnon3 ?: []) ?
    (
        count($columnpLocation ?: []) ?
        '' :
        "ALTER TABLE `plugins` CHANGE `pAnon3` `pLocation` LONGTEXT NOT NULL"
    ) :
    ''
);
$pdescription = (
    count($columnpAnon4 ?: []) ?
    (
        count($columnpDescription ?: []) ?
        '' :
        "ALTER TABLE `plugins` CHANGE `pAnon4` `pDescription` LONGTEXT NOT NULL"
    ) :
    ''
);
$mshutdown = (
    count($columnmsAnon3 ?: []) ?
    (
        count($columnmsShutdown ?: []) ?
        '' :
        "ALTER TABLE `multicastSessions` CHANGE `msAnon3` `msShutdown` "
        . "ENUM('0','1') NOT NULL DEFAULT '0'"
    ) :
    ''
);
$mmaxwait = (
    count($columnmsAnon4 ?: []) ?
    (
        count($columnmsMaxwait ?: []) ?
        '' :
        "ALTER TABLE `multicastSessions` CHANGE `msAnon4` "
        . "`msMaxwait` INTEGER NOT NULL"
    ) :
    ''
);
$ginit = (
    count($columngInit ?: []) ?
    '' :
    "ALTER TABLE `groups` ADD COLUMN `groupInit` "
    . "LONGTEXT NOT NULL AFTER `groupPrimaryDisk`"
);
$this->schema[] = array_filter([
    $ginit,
    $picon,
    $prunfile,
    $plocation,
    $pdescription,
    $mshutdown,
    $mmaxwait
]);
// 277
$this->schema[] = [
    "CREATE TABLE `userAuths` ("
    . "`uaID` INT NOT NULL AUTO_INCREMENT,"
    . "`uaUserID` INT NOT NULL,"
    . "`uaExpireDate` TIMESTAMP NOT NULL "
    . "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
    . "`uaIsExpired` INT NOT NULL DEFAULT '0',"
    . "`uaSelectorHash` VARCHAR(255) NOT NULL,"
    . "`uaPasswordHash` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`uaID`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 278 is #268 in 1.5.8
$this->schema[] = [
    "UPDATE `globalSettings` SET "
    . "`settingDesc`='Email address(es) to send the reports to. Separate "
    . "multiple emails by comma (e.g. user_a@domain.com, user_b@domain2.com). "
    . "Token \$\{user-name\} is replaced by the task creators username.'"
    . "WHERE `settingKey`='FOG_EMAIL_ADDRESS'"
];
// 279
$this->schema[] = [
    "ALTER TABLE `users` "
    . "MODIFY `uName` VARCHAR(255),"
    . "MODIFY `uCreateBy` VARCHAR(255)"
];
// 280
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_LOG_INFO',"
    . "'Turn logging on for Informational messages. (Defaults to off)',"
    . "'0','Logging Settings'),"
    . "('FOG_LOG_DEBUG',"
    . "'Turn logging on for Debug messages. (Defaults to off)',"
    . "'0','Logging Settings'),"
    . "('FOG_LOG_ERROR',"
    . "'Turn logging on for Errors messages. (Defaults to off)',"
    . "'0','Logging Settings')"
];
// 281
$this->schema[] = [
    "ALTER TABLE `tasks` ADD `taskBypassBitlocker` "
    . "ENUM('0','1') NOT NULL DEFAULT '0'",
];
// 282 is #269 and #270 in 1.5.9
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttDescription`='Normal wipe will boot "
    . "the client computer and perform a full disk wipe. This method "
    . "writes ONE pass of random data to the hard disk.' "
    . "WHERE `ttID`=19",
    "UPDATE `globalSettings` SET "
    . "`settingDesc`='Compression Format Setting (Default to Partclone Zstd)', `settingValue`=5 "
    . "WHERE `settingKey`='FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT' AND `settingValue`=0"
];
// 283
// Only install the table if it doesn't exist already.
// DMI Keys Valid Strings:
$dmiStrings = [
    'bios-vendor',
    'bios-version',
    'bios-release-date',
    'system-manufacturer',
    'system-product-name',
    'system-version',
    'system-serial-number',
    'system-uuid',
    'baseboard-manufacturer',
    'baseboard-product-name',
    'baseboard-version',
    'baseboard-serial-number',
    'baseboard-asset-tag',
    'chassis-manufacturer',
    'chassis-type',
    'chassis-version',
    'chassis-serial-number',
    'chassis-asset-tag',
    'processor-family',
    'processor-manufacturer',
    'processor-version',
    'processor-frequency'
];
$dmiStrings = implode(
    "'),('",
    $dmiStrings
);
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `dmidecodeKeys` ("
    . "`dkID` INT NOT NULL AUTO_INCREMENT,"
    . "`dkName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`dkID`),"
    . "UNIQUE INDEX `name` (`dkName`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `dmidecodeKeys` (`dkName`) VALUES ('$dmiStrings')"
];
// 284
$this->schema[] = [
    "DELETE FROM `pxeMenu` WHERE `pxeName`='fog.approvehost'",
    "DELETE FROM `pxeMenu` WHERE `pxeName`='fog.quickdel'",
];
// 285
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_SSH_PORT',"
    . "'What port for SSH into the FOG Server. (Defaults to 22)',"
    . "'22','General Settings')"
];
// 286
$this->schema[] = [
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "('10', 'Windows 11', ''),"
    . "('11', 'Windows Server', '')"
];
// 287
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `fileDeleteQueue` ("
    . "`fdqID` INT NOT NULL AUTO_INCREMENT,"
    . "`fdqPathName` VARCHAR(255) NOT NULL,"
    . "`fdqStorageGroupID` INT NOT NULL,"
    . '`fqdCreateDate` DATETIME NOT NULL,'
    . '`fqdCompletedDate` DATETIME NOT NULL,'
    . '`fqdCreateBy` VARCHAR(40) NOT NULL,'
    . "PRIMARY KEY(`fdqID`)"
    . ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC"
];
// 288
$this->schema[] = [
    "ALTER TABLE `fileDeleteQueue` ADD `fdqState` "
    . 'INT(11) NOT NULL',
    'ALTER TABLE `fileDeleteQueue` CHANGE COLUMN `fqdCreateDate` `fdqCreateDate` DATETIME',
    'ALTER TABLE `fileDeleteQueue` CHANGE COLUMN `fqdCompletedDate` `fdqCompletedDate` DATETIME',
    'ALTER TABLE `fileDeleteQueue` CHANGE COLUMN `fqdCreateBy` `fdqCreateBy` VARCHAR(40)',
    // GH-1243: NULL, not a zero date. '0000-00-00 00:00:00' is not a legal
    // DATETIME default under MySQL 8.0's stock sql_mode (NO_ZERO_DATE,
    // STRICT_TRANS_TABLES) -- it is error 1067, which is on neither
    // tolerance list, so the whole schema update threw and FOG could not be
    // installed on MySQL at all. NULL says the same thing ("not completed
    // yet") and every supported server accepts it. Editing this historical
    // step is safe: it has already run everywhere it was going to, and step
    // 343 is what repairs those installs.
    "ALTER TABLE `fileDeleteQueue` MODIFY COLUMN `fdqCompletedDate` DATETIME NULL DEFAULT NULL",
    "ALTER TABLE `fileDeleteQueue` MODIFY COLUMN `fdqCreateDate` DATETIME DEFAULT CURRENT_TIMESTAMP",
];
// 289
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FILEDELETEQUEUEGLOBALENABLED','This setting defines if file delete queue should be "
    . "enabled or not. (Default is enabled)',"
    . "'1', 'FOG Linux Service Enabled'),"
    . "('FILEDELETEQUEUESLEEPTIME','The amount of time between file "
    . "delete queue service runs. Value is in seconds. (Default 14400)',"
    . "'14400','FOG Linux Service Sleep Times'),"
    . "('FILEDELETEQUEUELOGFILENAME','Filename to store the file delete queue log "
    . "file to (Default fogfiledeletequeue.log)','fogfiledeletequeue.log',"
    . "'FOG Linux Service Logs'),"
    . "('FILEDELETEQUEUEDEVICEOUTPUT','The tty to output to for image "
    . "size service. (Default /dev/tty3)','/dev/tty3',"
    . "'FOG Linux Service TTY Output')"
];
// 290
$this->schema[] = [
    "ALTER TABLE `fileDeleteQueue` ADD `fdqPathType` "
    . "VARCHAR(255) NOT NULL"
];
// 291
$this->schema[] = [
    "ALTER TABLE `hosts` ADD COLUMN `hostInfoKey` VARCHAR(255)",
    "ALTER TABLE `hosts` ADD COLUMN `hostInfoLock` BOOLEAN DEFAULT 0"
];
// 292
$this->schema[] = [
    "ALTER TABLE `inventory` ADD COLUMN `iGpuvendors` VARCHAR(255) NOT NULL",
    "ALTER TABLE `inventory` ADD COLUMN `iGpuproducts` VARCHAR(255) NOT NULL"
];
// 293
$this->schema[] = [
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_TFTP_PXE_KERNEL_ARM','Location of the ARM kernel file on "
    . "the PXE server, this should point to the kernel itself.',"
    . "'arm_Image','TFTP Server'),"
    . "('FOG_PXE_BOOT_IMAGE_ARM','The settings defines where the ARM "
    . "fog boot file system image is located.','arm_init.cpio.gz','TFTP Server')",
];
// 294
$this->schema[] = [
    "ALTER TABLE `snapinJobs` "
    . "ADD COLUMN `sjAbortOnFail` ENUM('0','1') NOT NULL DEFAULT '0' "
    . "AFTER `sjStateID`",
    "ALTER TABLE `snapinTasks` "
    . "ADD COLUMN `stSequence` INT(11) NOT NULL DEFAULT 0 "
    . "AFTER `stSnapinID`",
    // Historical ordering fallback: older rows are approximated by task ID order.
    "UPDATE `snapinTasks` SET `stSequence`=`stID` WHERE `stSequence`=0",
    "ALTER TABLE `snapinAssoc` "
    . "ADD COLUMN `saSequence` INT(11) NOT NULL DEFAULT 0 "
    . "AFTER `saSnapinID`",
    // Seed the run order from association id so existing hosts keep their
    // current (implicit) order until an admin reorders them.
    "UPDATE `snapinAssoc` SET `saSequence`=`saID` WHERE `saSequence`=0",
];
// 295
$this->schema[] = [
    // Per-plugin applied schema-migration counter. Lets installed plugins
    // receive additive (non-destructive) schema changes on upgrade, the same
    // way core tables do via this list. Defaults to 0 (no steps applied).
    "ALTER TABLE `plugins` "
    . "ADD COLUMN `pSchema` INTEGER NOT NULL DEFAULT 0",
];
// 296
$this->schema[] = [
    // Default rendering mode for the management list/export tables. 'infinite'
    // uses virtual-scroll (Scroller) so all matching rows load in chunks as
    // you scroll; 'paged' keeps the classic page-number pager. Read into the
    // hidden #scrollMode input and consumed by registerTable() in fog.common.js.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_TABLE_SCROLL_MODE','This setting defines how the management tables "
    . "page through records. <b>infinite</b> loads rows as you scroll; "
    . "<b>paged</b> uses the classic page-number bar.','infinite',"
    . "'FOG View Settings')",
];
// 297
$this->schema[] = [
    // Per-storage-group list of trusted IPs/CIDR ranges allowed to make
    // node-to-node status calls (freespace.php, hw.php). A node serving such
    // a request consults the trusted ranges of its own group(s), in addition
    // to the always-trusted exact storage node IPs + loopback. Empty by
    // default (no extra ranges trusted). Newline/comma/space separated.
    "ALTER TABLE `nfsGroups` "
    . "ADD COLUMN `ngTrustedCIDRs` VARCHAR(2048) NOT NULL DEFAULT ''",
];
// 298
$this->schema[] = [
    // Seed the default storage group's trusted CIDR from the master's own
    // network, computed by the installer and exposed as STORAGE_DEFAULT_CIDR
    // in config.class.php. Only fills it while still empty, so any
    // admin-configured ranges are preserved on upgrade. If the constant is
    // absent (e.g. schema run without re-running the installer) this is a
    // no-op and the group is left untouched.
    function () {
        if (!defined('STORAGE_DEFAULT_CIDR')
            || trim((string)STORAGE_DEFAULT_CIDR) === ''
        ) {
            return true;
        }
        // sanitize() returns a fully quoted literal (PDO::quote), so it is
        // used directly without adding our own surrounding quotes.
        $cidr = self::$DB->sanitize(trim((string)STORAGE_DEFAULT_CIDR));
        self::$DB->query(
            "UPDATE `nfsGroups` "
            . "SET `ngTrustedCIDRs` = $cidr "
            . "WHERE `ngName` = 'default' "
            . "AND `ngTrustedCIDRs` = ''"
        );
        return true;
    },
];
// 299
$this->schema[] = [
    // Printer IP field originally VARCHAR(20) — too small for FQDNs and
    // host:port targets (e.g. CUPS/IPP queues). Widen to VARCHAR(255) to
    // match the FQDN max length (253) and the pConfigFile convention.
    "ALTER TABLE `printers` "
    . "MODIFY COLUMN `pIP` VARCHAR(255) NOT NULL",
];
// 300
$this->schema[] = [
    // Tasks never recorded when they reached their current state, so the
    // task list's Recent view had nothing to sort completed/canceled work
    // by. Stamped on every state transition (Task::set + the mass-update
    // cancel/complete paths). NULL for rows that last changed state before
    // this upgrade; readers fall back to
    // GREATEST(taskCheckIn, taskCreateTime) for those.
    "ALTER TABLE `tasks` "
    . "ADD COLUMN `taskStateChangedTime` DATETIME NULL DEFAULT NULL",
];
// 301
$this->schema[] = [
    // Backfill taskStateChangedTime for rows that predate migration 300
    // (all NULL), so the Recent view can sort by it. Uses the same
    // GREATEST(taskCheckIn, taskCreateTime) the display formatter falls
    // back to, so sorted order matches the shown dates.
    "UPDATE `tasks` "
    . "SET `taskStateChangedTime` = GREATEST(`taskCheckIn`, `taskCreateTime`) "
    . "WHERE `taskStateChangedTime` IS NULL",
];
// 302
$this->schema[] = [
    // Native role-based permissions (retiring the accesscontrol plugin).
    // Adopt the plugin's roles table as native: identical layout, so this
    // converges on both fresh installs and plugin-upgraded databases with
    // zero data migration.
    "CREATE TABLE IF NOT EXISTS `roles` ("
    . "`rID` INT NOT NULL AUTO_INCREMENT,"
    . "`rName` VARCHAR(255) NOT NULL,"
    . "`rDesc` LONGTEXT NOT NULL,"
    . "`rCreatedBy` VARCHAR(40) NOT NULL,"
    . "`rCreatedTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    . "PRIMARY KEY (`rID`),"
    . "UNIQUE KEY `rName` (`rName`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 303
$this->schema[] = [
    // User <-> role assignments, adopted from the accesscontrol plugin.
    // The native layout allows multiple roles per user (composite unique)
    // and defaults ruaName so assocSetter inserts work under strict SQL
    // mode; the closure below normalizes pre-existing plugin-era tables
    // (lone UNIQUE on ruaUserID = one role per user, ruaName no default)
    // to the same shape.
    "CREATE TABLE IF NOT EXISTS `roleUserAssoc` ("
    . "`ruaID` INT NOT NULL AUTO_INCREMENT,"
    . "`ruaName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`ruaRoleID` INT NOT NULL,"
    . "`ruaUserID` INT NOT NULL,"
    . "PRIMARY KEY (`ruaID`),"
    . "UNIQUE KEY `ruaRoleUser` (`ruaRoleID`,`ruaUserID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    function () {
        $indexes = self::$DB->query(
            "SELECT `INDEX_NAME` AS `iname`, "
            . "GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX`) AS `cols`, "
            . "MAX(`NON_UNIQUE`) AS `nonuniq` "
            . "FROM `information_schema`.`STATISTICS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'roleUserAssoc' "
            . "GROUP BY `INDEX_NAME`"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $hasComposite = false;
        $lonely = [];
        foreach ((array)$indexes as $index) {
            if ($index['nonuniq']) {
                continue;
            }
            if ('ruaRoleID,ruaUserID' === $index['cols']) {
                $hasComposite = true;
            }
            if ('ruaUserID' === $index['cols']) {
                $lonely[] = $index['iname'];
            }
        }
        if (!$hasComposite) {
            // Collapse duplicate (role, user) pairs (only possible if the
            // plugin's unique index was hand-removed) so the composite
            // unique can land.
            self::$DB->query(
                "DELETE `a` FROM `roleUserAssoc` `a` "
                . "INNER JOIN `roleUserAssoc` `b` "
                . "ON `a`.`ruaRoleID` = `b`.`ruaRoleID` "
                . "AND `a`.`ruaUserID` = `b`.`ruaUserID` "
                . "AND `a`.`ruaID` > `b`.`ruaID`"
            );
            self::$DB->query(
                "ALTER TABLE `roleUserAssoc` "
                . "ADD UNIQUE INDEX `ruaRoleUser` (`ruaRoleID`,`ruaUserID`)"
            );
        }
        // Drop the plugin's one-role-per-user constraint.
        foreach ($lonely as $iname) {
            self::$DB->query(
                "ALTER TABLE `roleUserAssoc` "
                . "DROP INDEX `" . $iname . "`"
            );
        }
        self::$DB->query(
            "ALTER TABLE `roleUserAssoc` "
            . "MODIFY COLUMN `ruaName` VARCHAR(60) NOT NULL DEFAULT ''"
        );
        return true;
    },
];
// 304
$this->schema[] = [
    // Permissions granted to roles. rpName holds '<node>.<action>'
    // (e.g. 'host.edit'), a node wildcard ('host.*'), or the global
    // wildcard '*'. A user's permissions are the union across all
    // assigned roles; access is deny-by-default, so a user with no role
    // holds nothing (see the Authorization class).
    "CREATE TABLE IF NOT EXISTS `rolePermissions` ("
    . "`rpID` INT NOT NULL AUTO_INCREMENT,"
    . "`rpRoleID` INT NOT NULL,"
    . "`rpName` VARCHAR(64) NOT NULL,"
    . "PRIMARY KEY (`rpID`),"
    . "UNIQUE KEY `rpRolePerm` (`rpRoleID`,`rpName`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 305
$this->schema[] = [
    // Seed the two default roles with the same IDs/names the plugin used,
    // so this is a no-op on plugin-upgraded databases. IGNORE also covers
    // the edge where a role of the same name exists under another ID.
    "INSERT IGNORE INTO `roles` "
    . "(`rID`,`rName`,`rDesc`,`rCreatedBy`,`rCreatedTime`) "
    . "VALUES "
    . "(1,'Administrator','FOG Administrator','fog',NOW()),"
    . "(2,'Technician','FOG Technician','fog',NOW())",
];
// 306
$this->schema[] = [
    // Seed default permission sets, only into roles that have zero
    // permission rows — re-runs never resurrect deliberately removed
    // permissions. Technician (matched by name; a renamed role instead
    // falls through to the wildcard rule below) gets the curated
    // technician set. Every other zero-row role — Administrator and any
    // custom plugin-era role, whose menu rules were cosmetic-only and are
    // intentionally not migrated — gets the global wildcard, preserving
    // its prior effective (full) access.
    function () {
        $techPermissions = [
            'host.*', 'group.*', 'image.view', 'image.task', 'snapin.view',
            'printer.*', 'task.view', 'task.task', 'report.view'
        ];
        $techIDs = self::$DB->query(
            "SELECT `rID` FROM `roles` `r` "
            . "WHERE `rName` = 'Technician' "
            . "AND NOT EXISTS ("
            . "SELECT 1 FROM `rolePermissions` `p` "
            . "WHERE `p`.`rpRoleID` = `r`.`rID`"
            . ")"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $values = [];
        foreach ((array)$techIDs as $row) {
            $rID = (int)$row['rID'];
            foreach ($techPermissions as $permission) {
                $values[] = "($rID,'$permission')";
            }
        }
        if (count($values ?: [])) {
            self::$DB->query(
                "INSERT IGNORE INTO `rolePermissions` (`rpRoleID`,`rpName`) "
                . "VALUES " . implode(',', $values)
            );
        }
        self::$DB->query(
            "INSERT IGNORE INTO `rolePermissions` (`rpRoleID`,`rpName`) "
            . "SELECT `rID`, '*' FROM `roles` `r` "
            . "WHERE NOT EXISTS ("
            . "SELECT 1 FROM `rolePermissions` `p` "
            . "WHERE `p`.`rpRoleID` = `r`.`rID`"
            . ")"
        );
        return true;
    },
];
// 307
$this->schema[] = [
    // Retire the accesscontrol plugin: roles and user assignments went
    // native in steps 302-306 and the plugin code is removed from the
    // tree, so drop its plugins-table row to keep Plugin Management from
    // listing a ghost entry. The plugin's menu-rule tables (rules,
    // roleRuleAssoc) are intentionally left in the database - they were
    // never enforced and deleting data is not this migration's call.
    "DELETE FROM `plugins` WHERE `pName` = 'accesscontrol'",
];
// 308
$this->schema[] = [
    // User groups: a named group of users that roles can attach to. A
    // user's effective permissions are the union of roles assigned
    // directly to the user and roles assigned to any group the user
    // belongs to (see Authorization::getPermissions()). Groups are flat.
    "CREATE TABLE IF NOT EXISTS `userGroups` ("
    . "`ugID` INT NOT NULL AUTO_INCREMENT,"
    . "`ugName` VARCHAR(255) NOT NULL,"
    . "`ugDesc` LONGTEXT NOT NULL,"
    . "`ugCreatedBy` VARCHAR(40) NOT NULL,"
    . "`ugCreatedTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    . "PRIMARY KEY (`ugID`),"
    . "UNIQUE KEY `ugName` (`ugName`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 309
$this->schema[] = [
    // User <-> group membership. Composite unique keeps a user in a group
    // at most once; ugmName defaults so assocSetter batch inserts work
    // under strict SQL mode.
    "CREATE TABLE IF NOT EXISTS `userGroupMembers` ("
    . "`ugmID` INT NOT NULL AUTO_INCREMENT,"
    . "`ugmName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`ugmGroupID` INT NOT NULL,"
    . "`ugmUserID` INT NOT NULL,"
    . "PRIMARY KEY (`ugmID`),"
    . "UNIQUE KEY `ugmGroupUser` (`ugmGroupID`,`ugmUserID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 310
$this->schema[] = [
    // Group <-> role assignments. Composite unique keeps a role on a
    // group at most once; rugName defaults so assocSetter batch inserts
    // work under strict SQL mode.
    "CREATE TABLE IF NOT EXISTS `roleUserGroupAssoc` ("
    . "`rugID` INT NOT NULL AUTO_INCREMENT,"
    . "`rugName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`rugGroupID` INT NOT NULL,"
    . "`rugRoleID` INT NOT NULL,"
    . "PRIMARY KEY (`rugID`),"
    . "UNIQUE KEY `rugGroupRole` (`rugGroupID`,`rugRoleID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 311
$this->schema[] = [
    // Non-regression for native plugin RBAC. Plugin pages became RBAC-gated
    // when each shipped plugin began self-registering its node via the
    // PERMISSION_REGISTRY_DATA hook. Before that, plugin pages were ungated,
    // so any role - including Technician - could reach every installed
    // plugin. Grant Technician the wildcard for each shipped plugin node so
    // upgraded technicians keep exactly the access they had. Administrator
    // already holds '*' and needs nothing here; custom roles are the admin's
    // to grant. INSERT IGNORE plus the (rpRoleID,rpName) unique key make
    // this idempotent, and matching Technician by name means a renamed role
    // is intentionally left untouched (same stance as step 306).
    function () {
        $pluginNodes = [
            'capone', 'helloworld', 'ldap', 'location', 'ntfy', 'ou',
            'pushbullet', 'site', 'slack', 'subnetgroup', 'taskstateedit',
            'tasktypeedit', 'windowskey', 'wolbroadcast'
        ];
        $techIDs = self::$DB->query(
            "SELECT `rID` FROM `roles` WHERE `rName` = 'Technician'"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $values = [];
        foreach ((array)$techIDs as $row) {
            $rID = (int)$row['rID'];
            foreach ($pluginNodes as $node) {
                $values[] = "($rID,'$node.*')";
            }
        }
        if (count($values ?: [])) {
            self::$DB->query(
                "INSERT IGNORE INTO `rolePermissions` (`rpRoleID`,`rpName`) "
                . "VALUES " . implode(',', $values)
            );
        }
        return true;
    },
];
// 312
// Guarded because working-1.6 and dev-branch assign different step numbers
// to the same migration -- they forked at step 264 -- so an install can
// arrive here having already gained these columns from the dev-branch port
// of this change. Steps 275 and 276 exist for exactly this reason. Without
// the guard the duplicate ADD COLUMN would fail, and PDODB does not throw
// on query errors, so it would fail silently rather than visibly.
$columnmsSenderPID = array_filter(
    (array)DatabaseManager::getColumns(
        'multicastSessions',
        'msSenderPID'
    )
);
$this->schema[] = count($columnmsSenderPID ?: []) ? [] : [
    // Sender ownership for multicast sessions. FOGMulticastManager tracked
    // the udp-sender process only in MulticastTask::$procRef, which is
    // in-process memory. A daemon restart lost every reference, so the
    // orphaned sender kept holding its portbase while the re-forked daemon
    // saw an empty known-task list and spawned a SECOND sender for the same
    // session on the same ports. Persisting the pid, the owning storage node
    // and the spawn time lets the daemon reconcile orphans on startup, lets
    // sessions be scoped to the node that actually owns them, and lets the
    // web tier tell whether a session has already begun transmitting.
    "ALTER TABLE `multicastSessions` "
    . "ADD COLUMN `msSenderPID` INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE `multicastSessions` "
    . "ADD COLUMN `msSenderNode` INTEGER NOT NULL DEFAULT 0",
    "ALTER TABLE `multicastSessions` "
    . "ADD COLUMN `msSenderStart` DATETIME NULL DEFAULT NULL",
];
// 313
$this->schema[] = [
    // FOG_MULTICAST_PORT_OVERRIDE is now a pool rather than a single port.
    // It previously forced every concurrent session onto one portbase, so
    // a second session could never actually run; as a comma separated list
    // each entry is one concurrently runnable session, allocated at session
    // creation. The stored value stays valid -- a single port is simply a
    // pool of one, which is what the old setting effectively meant.
    "UPDATE `globalSettings` SET `settingDesc` = 'This setting defines the "
    . "multicast base ports FOG may use. Enter a comma separated list, for "
    . "example 63100,63200,63300 -- each port is one multicast session that "
    . "can run at the same time, and a session takes the port plus the one "
    . "above it. Ports must be even and between 1024 and 65534; anything "
    . "else is ignored. Default is 0, which is disabled and lets FOG pick a "
    . "port per session.' WHERE `settingKey` = 'FOG_MULTICAST_PORT_OVERRIDE'",
];
// Guarded for the same reason as step 312: working-1.6 and dev-branch number
// the same migration differently, so an install may already carry the column.
$columnuAuthSource = array_filter(
    (array)DatabaseManager::getColumns(
        'users',
        'uAuthSource'
    )
);
// 314
$this->schema[] = count($columnuAuthSource ?: []) ? [] : [
    // Records which external provider vouched for an account ('' = local).
    //
    // Authorization::getPermissions() treats "no role rows at all" as an
    // implicit administrator so that upgrades cannot lock anyone out. The
    // LDAP plugin has always created its users with no role rows, so after
    // native RBAC landed every LDAP-authenticated user silently became a
    // full administrator -- the plugin still wrote uType 990/991, and
    // nothing reads uType for authorization any more.
    //
    // The provenance has to live in core, not in the plugin, because the
    // rule it protects lives in core. It is deliberately NOT keyed on the
    // plugin's uType sentinels: uType is a shared generic field that is
    // admin-editable at runtime (FOG_PLUGIN_LDAP_USER_FILTER) and writable
    // over the API and CSV import, and a second auth plugin would have to
    // invent its own magic numbers and hope they never collide. Storing the
    // provider name makes the deny-by-default a standing property of the
    // resolver rather than a check that runs once at login.
    "ALTER TABLE `users` "
    . "ADD COLUMN `uAuthSource` VARCHAR(32) NOT NULL DEFAULT ''",
];
// 315
$this->schema[] = [
    // Aisle 016. status/hostgetkey.php is unauthenticated and MAC-resolved, and
    // the host token it returns is the only gate on service/hostinfo.php, which
    // emits plaintext AD join credentials and the product key. Network position
    // is the only signal available to tell a booting client from an arbitrary
    // caller, and it cannot be inferred (DHCP re-lease, PXE vs OS NIC, VLAN hop,
    // relayed DHCP, NAT all break a strict host-ip match), so the admin declares
    // it. Empty is the default and means no restriction -- an upgrade changes
    // nothing until a site opts in.
    "INSERT IGNORE INTO `globalSettings`"
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)"
    . "VALUES"
    . "('FOG_HOSTKEY_ALLOWED_SOURCES','This setting restricts which source "
    . "addresses may obtain a host token from status/hostgetkey.php, which is "
    . "what unlocks the host information service (including Active Directory "
    . "join credentials and the product key). Enter a comma separated list of "
    . "imaging networks in CIDR form and/or individual addresses, for example "
    . "10.0.0.0/8,192.168.5.20. Leave empty to allow any source, which is the "
    . "default and matches the behavior of earlier versions.','','Security')",
];
// 316
$this->schema[] = [
    // Materialise the implicit-administrator fallback into real roles, so
    // that fallback can be removed.
    //
    // Authorization::getPermissions() has treated "no role rows at all" as
    // full access since RBAC landed, to keep an upgrade from locking
    // everyone out before any role was assigned. That makes an accidental
    // role deletion a silent promotion, and it is the reason a role-less
    // account created by an auth plugin was an administrator. Removing it
    // requires that every account which relies on it today first holds a
    // role that says the same thing explicitly.
    //
    // uType is the only record of what these accounts were: 0 was an
    // administrator, 1 the mobile tier whose UI was deleted in 2017.
    "INSERT IGNORE INTO `roles` "
    . "(`rName`,`rDesc`,`rCreatedBy`,`rCreatedTime`) "
    . "VALUES ('Legacy Restricted','Pre-1.6 restricted (mobile) tier: "
    . "deploy and monitor only','fog',NOW())",
    // Deliberately not the seeded Technician role: Technician carries
    // host.* and group.*, which is strictly broader than the old mobile
    // tier could ever do. This grants what that tier actually had -- see
    // and task hosts and images, watch tasks, read reports -- and nothing
    // that edits or deletes.
    "INSERT IGNORE INTO `rolePermissions` (`rpRoleID`,`rpName`) "
    . "SELECT `r`.`rID`, `p`.`n` FROM `roles` `r` JOIN ("
    . "SELECT 'host.view' AS `n` "
    . "UNION ALL SELECT 'host.task' "
    . "UNION ALL SELECT 'image.view' "
    . "UNION ALL SELECT 'image.task' "
    . "UNION ALL SELECT 'task.view' "
    . "UNION ALL SELECT 'task.task' "
    . "UNION ALL SELECT 'report.view'"
    . ") `p` WHERE `r`.`rName` = 'Legacy Restricted'",
    // Only accounts that hold no role at all, directly or through a group,
    // are touched: those are exactly the ones relying on the fallback.
    // Anyone already carrying a role has been deliberately scoped and must
    // not be promoted.
    //
    // Externally sourced accounts are excluded. Their provenance stamp
    // already denies them by default and their provider assigns their role
    // at login; backfilling them from uType would hand every LDAP account
    // the administrator role that the previous release removed.
    //
    // The role-less tests go through derived tables rather than a plain
    // subquery on roleUserAssoc because MySQL refuses to read the insert
    // target directly (ER_UPDATE_TABLE_USED); a derived table materialises
    // first and is allowed.
    "INSERT IGNORE INTO `roleUserAssoc` (`ruaRoleID`,`ruaUserID`) "
    . "SELECT `r`.`rID`, `u`.`uId` FROM `users` `u` "
    . "JOIN `roles` `r` ON `r`.`rName` = 'Administrator' "
    . "WHERE `u`.`uType` = 0 AND `u`.`uAuthSource` = '' "
    . "AND `u`.`uId` NOT IN ("
    . "SELECT `d`.`uid` FROM (SELECT DISTINCT `ruaUserID` AS `uid` "
    . "FROM `roleUserAssoc`) `d`) "
    . "AND `u`.`uId` NOT IN ("
    . "SELECT `g`.`uid` FROM (SELECT DISTINCT `gm`.`ugmUserID` AS `uid` "
    . "FROM `userGroupMembers` `gm` JOIN `roleUserGroupAssoc` `rg` "
    . "ON `rg`.`rugGroupID` = `gm`.`ugmGroupID`) `g`)",
    "INSERT IGNORE INTO `roleUserAssoc` (`ruaRoleID`,`ruaUserID`) "
    . "SELECT `r`.`rID`, `u`.`uId` FROM `users` `u` "
    . "JOIN `roles` `r` ON `r`.`rName` = 'Legacy Restricted' "
    . "WHERE `u`.`uType` = 1 AND `u`.`uAuthSource` = '' "
    . "AND `u`.`uId` NOT IN ("
    . "SELECT `d`.`uid` FROM (SELECT DISTINCT `ruaUserID` AS `uid` "
    . "FROM `roleUserAssoc`) `d`) "
    . "AND `u`.`uId` NOT IN ("
    . "SELECT `g`.`uid` FROM (SELECT DISTINCT `gm`.`ugmUserID` AS `uid` "
    . "FROM `userGroupMembers` `gm` JOIN `roleUserGroupAssoc` `rg` "
    . "ON `rg`.`rugGroupID` = `gm`.`ugmGroupID`) `g`)",
];
// Guarded the same way as steps 312 and 314: working-1.6 and dev-branch number
// the same migration differently, so an install may already carry the column.
$columnhostSecTokenPrev = array_filter(
    (array)DatabaseManager::getColumns(
        'hosts',
        'hostSecTokenPrev'
    )
);
// 317
$this->schema[] = count($columnhostSecTokenPrev ?: []) ? [] : [
    // FOGPage::authorize() rotates hostSecToken and COMMITS it before the
    // encrypted response carrying the new token can reach the client. Anything
    // that interrupts that delivery -- the encrypt throwing, a dropped
    // connection, a deploy landing mid-request -- left the client holding a
    // token the server had already discarded, and there was no way back: the
    // client has no #!ist handler, and the server-side "recovery" that used to
    // clear pub_key never worked because it left sec_tok in place. The only
    // exit was an administrator pressing Reset Encryption Data.
    //
    // One generation of history closes that gap. A client whose reply went
    // missing re-presents its previous token, is recognized, and is handed the
    // current one again. The grace is retired as soon as the client proves it
    // holds the current token, so a stolen token does not stay valid
    // indefinitely.
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostSecTokenPrev` LONGTEXT NOT NULL",
];
// 318
$this->schema[] = [
    // Sweep snapin tasks that belong to no job.
    //
    // A task is only ever reachable through its job -- the snapin task list
    // resolves stJobID to a snapinJob and then to that job's host to render a
    // row. A task whose job is not there resolves to nothing, and the list
    // endpoint dies on it with "Call to a member function get() on string"
    // rather than skipping the row, so a single bad row takes the whole page
    // down. (The renderer is guarded separately; this clears the rows.)
    //
    // Covers both shapes in one condition: a stJobID pointing at a job that
    // has since been deleted, and a stJobID of 0, which is a task that never
    // had a job at all. Neither is actionable and neither is displayable.
    //
    // Scoped through the join rather than by id so it stays correct on any
    // install. Deliberately NOT keyed on the snapin: a task whose snapin is
    // gone but whose job is intact is still a real record of what that job
    // did, and it renders fine.
    //
    // Refs https://github.com/FOGProject/fogproject/issues/895
    "DELETE `st` FROM `snapinTasks` `st` "
    . "LEFT JOIN `snapinJobs` `sj` ON `sj`.`sjID` = `st`.`stJobID` "
    . "WHERE `sj`.`sjID` IS NULL",
];
// 319
$this->schema[] = [
    // Structural reconcile against commons/schema-expected.php.
    //
    // vValue is a COUNT of applied array elements, not a version: the
    // updater runs array_slice($this->schema, $mySchema). A migration's
    // only identity is its index. working-1.6 and dev-branch fill indexes
    // 263-276 with entirely different migrations, so the count a 1.5
    // install brings with it does not mean here what it meant there.
    //
    // A fully patched 1.5.10 arrives at vValue=277, so the updater starts
    // at 277 and 1.6's 263-276 are skipped in silence -- taking with them
    // groups.groupInit, the plugins pAnon1-4 and multicastSessions
    // msAnon3/4 renames, and the entire userAuths table. That is the
    // RECOMMENDED upgrade path ("fully update 1.5, then move to 1.6"),
    // which is what makes it worth a step of its own.
    //
    // Deliberately NOT a hand-written replay of those fourteen steps.
    // Encoding "what 1.5 skipped" would be correct only until either
    // branch adds another index and shifts the offset again. Comparing the
    // live database against the structure this release expects stays
    // correct whatever the counts do, and doubles as the repair path for a
    // half-failed update or an old restored backup.
    //
    // Additive only -- creates missing tables, adds missing columns,
    // finishes declared renames. Never drops, never retypes, never touches
    // row data, and is safe to run against an already-correct database.
    //
    // The reconcile itself now runs at the END OF EVERY UPDATE, in
    // SchemaUpdaterPage::update(), rather than from here. An indexed step
    // only ever fires for installs sitting below it, so this one would
    // never run again for anyone already at 319 and a future divergence
    // would go unrepaired until someone appended another step. Tying it to
    // "an update happened" instead removes that standing obligation.
    //
    // THIS INDEX MUST STAY. It is deliberately an empty no-op rather than
    // deleted: index positions are identity in this file, 319 is already
    // recorded in the wild as a completed update, and removing it would
    // renumber nothing but would make count($this->schema) disagree with
    // every database that has already reached 319.
];
// 320
$this->schema[] = [
    // Put FOG_UDPCAST_STARTINGPORT back inside the firewalled window.
    //
    // Until now MulticastSession::allocatePort() overwrote this setting with
    // mt_rand(24576, 32766) * 2 after EVERY multicast session, and
    // ImageManagement did it a second time on session create. So whatever is
    // stored on an existing install is a leftover from the last session that
    // ran -- not a value any admin chose, because no admin-chosen value could
    // survive to be used twice. Resetting it therefore discards nothing real.
    //
    // It matters because the port is no longer rotated: this setting is now
    // the base of the window sessions are allocated from, and the installer
    // firewalls exactly that window (63100 .. +2*64). An install carrying a
    // random leftover would be stable but sitting outside the open range, so
    // multicast would keep failing silently on a firewalled server.
    //
    // Scoped so it can only touch values the rotation could actually have
    // produced. mt_rand(24576, 32766) * 2 yields EVEN ports in 49152..65532
    // and nothing else, so a value outside that set -- 30000, say, or an odd
    // number -- is provably an admin's own and is left alone. Values already
    // inside the firewalled window are left alone too; they need no help.
    //
    // Values that are simply unusable (0, odd, out of range) need no step
    // here either: defaultPortPool() rejects them and falls back to 63100 at
    // runtime.
    //
    // settingValue is VARCHAR, so compare as a number explicitly rather than
    // relying on MySQL's implicit coercion of a string column.
    "UPDATE `globalSettings` "
    . "SET `settingValue` = '63100' "
    . "WHERE `settingKey` = 'FOG_UDPCAST_STARTINGPORT' "
    . "AND CAST(`settingValue` AS UNSIGNED) BETWEEN 49152 AND 65532 "
    . "AND CAST(`settingValue` AS UNSIGNED) % 2 = 0 "
    . "AND CAST(`settingValue` AS UNSIGNED) NOT BETWEEN 63100 AND 63228",
];
// 321
$this->schema[] = [
    // Adds the "Enroll Secure Boot Key" PXE menu item, always visible
    // (pxeRegOnly=2, same grouping as fog.local/fog.memtest) regardless of
    // registration state -- a machine needing its MOK enrolled has usually
    // never registered yet.
    //
    // pxeID 14 is the next free id: 1-13 are already taken (8 and 13 were
    // later removed by name in earlier schema steps, but their ids are not
    // reused). IpxeBootMenu::_menuOpt() keys its special-cased (non-kernel-chain)
    // items on this id the same way it already does for 1 (fog.local), 2
    // (fog.memtest) and 11 (fog.advanced).
    //
    // No pxeArgs/pxeParams: this item never reaches IpxeBootMenu's default
    // (login + kernel chain) branch, so neither is read.
    "INSERT IGNORE INTO `pxeMenu` "
    . "(`pxeID`,`pxeName`,`pxeDesc`,`pxeDefault`,`pxeRegOnly`,`pxeArgs`) "
    . "VALUES "
    . "(14, 'fog.enrollsecureboot', 'Enroll Secure Boot Key', '0', '2', NULL)",
];
// 322
$this->schema[] = [
    // Lets "Enroll Secure Boot Key" be scheduled like any other task
    // (against a host or a group) instead of only being reachable by
    // manually picking pxeID 14 from the boot menu every time. 25 is the
    // next free ttID -- 23 and 24 were used and later fully removed (see
    // the two DELETE FROM `taskTypes` steps above), so their ids are not
    // reused.
    //
    // Empty ttKernel/ttKernelArgs: this task type never reaches the
    // generic kernel-chain path in IpxeBootMenu::getTasking() -- it is
    // special-cased there, the same way ttID 4 (Memtest) already is, to
    // reuse IpxeBootMenu::_enrollSecureBootChoice() instead. Leaving
    // ttKernelArgs empty also avoids accidentally matching the
    // type=/mode= regex fallbacks TaskType::isDeploy()/isCapture()/etc.
    // use for tasks created before those columns existed.
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`,`ttName`,`ttDescription`,`ttIcon`,`ttKernel`,"
    . "`ttKernelArgs`,`ttType`,`ttIsAdvanced`,`ttIsAccess`) "
    . "VALUES "
    . "(25, 'Enroll Secure Boot Key', 'Enroll Secure Boot Key will "
    . "chain the client straight to the Secure Boot enrollment menu "
    . "so a technician can enroll this FOG server\'s MOK without "
    . "hunting for it in the PXE boot menu. A technician still has "
    . "to be at the console: MokManager gives up after about 10 "
    . "seconds with no keypress and boots normally, and reboots if "
    . "left idle partway through for a few minutes.', 'lock', '', "
    . "'', 'fog', '1', 'both')",
];
// 323
$this->schema[] = [
    // Redefines task type 25 so it BOOTS FOS and performs the enrollment, rather
    // than chaining the client to MokManager and leaving it there.
    //
    // Step 322 above shipped it as "Enroll Secure Boot Key": a schedulable
    // wrapper around PXE menu item 14, which drops the client at the MokManager
    // screen for a technician to drive by hand. That was the best available
    // answer before FOS could touch EFI variables at all. It can now, and the
    // two are for the same job, so this supersedes it rather than sitting
    // alongside it -- two near-identically named task types with different
    // behavior is a support burden nobody needs.
    //
    // Updated in a NEW step rather than by editing 322 in place: 322 has already
    // run on every 1.6 beta server, and a server does not re-run a step it has
    // passed. Editing it would silently change nothing for exactly the installs
    // that have the old row.
    //
    // ttID 25 is deliberately REUSED here, which the "removed ids are not
    // reused" convention above does not cover: this is the same conceptual task
    // type getting a better implementation, not a new one taking a dead id. Any
    // task already scheduled against 25 keeps working and simply does the more
    // capable thing.
    //
    // ttKernelArgs goes from '' to 'mode=enrollsb' -- that is what routes it
    // down the generic kernel-chain path in IpxeBootMenu::getTasking() now that the
    // _enrollSecureBootChoice() special case for this type is gone. PXE menu
    // item 14 and _enrollSecureBootChoice() itself both stay: chaining straight
    // to MokManager is still how a technician answers a pending request, or
    // enrolls from local FAT media on a machine FOS cannot boot.
    //
    // ttIsAdvanced drops from '1' to '0': the old row hid behind Advanced
    // because it stranded the client at a firmware screen. This one completes on
    // its own in Setup Mode, so hiding it would cost discoverability and buy no
    // safety.
    "UPDATE `taskTypes` SET "
    . "`ttName`='Enroll Secure Boot',"
    . "`ttDescription`='Gets this FOG server''s Secure Boot signing "
    . "certificate trusted by the client, so the client can boot FOS with "
    . "Secure Boot switched on. If the client is in SETUP MODE (its Secure Boot "
    . "keys have been cleared in firmware) the task enrolls the certificate "
    . "automatically and finishes -- no password and nobody at the keyboard; "
    . "Microsoft''s certificates are enrolled alongside it so Windows and "
    . "FOG''s own signed PXE boot keep working. Otherwise it stages a request "
    . "that must be confirmed once at the MOK Manager screen on the next boot, "
    . "by someone at the machine -- shim requires that and it cannot be "
    . "automated; the one-time password is shown on the client screen, or set "
    . "fleet-wide with the sbmokpw kernel argument. EITHER WAY THE CLIENT MUST "
    . "NOT ALREADY BE ENFORCING SECURE BOOT: it does not trust this server''s "
    . "kernel yet, so it cannot boot FOS at all and this task will not run.',"
    . "`ttIcon`='shield',"
    . "`ttKernelArgs`='mode=enrollsb',"
    . "`ttIsAdvanced`='0' "
    . "WHERE `ttID`=25",
];
// 324
$this->schema[] = [
    // Distinguishes pxeID 14 from the unattended item added in step 325
    // below. It has always chained straight to MokManager for a technician
    // to drive by hand; the plain "Enroll Secure Boot Key" name stopped
    // being enough once there is a second, unattended way to enroll from
    // the same menu.
    //
    // A new step, not an edit to step 321: that INSERT has already run on
    // every existing 1.6 beta server, and a server does not re-run a step
    // it has passed.
    // Keyed on pxeName, not pxeID. pxeMenu is user-writable with an
    // auto_increment key, so on a site that already had a custom menu item
    // the step 321 INSERT IGNORE never landed and id 14 belongs to THAT
    // admin's entry -- this UPDATE would have rewritten its description.
    // Safe to key by name here rather than add yet another step: this
    // shipped one commit ago and FOG_SCHEMA was never bumped past it, so no
    // server has run it. See IpxeBootMenu::_menuOpt() and
    // Schema::seedRequiredRows(), which key on the same name.
    "UPDATE `pxeMenu` SET "
    . "`pxeDesc`='Enroll Secure Boot Key (MOK attended setup)' "
    . "WHERE `pxeName`='fog.enrollsecureboot'",
];
// 325
$this->schema[] = [
    // Exposes task type 25's mode=enrollsb (schema step 323) directly on
    // the PXE menu, so a technician standing at a machine already in Setup
    // Mode does not have to leave the console to schedule a task. Falls
    // through IpxeBootMenu::_menuOpt()'s default kernel-chain branch exactly
    // like the existing mode=autoreg/mode=onlydebug/mode=sysinfo items --
    // no special case needed there.
    //
    // pxeID 15 is the next free id (8 and 13 were removed by name in
    // earlier steps but their ids are not reused; 1-14 are otherwise
    // taken). pxeRegOnly=2: same "always shown" grouping as item 14, since
    // a machine needing its Secure Boot key enrolled has usually never
    // registered yet.
    //
    // IpxeBootMenu::printDefault() additionally hides this item unless
    // PK.auth/KEK.auth/db.auth all exist in service/secureboot/ -- without
    // them mode=enrollsb's auto-enroll path has nothing valid to write.
    "INSERT IGNORE INTO `pxeMenu` "
    . "(`pxeID`,`pxeName`,`pxeDesc`,`pxeDefault`,`pxeRegOnly`,`pxeArgs`) "
    . "VALUES "
    . "(15, 'fog.enrollsecurebootunattended', 'Enroll Secure Boot Key "
    . "(Unattended - secure boot in setup mode required)', '0', '2', "
    . "'mode=enrollsb')",
];
// 326
$this->schema[] = [
    // Removes FOG_PLUGINSYS_DIR. The setting only ever pretended to be
    // configurable: Plugin::_getDirs() read it and, whenever it was not
    // exactly '../lib/plugins/', wrote that value straight back before
    // using it -- so editing it in FOG Configuration changed nothing and
    // was silently reverted on the next boot. _getDirs() no longer reads
    // it at all, which leaves an editable row in the UI that does nothing,
    // and a setting that lies is worse than no setting.
    //
    // The plugin roots are fixed in code instead (BASEPATH/lib/plugins,
    // plus FOG_PLUGIN_DIR for third-party plugins -- see ADR 0009).
    // FOG_PLUGINSYS_ENABLED is deliberately NOT touched: that one is a real
    // on/off switch getActivePlugins() still honors.
    "DELETE FROM `globalSettings` WHERE `settingKey` = 'FOG_PLUGINSYS_DIR'",
];
// 327
$this->schema[] = [
    // Repairs plugins whose pSchema survived their uninstall.
    //
    // pSchema counts applied migration steps, but uninstalling a plugin
    // dropped its tables and left the count where it was. The next install
    // then found "already at step N", applied nothing, created no tables, and
    // still reported success -- the plugin came up active with nothing behind
    // it and every query threw "Base table or view not found". Uninstall now
    // clears the count with the tables (PluginManagement::removePost()), but
    // every install that has ever uninstalled a plugin is already carrying a
    // stale one, and the fix alone does not reach them.
    //
    // Safe by definition: a row that is not installed has no tables, so there
    // is no applied migration for the count to describe. Untouched rows where
    // pInstalled is 1.
    "UPDATE `plugins` SET `pSchema` = 0 WHERE `pInstalled` <> '1'",
];
// 328
$this->schema[] = [
    // The switch for the UI plugin installer (ADR 0009 tier 3). Off, and it
    // stays off until an admin turns it on, because turning it on is only
    // half the job: the other half is a root-run command that makes
    // /opt/fog/plugins writable by the web server, and that is a directory
    // PHP autoloads code from. Until both are done the upload route refuses.
    //
    // Deliberately not a single click. A setting that could grant itself a
    // web-writable code directory would be a worse hole than the one it is
    // trying to be convenient about, so the privilege change is a separate,
    // deliberate act with root behind it -- see bin/fog-plugin-uploads.sh.
    "INSERT IGNORE INTO `globalSettings`"
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)"
    . "VALUES"
    . "('FOG_PLUGIN_UI_INSTALL_ENABLED','This setting allows plugins to be "
    . "installed by uploading an archive in Plugin Management. It is off by "
    . "default. A plugin is PHP that runs on this server, so anyone who can "
    . "upload one can run code on it -- the upload also requires the "
    . "plugin.install permission, which is separate from the permission to "
    . "activate a plugin already on disk. Enabling this setting is not enough "
    . "on its own: the plugin directory must also be made writable by the web "
    . "server by running bin/fog-plugin-uploads.sh enable as "
    . "root.','0','Plugin System')",
];
// 329
$this->schema[] = [
    // FOGPluginRunner, the single daemon behind every plugin's scheduled work
    // (ADR 0010). Categories match the other seven services so the runner
    // appears alongside them on the configuration page rather than in a
    // section of its own.
    //
    // The sleep time is also the scheduling granularity: a task asking for 60
    // seconds gets 60 only because this defaults to 60, and raising it rounds
    // every task's effective interval up to it.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('PLUGINRUNNERGLOBALENABLED','This setting defines if the plugin task "
    . "runner should be enabled or not. It runs background work declared by "
    . "installed, active plugins. (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('PLUGINRUNNERSLEEPTIME','The amount of time between plugin task "
    . "runner passes. This is also the finest schedule a plugin task can ask "
    . "for. Value is in seconds. (Default 60)',"
    . "'60','FOG Linux Service Sleep Times'),"
    . "('PLUGINRUNNERLOGFILENAME','Filename to store the plugin task runner "
    . "log file to. It is written to a plugins/ subdirectory of the service "
    . "log path, because this service runs as the web user rather than root. "
    . "(Default fogpluginrunner.log)','fogpluginrunner.log',"
    . "'FOG Linux Service Logs'),"
    . "('PLUGINRUNNERDEVICEOUTPUT','The tty to output to for the plugin task "
    . "runner service. (Default /dev/tty3)','/dev/tty3',"
    . "'FOG Linux Service TTY Output')"
];
// 330
$this->schema[] = [
    // Schedule-only test task for fog.enrollsbforce (fos repo): forces the
    // db/KEK/PK authenticated write sbEnrollDb() performs, without task type
    // 25's "only when sbState()=='setup'" guard, to find out whether the
    // same signed .auth updates also succeed as ordinary authenticated
    // writes outside Setup Mode -- e.g. on a machine that already enrolled
    // this same PK/KEK once. See fos ADR-0009: write policy follows a valid
    // signature against what's currently enrolled, not whether Secure Boot
    // enforcement itself is on or off, so that may hold even while
    // enforcing.
    //
    // No pxeMenu row: deliberately schedule-only, against a single test
    // host, via Task Management. Scheduling creates a real `tasks` row tied
    // to a real hostID, which is what the unregistered-host PXE-menu path
    // (pxeID 15) does not do -- see Post_Wipe.php/TaskQueue::checkout()'s
    // "No Active Task found" failure that surfaced testing that path.
    //
    // ttID 26 is the next free id. Safe to hardcode here, unlike pxeMenu:
    // taskTypes has no admin-facing "create a custom type" page, so there is
    // no user-writable auto_increment collision risk to guard against (see
    // step 324's rationale for why that mattered for pxeMenu).
    //
    // ttIsAdvanced '1': this is a throwaway diagnostic, not a fleet
    // operation, so it stays behind the Advanced toggle rather than sitting
    // next to ttID 25 in the normal task list.
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`,`ttName`,`ttDescription`,`ttIcon`,`ttKernel`,"
    . "`ttKernelArgs`,`ttType`,`ttIsAdvanced`,`ttIsAccess`) "
    . "VALUES "
    . "(26,'Enroll Secure Boot (TEST - force DB write)','TEST TASK: forces "
    . "the Secure Boot database write (db/KEK/PK) regardless of firmware "
    . "state, to check whether it succeeds outside UEFI Setup Mode. Not for "
    . "fleet use -- schedule against a single test host only.','shield','',"
    . "'mode=enrollsbforce','fog','1','both')",
];
// 331
$this->schema[] = [
    // Sites, moving out of the site plugin and into core (Phase 1).
    //
    // Deliberately NOT the plugin's DDL adopted in place, which is how
    // roles came across at step 302. That worked because accesscontrol's
    // roles table was already the shape core wanted. site's is not: its
    // four association tables carry no unique key, so the same host can be
    // in the same site twice, and their Name columns have no default,
    // which breaks assocSetter's batch inserts under strict SQL mode -- the
    // exact two problems steps 303 and 309 had to write repair closures
    // for. Reconstructing (step 332) costs one migration and lets core
    // start from the shape it would choose today.
    //
    // New table names throughout, because the plugin's tables have to stay
    // readable until the reconstruct has run and been checked. `sites` vs
    // `site` is not a typo.
    //
    // Empty and unread at this step. Nothing queries these until scope
    // resolution moves into core, so this is inert on any server.
    "CREATE TABLE IF NOT EXISTS `sites` ("
    . "`siteID` INT NOT NULL AUTO_INCREMENT,"
    . "`siteName` VARCHAR(255) NOT NULL,"
    . "`siteDesc` LONGTEXT NOT NULL,"
    // The catch-all marker, and the reason it is NULL rather than 0.
    //
    // A catch-all site means "no restriction": its members are in scope
    // for everything. Two of them would be meaningless, and worse than
    // meaningless -- a second one is indistinguishable from the first at
    // the point of use, so a stray insert would silently widen what a
    // whole set of users can see. That is a boundary invariant, so the
    // engine holds it rather than the application: InnoDB allows any
    // number of NULLs in a UNIQUE index but only one of a given value,
    // so at most one row can ever carry the marker.
    //
    // Consequence a writer MUST honor: a site that is not the catch-all
    // stores NULL, never 0. 0 is a value like any other, so the second
    // site to store it collides -- and under FOGController::save() that
    // collision is NOT an error. save() builds INSERT ... ON DUPLICATE KEY
    // UPDATE (fogcontroller.class.php:557-562), so creating a second site
    // with siteCatchAll = 0 would UPDATE the first one's row instead of
    // inserting: a create that silently overwrites an unrelated site. That
    // is the bug class three other tables here were repaired for.
    //
    // NULL avoids it structurally rather than by care. save() drops any
    // field whose value is null before building the column list (:545),
    // so a null marker is never in the INSERT at all, never participates
    // in a key collision, and takes the column default -- which is NULL.
    //
    // The CHECK is the belt: it makes storing 0 a hard error rather than a
    // silent overwrite, on every server new enough to enforce it (MariaDB
    // 10.2+, MySQL 8.0.16+). Older servers parse CHECK and ignore it, so
    // it cannot break the CREATE anywhere -- it just does not protect
    // there, which is why the NULL convention above is the real rule and
    // this is only the backstop.
    . "`siteCatchAll` TINYINT(1) UNSIGNED NULL DEFAULT NULL,"
    . "PRIMARY KEY (`siteID`),"
    . "UNIQUE KEY `siteName` (`siteName`),"
    . "UNIQUE KEY `siteCatchAll` (`siteCatchAll`),"
    . "CONSTRAINT `siteCatchAllIsOneOrNull` CHECK (`siteCatchAll` = 1)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // The four membership tables. Same shape as userGroupMembers (step
    // 309): composite unique so a thing is in a site at most once, and a
    // defaulted Name column so assocSetter's batch inserts survive strict
    // SQL mode.
    //
    // Each also carries a plain index on the object column. Scope
    // resolution asks both directions -- "which sites is this user in"
    // when a request starts, and "is this host in any of them" per object
    // -- and the composite unique only serves the site-leading half.
    "CREATE TABLE IF NOT EXISTS `siteHostMembers` ("
    . "`shmID` INT NOT NULL AUTO_INCREMENT,"
    . "`shmName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`shmSiteID` INT NOT NULL,"
    . "`shmHostID` INT NOT NULL,"
    . "PRIMARY KEY (`shmID`),"
    . "UNIQUE KEY `shmSiteHost` (`shmSiteID`,`shmHostID`),"
    . "KEY `shmHostID` (`shmHostID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `siteUserMembers` ("
    . "`sumID` INT NOT NULL AUTO_INCREMENT,"
    . "`sumName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`sumSiteID` INT NOT NULL,"
    . "`sumUserID` INT NOT NULL,"
    . "PRIMARY KEY (`sumID`),"
    . "UNIQUE KEY `sumSiteUser` (`sumSiteID`,`sumUserID`),"
    . "KEY `sumUserID` (`sumUserID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `siteGroupMembers` ("
    . "`sgmID` INT NOT NULL AUTO_INCREMENT,"
    . "`sgmName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`sgmSiteID` INT NOT NULL,"
    . "`sgmGroupID` INT NOT NULL,"
    . "PRIMARY KEY (`sgmID`),"
    . "UNIQUE KEY `sgmSiteGroup` (`sgmSiteID`,`sgmGroupID`),"
    . "KEY `sgmGroupID` (`sgmGroupID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `siteUserGroupMembers` ("
    . "`sugmID` INT NOT NULL AUTO_INCREMENT,"
    . "`sugmName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`sugmSiteID` INT NOT NULL,"
    . "`sugmUserGroupID` INT NOT NULL,"
    . "PRIMARY KEY (`sugmID`),"
    . "UNIQUE KEY `sugmSiteUserGroup` (`sugmSiteID`,`sugmUserGroupID`),"
    . "KEY `sugmUserGroupID` (`sugmUserGroupID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 332
$this->schema[] = [
    // Rebuild the site plugin's data as core's, then drop the plugin's
    // tables -- but only on proof that everything was carried across.
    //
    // One closure rather than a list of statements, for two reasons. A
    // server that never had the plugin has none of these tables, and
    // "Table doesn't exist" is error 1146, which is NOT in the updater's
    // skip list (1050, 1054, 1060, 1061, 1062, 1091) -- an unguarded
    // INSERT ... SELECT would abort the whole update on every fresh
    // install. And the drop has to see what the inserts actually wrote,
    // which only holds if they run in one place.
    //
    // Ids are preserved: `sites`.`siteID` takes the plugin's `sID`. The
    // table was created empty one step ago and this is its only writer, so
    // there is nothing to collide with, and it means the four membership
    // tables need no id translation at all. It also makes the whole step
    // idempotent for free -- a re-run re-inserts the same ids and every
    // row is ignored.
    function () {
        $tables = [
            'site',
            'siteHostAssoc',
            'siteUserAssoc',
            'siteGroupAssoc',
            'siteUserGroupAssoc',
        ];
        // Checked one at a time rather than as a set. The plugin grew
        // siteGroupAssoc and siteUserGroupAssoc in its own schema steps 5
        // and 6, so an install that stopped applying them earlier has the
        // first three and not the last two. That is a normal server, not a
        // damaged one, and it must migrate what it has.
        $present = [];
        $rows = self::$DB->query(
            "SELECT `TABLE_NAME` AS `t` "
            . "FROM `information_schema`.`TABLES` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` IN ('" . implode("','", $tables) . "')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ((array)$rows as $row) {
            $present[] = $row['t'];
        }
        if (!in_array('site', $present, true)) {
            // Never had the plugin, or already migrated and dropped.
            return true;
        }

        // Sites. Duplicate names are renamed rather than dropped: the
        // plugin put no unique key on sName, core does, and two sites
        // called "HQ" are two different sites with two different member
        // lists -- INSERT IGNORE would silently discard one of them and
        // take its members with it. The suffix is derived from how many
        // lower-id rows share the name, so it is stable across re-runs and
        // needs no window function (MariaDB 10.2+ only, and FOG runs on
        // older).
        //
        // A rename that collides with a real existing name is left to the
        // count gate below: the row is ignored, the counts disagree, and
        // the plugin's tables survive for a human to sort out.
        $dupRank = "(SELECT COUNT(*) FROM `site` `p` "
            . "WHERE `p`.`sName` = `s`.`sName` AND `p`.`sID` < `s`.`sID`)";
        self::$DB->query(
            "INSERT IGNORE INTO `sites` (`siteID`, `siteName`, `siteDesc`) "
            . "SELECT `s`.`sID`, "
            . "CASE WHEN $dupRank = 0 THEN `s`.`sName` "
            . "ELSE CONCAT(`s`.`sName`, ' (', $dupRank + 1, ')') END, "
            . "`s`.`sDesc` "
            . "FROM `site` `s`"
        );

        // Memberships. SELECT DISTINCT because the plugin's association
        // tables carry no unique key, so the same host can be in the same
        // site twice; core's tables do, so the duplicate would be ignored
        // and the raw row counts would disagree on a database that is
        // actually fine.
        //
        // Rows are carried across only when BOTH ends still exist. The
        // plugin never cleaned up after a delete, so its tables hold links
        // to sites, hosts and users that are long gone -- verified on a
        // real install, where siteUserAssoc still granted a site to user 6,
        // deleted at some point in the past.
        //
        // A dangling link is not merely untidy. users.uId is
        // AUTO_INCREMENT, and InnoDB recomputes that counter as MAX(id)+1
        // on restart, so an id CAN come round again once every row above it
        // has also gone. A leftover row would then hand a brand new account
        // the deleted one's site without anybody granting it. Remote, but
        // this is the one moment the contents of a security boundary are
        // being chosen, and carrying known-dead rows into it is a decision
        // rather than an oversight.
        //
        // They are counted and logged, not treated as loss: excluded from
        // the expected count as well as the insert, so they cannot block
        // the drop and strand the tables on the servers that need tidying
        // most.
        $members = [
            // core table => [core site col, core obj col, plugin table,
            //                plugin site col, plugin obj col,
            //                object's own table, object's own key]
            'siteHostMembers' => [
                'shmSiteID', 'shmHostID',
                'siteHostAssoc', 'shaSiteID', 'shaHostID',
                'hosts', 'hostID',
            ],
            'siteUserMembers' => [
                'sumSiteID', 'sumUserID',
                'siteUserAssoc', 'suaSiteID', 'suaUserID',
                'users', 'uId',
            ],
            'siteGroupMembers' => [
                'sgmSiteID', 'sgmGroupID',
                'siteGroupAssoc', 'sgaSiteID', 'sgaGroupID',
                'groups', 'groupID',
            ],
            'siteUserGroupMembers' => [
                'sugmSiteID', 'sugmUserGroupID',
                'siteUserGroupAssoc', 'sugaSiteID', 'sugaUserGroupID',
                'userGroups', 'ugID',
            ],
        ];
        // One WHERE for the insert, the expected count and the orphan
        // count, so the three can never drift apart.
        $live = function ($map) {
            list(, , , $sSite, $sObj, $oTable, $oKey) = $map;
            return "`$sSite` IN (SELECT `siteID` FROM `sites`) "
                . "AND `$sObj` IN (SELECT `$oKey` FROM `$oTable`)";
        };
        foreach ($members as $dest => $map) {
            list($dSite, $dObj, $src, $sSite, $sObj) = $map;
            if (!in_array($src, $present, true)) {
                continue;
            }
            self::$DB->query(
                "INSERT IGNORE INTO `$dest` (`$dSite`, `$dObj`) "
                . "SELECT DISTINCT `$sSite`, `$sObj` FROM `$src` "
                . "WHERE " . $live($map)
            );
        }

        // Aliased column + FETCH_ASSOC, matching Schema::_rowsMissing().
        // get() with no field argument does not reduce a single-column row
        // to a scalar, so the alias is what makes the value reachable.
        $count = function ($sql) {
            $row = self::$DB->query($sql)->fetch(\PDO::FETCH_ASSOC)->get();
            return is_array($row) && isset($row['cnt']) ? (int)$row['cnt'] : 0;
        };

        // The gate. Counted per category so the log names which one is
        // short, and compared as "what the source says should be there"
        // against "what is there", both scoped to the sites that migrated.
        $mismatch = [];
        $orphans = 0;
        $expected = $count("SELECT COUNT(*) AS `cnt` FROM `site`");
        $actual = $count(
            "SELECT COUNT(*) AS `cnt` FROM `sites` "
            . "WHERE `siteID` IN (SELECT `sID` FROM `site`)"
        );
        if ($expected !== $actual) {
            $mismatch[] = "sites: expected $expected, wrote $actual";
        }
        foreach ($members as $dest => $map) {
            list($dSite, $dObj, $src, $sSite, $sObj) = $map;
            if (!in_array($src, $present, true)) {
                continue;
            }
            $expected = $count(
                "SELECT COUNT(DISTINCT `$sSite`, `$sObj`) AS `cnt` "
                . "FROM `$src` WHERE " . $live($map)
            );
            $actual = $count(
                "SELECT COUNT(*) AS `cnt` FROM `$dest` "
                . "WHERE `$dSite` IN (SELECT `sID` FROM `site`)"
            );
            if ($expected !== $actual) {
                $mismatch[] = "$dest: expected $expected, wrote $actual";
            }
            $orphans += $count(
                "SELECT COUNT(*) AS `cnt` FROM `$src` "
                . "WHERE NOT (" . $live($map) . ")"
            );
        }

        if ($orphans > 0) {
            error_log(
                sprintf(
                    'FOG site migration: %d association row(s) pointed at a '
                    . 'site, host, user or group that no longer exists and '
                    . 'were not carried across. They were already '
                    . 'unreachable.',
                    $orphans
                )
            );
        }

        if (count($mismatch)) {
            // Deliberately NOT a returned error string. That would abort
            // the schema update and leave the admin unable to finish an
            // upgrade over data that is still intact and still readable.
            // Keeping the source tables is the whole remedy: the repair is
            // a forward fix, and a forward fix needs its input.
            error_log(
                'FOG site migration: counts disagree, so the site plugin '
                . 'tables have been KEPT rather than dropped -- '
                . implode('; ', $mismatch)
                . '. Core is using the new `sites` tables from now on; the '
                . 'old ones are left only so the difference can be worked '
                . 'out. Nothing is lost.'
            );
            return true;
        }

        foreach (array_reverse($tables) as $table) {
            if (in_array($table, $present, true)) {
                self::$DB->query("DROP TABLE IF EXISTS `$table`");
            }
        }
        return true;
    },
];
// 333
$this->schema[] = [
    // The catch-all site, and every user who is in no site joins it.
    //
    // This is what stops the changeover being a fleet-wide lockout. Sites
    // become unconditional here: before, installing the plugin WAS the
    // opt-in, and Site::inScope() denies a user with no sites (deny-all is
    // the correct posture and is not changing). Make the boundary
    // unconditional without this and every non-'*' user on every upgraded
    // server is denied every host the moment they log in.
    //
    // Runs after the reconstruct, so a user the plugin had genuinely
    // scoped keeps exactly the scope they had and is not touched here.
    //
    // A user who had the plugin installed but was never assigned to a site
    // DOES join, which widens what they can see. That is deliberate. "No
    // site" today means the plugin denies them every host, user and group
    // in the system -- an account that cannot do anything is almost never
    // what an admin intended, and far more likely a site they never got
    // round to filling in. The alternative is upgrading a server into a
    // state where working accounts silently stop working, which is the
    // failure this whole step exists to prevent.
    //
    // Nothing is written to the host, group or usergroup tables. The
    // catch-all means "no restriction", not "these particular objects" --
    // it is a flag that scope resolution short circuits on. An enumerated
    // membership list would look identical on the day of the upgrade and
    // then quietly rot: the first host registered afterward would belong
    // to no site and so be invisible to everyone, which is a migration
    // that appears to work and fails on day two, during the most common
    // operation FOG performs.
    function () {
        $held = self::$DB->query(
            "SELECT `siteID` AS `cnt` FROM `sites` "
            . "WHERE `siteCatchAll` IS NOT NULL LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        if (is_array($held) && isset($held['cnt'])) {
            // Already present. Only reachable on a re-run, and a re-run
            // must not re-add users an admin has since taken out.
            return true;
        }

        // siteName is UNIQUE and a migrated site may already be called
        // Everything, so take the first free spelling rather than letting
        // the insert be ignored and the catch-all silently not exist.
        $exists = function ($name) {
            $row = self::$DB->query(
                sprintf(
                    "SELECT COUNT(*) AS `cnt` FROM `sites` "
                    . "WHERE `siteName` = %s",
                    self::$DB->escape($name)
                )
            )->fetch(\PDO::FETCH_ASSOC)->get();
            return is_array($row) && isset($row['cnt']) && (int)$row['cnt'];
        };
        $name = 'Everything';
        for ($n = 2; $exists($name); $n++) {
            $name = "Everything ($n)";
        }

        self::$DB->query(
            sprintf(
                "INSERT INTO `sites` (`siteName`, `siteDesc`, `siteCatchAll`) "
                . "VALUES (%s, %s, 1)",
                self::$DB->escape($name),
                self::$DB->escape(
                    'Members of this site are not restricted to any site: '
                    . 'they see every host, user and group, which is how FOG '
                    . 'behaved before sites were built in. Created during the '
                    . 'upgrade so existing accounts kept working. Safe to '
                    . 'rename; remove members from it to start scoping them.'
                )
            )
        );

        // Read the id back rather than relying on a last-insert-id API.
        // siteCatchAll is UNIQUE, so this identifies the row exactly, and
        // it doubles as confirmation that the insert actually landed.
        $row = self::$DB->query(
            "SELECT `siteID` AS `cnt` FROM `sites` "
            . "WHERE `siteCatchAll` IS NOT NULL LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        if (!is_array($row) || !isset($row['cnt'])) {
            return _('Could not create the catch-all site');
        }
        $siteID = (int)$row['cnt'];

        // Users only, and only those in no site at all. `uId` is spelled
        // with a capital I in this table -- see the CREATE in step 0.
        self::$DB->query(
            sprintf(
                "INSERT IGNORE INTO `siteUserMembers` "
                . "(`sumSiteID`, `sumUserID`) "
                . "SELECT %d, `u`.`uId` FROM `users` `u` "
                . "WHERE `u`.`uId` NOT IN "
                . "(SELECT `sumUserID` FROM `siteUserMembers`)",
                $siteID
            )
        );
        return true;
    },
];
// 334
$this->schema[] = [
    // Retire the site plugin's `plugins` row.
    //
    // The plugin's code is gone as of fog-plugins v1.6.5 -- sites are core
    // now -- but deleting the directory does not delete the row that
    // described it. Discovery only ever walks directories that exist, so
    // nothing else will ever touch it, and Plugin Management goes on
    // listing `site` as installed and active with no code behind it.
    //
    // Inert rather than dangerous: hooks are loaded from disk, so none of
    // the plugin's four enforcement hooks can fire, and activationBlockers()
    // already refuses to re-activate anything with "no code on disk". This
    // is tidying a ghost, not closing a hole.
    //
    // A closure because the row must only go if it really is the bundled
    // plugin we deleted. Three conditions, and each one is load bearing:
    //
    //   name = 'site'          the only plugin this release retired
    //   location under the     an admin's own `site` plugin in
    //     bundled root         FOG_PLUGIN_DIR is not ours to delete
    //   directory absent       the code really is gone
    //
    // The last one is the reason isMissing()'s docblock warns against
    // acting on absence generally: an unmounted external root makes every
    // plugin look absent at once. Pinning to the bundled root -- which is
    // inside the web tree the installer just re-laid -- takes that failure
    // mode off the table, because if THAT is missing the server has bigger
    // problems than a stale row.
    function () {
        $row = self::$DB->query(
            "SELECT `pID` AS `id`, `pLocation` AS `loc` FROM `plugins` "
            . "WHERE LOWER(`pName`) = 'site' LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        if (!is_array($row) || empty($row['id'])) {
            return true;
        }
        $loc = trim((string)($row['loc'] ?? ''));
        $bundled = rtrim(BASEPATH, DS) . DS . 'lib' . DS . 'plugins' . DS;
        if ('' === $loc || 0 !== strncmp($loc, $bundled, strlen($bundled))) {
            return true;
        }
        if (is_dir($loc)) {
            // Code still there, so the row is describing something real and
            // this is not the state the step was written for -- someone put
            // the plugin back deliberately, or an install ran the updater
            // over an older web tree. A step runs once, so nothing revisits
            // this; leaving a live plugin's row alone is the right way to
            // spend that one chance.
            return true;
        }
        self::$DB->query(
            "DELETE FROM `plugins` WHERE `pID` = :id",
            [],
            ['id' => (int)$row['id']]
        );
        return true;
    },
];
// 335
$this->schema[] = [
    // Site scope inherited from a role or a user group.
    //
    // Today a site is assigned one user at a time (`siteUserMembers`), so a
    // fifty-person helpdesk covering two sites is a hundred rows kept by
    // hand, and a new starter is out of scope until somebody remembers
    // them. Both facts needed to fix that already exist -- the user is in a
    // user group and holds a role -- and neither carries a site.
    //
    // GRANT, not MEMBER, and the two are not the same relation even though
    // they hold the same two ids. `siteUserGroupMembers` means "this user
    // group IS IN this site": it is one of the objects the site contains,
    // and a site-scoped admin may see and edit it. `siteUserGroupGrants`
    // means "members of this user group GET this site": holding it is what
    // puts you in scope. Overloading one table for both would save a table
    // and cost the ability to grant somebody access to a site without also
    // making their group an editable object inside it -- which is the
    // distinction sites exist to draw. It would also never fail loudly; it
    // would just quietly widen what site-scoped admins can reach.
    //
    // Empty and unread at this step. Nothing queries these until
    // SiteScope::userSiteIDs() grows its arms, so this is inert on any
    // server that applies it.
    //
    // Shape copied from the four membership tables deliberately, `*Name`
    // column included even though nothing reads it: Route::ids() orders by
    // name and assocSetter() derives its column from the class name, so a
    // table that departs from the pattern stops working with the shared
    // association machinery.
    //
    // The UNIQUE covers every non-id column but the name. That is the case
    // where FOGController::save()'s INSERT ... ON DUPLICATE KEY UPDATE has
    // nothing to destroy -- it can only rewrite the row it matched on --
    // rather than the silent-overwrite bug class the site tables above
    // needed repair closures for.
    "CREATE TABLE IF NOT EXISTS `siteRoleGrants` ("
    . "`srgID` INT NOT NULL AUTO_INCREMENT,"
    . "`srgName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`srgSiteID` INT NOT NULL,"
    . "`srgRoleID` INT NOT NULL,"
    . "PRIMARY KEY (`srgID`),"
    . "UNIQUE KEY `srgSiteRole` (`srgSiteID`,`srgRoleID`),"
    . "KEY `srgRoleID` (`srgRoleID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `siteUserGroupGrants` ("
    . "`suggID` INT NOT NULL AUTO_INCREMENT,"
    . "`suggName` VARCHAR(60) NOT NULL DEFAULT '',"
    . "`suggSiteID` INT NOT NULL,"
    . "`suggGroupID` INT NOT NULL,"
    . "PRIMARY KEY (`suggID`),"
    . "UNIQUE KEY `suggSiteGroup` (`suggSiteID`,`suggGroupID`),"
    . "KEY `suggGroupID` (`suggGroupID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 336
$this->schema[] = [
    // taskLog.taskID becomes the integer it always held. GH-1155.
    //
    // It is a foreign key to `tasks`.`taskID`, an int(11), but the column
    // was mediumtext. The values are numeric only because
    // FOGController::save() coerces them on the way in -- a behavior of the
    // PHP layer, not a constraint of the database -- and three things follow
    // from the type:
    //
    //   no index is possible without a prefix length, so the table has none
    //     on taskID at all and "the log for task N" is a full scan that
    //     adding an ordinary index cannot fix;
    //   a join to `tasks` compares text to int, so MySQL converts per row
    //     and could not use an index even if one existed;
    //   under that conversion '60abc' = 60 is true, so a junk value matches
    //     a real task.
    //
    // A closure, not a bare ALTER, for one reason: MySQL's own conversion
    // turns a non-numeric value into 0 with nothing but a warning, and 0 is
    // a task id that does not exist. Rows like that are unreadable either
    // way, but they should be counted and named in the log rather than
    // rewritten in silence -- an audit table quietly gaining rows that point
    // at task zero is worse than one that says how many it could not read.
    //
    // Idempotent: if the column is already an integer there is nothing to
    // do, so a re-run converges rather than failing on the second ALTER.
    function () {
        $type = self::$DB->query(
            "SELECT LOWER(`DATA_TYPE`) AS `t` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'taskLog' AND `COLUMN_NAME` = 'taskID' LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        if (!is_array($type) || !isset($type['t'])) {
            // No such table or column on this server; nothing to convert.
            return true;
        }
        if (strpos((string)$type['t'], 'int') !== false) {
            return true;
        }
        $bad = self::$DB->query(
            "SELECT COUNT(*) AS `n` FROM `taskLog` WHERE `taskID` REGEXP '[^0-9]'"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        $n = (int)($bad['n'] ?? 0);
        if ($n > 0) {
            error_log(
                sprintf(
                    'FOG taskLog migration: %d row(s) held a non-numeric '
                    . 'taskID and now read 0. They pointed at no task before '
                    . 'the conversion either -- MySQL would have made the '
                    . 'same change on its own, silently.',
                    $n
                )
            );
            self::$DB->query(
                "UPDATE `taskLog` SET `taskID` = 0 WHERE `taskID` REGEXP '[^0-9]'"
            );
        }
        self::$DB->query(
            "ALTER TABLE `taskLog` MODIFY `taskID` INT(11) NOT NULL"
        );
        self::$DB->query(
            "ALTER TABLE `taskLog` ADD KEY `taskID` (`taskID`)"
        );

        return true;
    },
];
// 337
$this->schema[] = [
    // sanboot replaces rEFInd as the default UEFI exit type. GH-1185 follow-up.
    //
    // FOG_EFI_BOOT_EXIT_TYPE was seeded 'refind_efi' at step 192 because iPXE
    // could not then boot the next UEFI boot entry itself: `sanboot` on EFI did
    // nothing useful, so leaving FOG meant chainloading a whole third-party boot
    // manager over HTTP to do it. iPXE grew UEFI sanboot support, and
    // IpxeBootMenu::__construct()'s 'sanboot' entry already drives it --
    // `sanboot --drive 0` boots \EFI\Boot\bootx64.efi, with 0x80/0x81/0x82
    // behind it -- so the third party is no longer buying anything.
    //
    // It is strictly less machinery for the same job. rEFInd is a 200-260KB PE
    // image fetched over unauthenticated HTTP on every single exit from the boot
    // menu, on every host, whether or not a task ran. Under Secure Boot it also
    // has to be signed by this server first (_resignRefind), because the copies
    // upstream ships carry Rod Smith's own certificate or none at all -- so the
    // stock exit path depended on FOG's PKI having worked. sanboot hands control
    // to firmware and adds no image to verify.
    //
    // WHY THIS ALSO MOVES EXISTING SERVERS, and not just new installs.
    // Step 192's INSERT IGNORE cannot: the row already exists everywhere, so
    // editing its seeded value (done, above) only reaches a fresh database.
    // Every server in the field would keep rEFInd forever, which makes the
    // change inert where it matters.
    //
    // Scoped to rows still holding exactly 'refind_efi'. That value was seeded,
    // not chosen -- nobody picked it, it was simply what step 192 wrote -- so
    // moving it is completing the default change rather than overriding a
    // decision. Anything else an admin selected is left alone, and per-host
    // `hosts`.`hostExitEfi` is not touched at all: IpxeBootMenu reads the host
    // field FIRST and only falls back to this setting, so an explicit per-host
    // choice still wins.
    //
    // The honest cost, since it is real: an admin who deliberately selected
    // refind_efi globally is moved to sanboot and has to select it again.
    // 'refind_efi' stays in Setting::buildExitSelector(), rEFInd is still
    // shipped, still preserved across installs and still signed, so re-picking
    // it is one dropdown away and nothing about that path has regressed.
    "UPDATE `globalSettings`"
    . " SET `settingValue` = 'sanboot'"
    . " WHERE `settingKey` = 'FOG_EFI_BOOT_EXIT_TYPE'"
    . " AND `settingValue` = 'refind_efi'",
    "UPDATE `globalSettings`"
    . " SET `settingDesc` = 'The method (U)EFI uses to boot the next boot"
    . " entry/hard drive. (Default SANBOOT)'"
    . " WHERE `settingKey` = 'FOG_EFI_BOOT_EXIT_TYPE'",
];
// 338
$this->schema[] = [
    // taskLog gains a type and a body, so a task can log something that is
    // not a state change. GH-1206 follow-up.
    //
    // Every row in this table so far is one state transition: taskID,
    // taskStateID, who, when, from where. There has never been anywhere to
    // put WHAT happened, which is why FOS reporting a failure (#1207) had
    // only the PHP error log to land in -- a file nobody correlates with a
    // task, on a server whose operator has to know to go looking.
    //
    // `logType` defaults to 'state' and the ALTER backfills every existing
    // row with it, which is what those rows are. The writer at
    // TaskingElement::taskLog() is deliberately left alone: the default is
    // the correct value for it, so a state row costs no extra column.
    // 'error' and 'warning' are what FOS reports arrive as.
    //
    // `logText` is NULL, not '', so "no body" and "an empty body" stay
    // distinguishable -- a state row has no body at all.
    //
    // A closure rather than a bare ALTER for the same reason step 336 is
    // one: ADD COLUMN has no IF NOT EXISTS below MariaDB 10.0.2/MySQL 8.0.29,
    // so a re-run has to converge on its own rather than error.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'taskLog' "
            . "AND `COLUMN_NAME` IN ('logType','logText')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        $adds = [];
        if (!in_array('logType', $cols)) {
            $adds[] = "ADD `logType` VARCHAR(16) NOT NULL DEFAULT 'state'";
        }
        if (!in_array('logText', $cols)) {
            $adds[] = "ADD `logText` TEXT NULL DEFAULT NULL";
        }
        if (count($adds) < 1) {
            return true;
        }
        self::$DB->query(
            "ALTER TABLE `taskLog` " . implode(', ', $adds)
        );

        return true;
    },
];
// 339
$this->schema[] = [
    // A task the host reported dead on gets a state of its own. GH-1206
    // follow-up to step 338.
    //
    // Until now such a task stayed Queued or In-Progress forever: the report
    // was recorded and announced, but the task list still said the machine
    // was working on it, and the host could not be re-tasked because it still
    // held an active task. Somebody had to notice and cancel it by hand.
    //
    // Not reusing Canceled (5), which was the alternative. Canceled means
    // an administrator stopped it; losing the difference between "somebody
    // stopped this" and "this broke" costs the operator the one fact they are
    // looking at the task list to find.
    //
    // INSERT IGNORE, so a re-run converges and a server that somehow already
    // has a row 6 keeps whatever it has rather than having it rewritten.
    "INSERT IGNORE INTO `taskStates` "
    . "(`tsID`,`tsName`,`tsDescription`,`tsOrder`,`tsIcon`) "
    . "VALUES "
    . "(6,'Failed','Host reported that the task could not be completed.',"
    . "6,'exclamation-triangle')",
];
// 340
$this->schema[] = [
    // Retype the rows that landed untyped between step 338 and the model
    // learning to type them.
    //
    // Step 338 gave `logType` a DEFAULT of 'state', which reads as though a
    // writer that sets no type gets one. It does not: a column default
    // applies only when the column is absent from the INSERT, and
    // FOGController::save() writes every declared field -- so
    // TaskingElement::taskLog(), which has recorded state changes since long
    // before this column existed, has been writing '' ever since the field
    // was declared. TaskLog::__construct() now supplies the type, and this
    // repairs what the gap produced.
    //
    // Scoped to '' (and NULL, which the column does not allow today but a
    // hand-edited schema might). Nothing else can be mistaken for it: the
    // only other values are written deliberately by the FOS report endpoint.
    "UPDATE `taskLog` "
    . "SET `logType` = 'state' "
    . "WHERE `logType` = '' OR `logType` IS NULL",
];
// 341
$this->schema[] = [
    // A report keeps enough identity to be read after its task is gone.
    //
    // taskLog stores no host and no task type of its own; Task Management's
    // log pane reaches all three through LEFT OUTER JOINs against `tasks`.
    // Nothing deletes taskLog rows, so a report's TEXT survives forever --
    // but Route::deletemass('host') cascades to `task`, and taskLog is in no
    // cascade at all, so deleting a host destroys its tasks and leaves the
    // reports behind with NULL where the host name was. On the install this
    // was written against, 9 of 56 rows were already orphaned that way.
    //
    // Host name is the first thing anyone searches a failure by, and it is
    // the field that cannot be recovered afterward -- by the time the join
    // fails, the host row is gone too. The point of GH-1206 is that a failure
    // message is findable later instead of arriving as a phone photo of a
    // wrapped console, and a foreign key to a routinely-deleted row cannot
    // deliver that.
    //
    // The alternative was to block deleting a task that has reports, which
    // inverts the dependency -- a diagnostic artifact would then constrain
    // operational cleanup, and to be consistent it would have to block HOST
    // deletion too, since that is the path that actually removes tasks.
    // Refusing to delete a host because it once failed to image is a worse
    // product than losing a host name.
    //
    // The state a row records is NOT copied: taskLog already stores
    // taskStateID itself, so the taskStates join survives the task. Neither
    // is the image, which this view has never shown.
    //
    // Nullable/empty and written only by the FOS report endpoint. 53 of those
    // 56 rows are state transitions written by TaskingElement::taskLog() on
    // every transition; they are meaningless without their task anyway, and
    // making that path do three extra lookups per transition buys nothing.
    // Same reasoning that gave logText no value on a state row in step 338.
    //
    // Two column shapes on purpose, and they follow what the writer can
    // actually produce. FOGController::save() omits an unset OPTIONAL column
    // whose key ends in "id" -- so logHostID gets its DEFAULT of NULL -- but
    // for every other key an unset value is written as '', never NULL (the
    // trap step 340 had to repair for logType, and the reason
    // TaskLog::__construct() types its own rows). Declaring logHostName NOT
    // NULL DEFAULT '' says what the ORM will really store rather than
    // describing a NULL the writer cannot produce.
    //
    // A closure rather than a bare ALTER for the same reason steps 336 and
    // 338 are: ADD COLUMN has no IF NOT EXISTS below MariaDB 10.0.2/MySQL
    // 8.0.29, so a re-run has to converge on its own rather than error.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'taskLog' "
            . "AND `COLUMN_NAME` IN "
            . "('logHostID','logHostName','logTaskTypeName')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        $adds = [];
        if (!in_array('logHostID', $cols)) {
            $adds[] = "ADD `logHostID` INT(11) NULL DEFAULT NULL";
        }
        if (!in_array('logHostName', $cols)) {
            // varchar(16) matches hosts.hostName, which is capped at the
            // NetBIOS limit and cannot outgrow this copy.
            $adds[] = "ADD `logHostName` VARCHAR(16) NOT NULL DEFAULT ''";
        }
        if (!in_array('logTaskTypeName', $cols)) {
            // varchar(30) matches taskTypes.ttName.
            $adds[] = "ADD `logTaskTypeName` VARCHAR(30) NOT NULL DEFAULT ''";
        }
        if (count($adds) > 0) {
            self::$DB->query(
                "ALTER TABLE `taskLog` " . implode(', ', $adds)
            );
        }

        // Backfill the reports whose task is still there, so the history is
        // not split between rows that know their host and rows that do not.
        // Restricted to report rows and to rows not already filled, so a
        // re-run is a no-op and a later hand-correction is not overwritten.
        self::$DB->query(
            "UPDATE `taskLog` "
            . "JOIN `tasks` ON `tasks`.`taskID` = `taskLog`.`taskID` "
            . "LEFT JOIN `hosts` "
            . "ON `hosts`.`hostID` = `tasks`.`taskHostID` "
            . "LEFT JOIN `taskTypes` "
            . "ON `taskTypes`.`ttID` = `tasks`.`taskTypeID` "
            . "SET `taskLog`.`logHostID` = `tasks`.`taskHostID`, "
            . "`taskLog`.`logHostName` = COALESCE(`hosts`.`hostName`, ''), "
            . "`taskLog`.`logTaskTypeName` = COALESCE(`taskTypes`.`ttName`, '') "
            . "WHERE `taskLog`.`logType` <> '" . TaskLog::TYPE_STATE . "' "
            . "AND `taskLog`.`logHostID` IS NULL"
        );

        return true;
    },
];
// 342
$this->schema[] = [
    // GH-1152: de-split a schema whose tables no longer share one collation.
    //
    // Step 0 now pins `DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci` on
    // every CREATE TABLE, which fixes fresh installs. It cannot fix an
    // existing one -- step 0 already ran there -- and the existing install is
    // the only case that can split in the first place.
    //
    // How the split happens: a bare `DEFAULT CHARSET=utf8` inherits whatever
    // the server's default collation for that charset is, and MariaDB changed
    // that between 11.4 and 11.8. Measured, rather than inferred (see
    // scripts/background_scripts/probe_schema_collation_matrix.sh):
    //
    //   MariaDB 10.5.29   bare -> utf8_general_ci
    //   MariaDB 11.4.12   bare -> utf8mb3_general_ci
    //   MariaDB 11.8.8    bare -> utf8mb3_uca1400_ai_ci   <- boundary
    //   MariaDB 12.3.2    bare -> utf8mb3_uca1400_ai_ci
    //   MySQL 8.0.46/8.4  bare -> utf8mb3_general_ci
    //
    // Upgrading a server does not rewrite existing tables. So on a box taken
    // across that boundary, every table that already existed keeps
    // utf8mb3_general_ci and every table created afterward comes out
    // utf8mb3_uca1400_ai_ci -- and nothing reports it. It stays silent until
    // a VARCHAR join crosses the boundary, at which point it is `Illegal mix
    // of collations`. Most of FOG's joins are int-keyed and would never show
    // it, which is exactly why this needs repairing rather than waiting for.
    //
    // CONVERT TO rather than `ALTER TABLE ... DEFAULT CHARACTER SET`: the
    // latter changes only the default for columns added later and leaves
    // every existing column on the old collation, which is the half that
    // actually produces the error. The schema declares no utf8mb4 column
    // anywhere, so nothing is narrowed by converting to utf8mb3, and the one
    // LONGBLOB is a binary type that CONVERT TO a nonbinary charset does not
    // touch.
    //
    // Scoped to tables that are actually wrong, so on the overwhelming
    // majority of installs -- anything at or below the boundary -- this step
    // executes no ALTER at all and costs one information_schema read. That
    // matters: CONVERT TO rebuilds the table, and `hosts`, `tasks` and
    // `taskLog` are not small on a real site.
    function () {
        // Both spellings of the same collation. 10.5 reports it as
        // utf8_general_ci and 11.4+/MySQL as utf8mb3_general_ci, and a table
        // is correct under either name -- comparing against one spelling
        // would rebuild every table on one whole family of servers.
        $okay = "('utf8_general_ci','utf8mb3_general_ci')";

        // Tables whose own default is wrong, UNION tables carrying a column
        // that is wrong. The second half is not redundant: a column takes the
        // table default at creation, so the two normally agree, but a
        // hand-edited or hand-repaired schema can carry one without the
        // other, and a step that converges only on the tidy case is not a
        // repair.
        $rows = self::$DB->query(
            "SELECT `TABLE_NAME` AS `t` FROM `information_schema`.`TABLES` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_TYPE` = 'BASE TABLE' "
            . "AND `TABLE_COLLATION` IS NOT NULL "
            . "AND `TABLE_COLLATION` NOT IN $okay "
            . "UNION "
            . "SELECT DISTINCT `TABLE_NAME` AS `t` "
            . "FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `COLLATION_NAME` IS NOT NULL "
            . "AND `COLLATION_NAME` NOT IN $okay"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        foreach ((array)$rows as $row) {
            if (empty($row['t'])) {
                continue;
            }
            self::$DB->query(
                "ALTER TABLE `" . $row['t'] . "` "
                . "CONVERT TO CHARACTER SET utf8mb3 "
                . "COLLATE utf8mb3_general_ci"
            );
        }

        return true;
    },
];
// 343
$this->schema[] = [
    // GH-1243: repair the zero-date default on installs that already have it.
    //
    // `fdqCompletedDate` was declared DATETIME DEFAULT '0000-00-00 00:00:00'
    // in step 288. That is not a legal default under MySQL 8.0's stock
    // sql_mode -- NO_ZERO_DATE and STRICT_TRANS_TABLES are both on by
    // default -- so the statement answers 1067, which is on neither
    // SchemaUpdaterPage::update()'s $skiperrs nor SchemaReconciler's
    // $_skiperrs.
    //
    // That was reported as "FOG cannot be installed on MySQL 8.0" and the
    // severity was wrong: PDODB::_connect() clears sql_mode on every
    // connection, so the updater never meets NO_ZERO_DATE and the statement
    // runs. What the default really was is DDL whose validity depends on FOG
    // having switched the server's own checks off -- see step 344 and
    // GH-1245. MariaDB's default sql_mode has never included either flag,
    // which is why no MariaDB install would object even without that.
    //
    // Step 288 is edited too, so a fresh install never creates the bad
    // default. This step is for the installs that already did.
    //
    // The rows are cleared BEFORE the column is altered. Nothing reads this
    // value -- FileDeleteQueueManager only ever writes it, on cancel() and
    // complete() -- so a zero date here means "never completed", which is
    // what NULL says without needing a sql_mode that tolerates it. Doing it
    // in this order also means the MODIFY never has to convert an illegal
    // value in place, which is itself an error on a strict server.
    //
    // YEAR() rather than comparing against the literal '0000-00-00 00:00:00':
    // a strict server rejects the literal in the comparison as well, so a
    // WHERE written the obvious way would fail on exactly the servers this
    // exists for.
    "UPDATE `fileDeleteQueue` SET `fdqCompletedDate` = NULL "
    . "WHERE `fdqCompletedDate` IS NOT NULL "
    . "AND YEAR(`fdqCompletedDate`) = 0",
    "ALTER TABLE `fileDeleteQueue` "
    . "MODIFY COLUMN `fdqCompletedDate` DATETIME NULL DEFAULT NULL",
];
// 344
$this->schema[] = [
    // GH-1245: "this never happened" is NULL, not a zero date.
    //
    // FOGController::save() writes '' for any unset optional field whose key
    // does not end in "id". A date column cannot hold '': the server either
    // refuses it or coerces it to '0000-00-00 00:00:00', and FOG only ever
    // sees the second because PDODB::_connect() issues
    // `SET SESSION sql_mode=''` on every connection. On the maintainer's own
    // 1.6 server -- MariaDB 11.8 with STRICT_TRANS_TABLES in its own config --
    // 83 of 86 rows carry a zero `hostLastDeploy` and 85 of 86 a zero
    // `hostSecTime`, values that server's configuration forbids.
    //
    // save() now writes a real NULL for an empty date, which these columns
    // have to be able to hold. Without this step it is worse than a no-op:
    // an explicit NULL into a NOT NULL column errors under a strict mode and
    // is coerced straight back to the zero date without one.
    //
    // Eleven columns, being every date column that is optional, not
    // auto-filled by save()'s switch, and without a server-side default --
    // which is exactly the set that can reach the '' arm and keep the result.
    //
    // Two reachable columns are deliberately left NOT NULL:
    //
    //   snapinTasks.stCheckinDate and userAuths.uaExpireDate both declare
    //   DEFAULT current_timestamp(), so the server supplies a real value
    //   rather than a zero date. uaExpireDate must stay that way: UserAuth
    //   ::reapExpired() deletes on `uaExpireDate` < now, and NULL never
    //   satisfies a comparison -- a nullable expiry would turn a token that
    //   fails safe (reaped at once) into one that is never reaped at all.
    //
    // No historical step is edited, unlike GH-1243's step 343. `datetime NOT
    // NULL` is legal DDL on every server, so the steps that created these
    // columns still replay cleanly and a fresh install simply arrives here
    // and is corrected.
    //
    // ALTER before UPDATE, the opposite order to 343: there the column was
    // already nullable, here the rows cannot be set NULL until it is. YEAR()
    // rather than the literal '0000-00-00 00:00:00' for the same reason as
    // 343 -- a strict server rejects the literal in the comparison too.
    "ALTER TABLE `hosts` "
    . "MODIFY COLUMN `hostLastDeploy` DATETIME NULL DEFAULT NULL",
    "UPDATE `hosts` SET `hostLastDeploy` = NULL "
    . "WHERE `hostLastDeploy` IS NOT NULL AND YEAR(`hostLastDeploy`) = 0",
    "ALTER TABLE `hosts` "
    . "MODIFY COLUMN `hostSecTime` TIMESTAMP NULL DEFAULT NULL",
    "UPDATE `hosts` SET `hostSecTime` = NULL "
    . "WHERE `hostSecTime` IS NOT NULL AND YEAR(`hostSecTime`) = 0",
    "ALTER TABLE `images` "
    . "MODIFY COLUMN `imageLastDeploy` DATETIME NULL DEFAULT NULL",
    "UPDATE `images` SET `imageLastDeploy` = NULL "
    . "WHERE `imageLastDeploy` IS NOT NULL AND YEAR(`imageLastDeploy`) = 0",
    "ALTER TABLE `imagingLog` "
    . "MODIFY COLUMN `ilFinishTime` DATETIME NULL DEFAULT NULL",
    "UPDATE `imagingLog` SET `ilFinishTime` = NULL "
    . "WHERE `ilFinishTime` IS NOT NULL AND YEAR(`ilFinishTime`) = 0",
    "ALTER TABLE `inventory` "
    . "MODIFY COLUMN `iDeleteDate` DATETIME NULL DEFAULT NULL",
    "UPDATE `inventory` SET `iDeleteDate` = NULL "
    . "WHERE `iDeleteDate` IS NOT NULL AND YEAR(`iDeleteDate`) = 0",
    "ALTER TABLE `multicastSessions` "
    . "MODIFY COLUMN `msCompleteDateTime` DATETIME NULL DEFAULT NULL",
    "UPDATE `multicastSessions` SET `msCompleteDateTime` = NULL "
    . "WHERE `msCompleteDateTime` IS NOT NULL AND YEAR(`msCompleteDateTime`) = 0",
    "ALTER TABLE `multicastSessions` "
    . "MODIFY COLUMN `msStartDateTime` DATETIME NULL DEFAULT NULL",
    "UPDATE `multicastSessions` SET `msStartDateTime` = NULL "
    . "WHERE `msStartDateTime` IS NOT NULL AND YEAR(`msStartDateTime`) = 0",
    "ALTER TABLE `snapinTasks` "
    . "MODIFY COLUMN `stCompleteDate` DATETIME NULL DEFAULT NULL",
    "UPDATE `snapinTasks` SET `stCompleteDate` = NULL "
    . "WHERE `stCompleteDate` IS NOT NULL AND YEAR(`stCompleteDate`) = 0",
    "ALTER TABLE `tasks` "
    . "MODIFY COLUMN `taskCheckIn` DATETIME NULL DEFAULT NULL",
    "UPDATE `tasks` SET `taskCheckIn` = NULL "
    . "WHERE `taskCheckIn` IS NOT NULL AND YEAR(`taskCheckIn`) = 0",
    "ALTER TABLE `tasks` "
    . "MODIFY COLUMN `taskScheduledStartTime` DATETIME NULL DEFAULT NULL",
    "UPDATE `tasks` SET `taskScheduledStartTime` = NULL "
    . "WHERE `taskScheduledStartTime` IS NOT NULL AND YEAR(`taskScheduledStartTime`) = 0",
    "ALTER TABLE `userTracking` "
    . "MODIFY COLUMN `utDate` DATE NULL DEFAULT NULL",
    "UPDATE `userTracking` SET `utDate` = NULL "
    . "WHERE `utDate` IS NOT NULL AND YEAR(`utDate`) = 0",
];
// 345
$this->schema[] = [
    // GH-1245: repair the ENUM error value.
    //
    // FOGController::save() wrote '' for every unset optional field whose key
    // does not end in "id". Into an ENUM that is not a member, so the server
    // stored the special error value at index 0 -- which reads back as '' and
    // is illegal to write under any strict sql_mode. FOG never saw the error
    // because PDODB::_connect() cleared sql_mode on every connection.
    //
    // It is not rare. On the maintainer's own 1.6 server: 83 of 87 hostMAC
    // rows in each of three columns, 73 of 86 `hostEnforce`, 85 of 86
    // `hostPending`, every `sShutdown`.
    //
    // Each column lands on its FIRST member, which is what save() now writes
    // for an empty value and what MySQL uses as a NOT NULL enum's implicit
    // default. Deliberately not the column's declared DEFAULT: `hostEnforce`
    // declares DEFAULT '1', so honoring it here would silently turn
    // enforcement ON for 73 hosts as a side effect of a storage repair. '' and
    // '0' are both falsey in PHP, so every consumer sees what it saw before.
    //
    // Every enum column in the schema, not only the ones a model can leave
    // empty today: the error value is illegal wherever it got in, and a
    // column that stops being written by one path may still hold it.
    "UPDATE `hostMAC` SET `hmIgnoreClient` = '0' WHERE `hmIgnoreClient` = ''",
    "UPDATE `hostMAC` SET `hmIgnoreImaging` = '0' WHERE `hmIgnoreImaging` = ''",
    "UPDATE `hostMAC` SET `hmPending` = '0' WHERE `hmPending` = ''",
    "UPDATE `hostMAC` SET `hmPrimary` = '0' WHERE `hmPrimary` = ''",
    "UPDATE `hosts` SET `hostEnforce` = '0' WHERE `hostEnforce` = ''",
    "UPDATE `hosts` SET `hostPending` = '0' WHERE `hostPending` = ''",
    "UPDATE `imageGroupAssoc` SET `igaPrimary` = '0' WHERE `igaPrimary` = ''",
    "UPDATE `images` SET `imageEnabled` = '0' WHERE `imageEnabled` = ''",
    "UPDATE `images` SET `imageReplicate` = '0' WHERE `imageReplicate` = ''",
    "UPDATE `multicastSessions` SET `msShutdown` = '0' WHERE `msShutdown` = ''",
    "UPDATE `nfsGroupMembers` SET `ngmGraphEnabled` = '0' WHERE `ngmGraphEnabled` = ''",
    "UPDATE `powerManagement` SET `pmAction` = 'shutdown' WHERE `pmAction` = ''",
    "UPDATE `powerManagement` SET `pmOndemand` = '0' WHERE `pmOndemand` = ''",
    "UPDATE `pxeMenu` SET `pxeHotKeyEnable` = '0' WHERE `pxeHotKeyEnable` = ''",
    "UPDATE `snapinGroupAssoc` SET `sgaPrimary` = '0' WHERE `sgaPrimary` = ''",
    "UPDATE `snapinJobs` SET `sjAbortOnFail` = '0' WHERE `sjAbortOnFail` = ''",
    "UPDATE `snapins` SET `sEnabled` = '0' WHERE `sEnabled` = ''",
    "UPDATE `snapins` SET `sHideLog` = '0' WHERE `sHideLog` = ''",
    "UPDATE `snapins` SET `sPackType` = '0' WHERE `sPackType` = ''",
    "UPDATE `snapins` SET `sReplicate` = '0' WHERE `sReplicate` = ''",
    "UPDATE `snapins` SET `sShutdown` = '0' WHERE `sShutdown` = ''",
    "UPDATE `taskTypes` SET `ttIsAccess` = 'both' WHERE `ttIsAccess` = ''",
    "UPDATE `taskTypes` SET `ttIsAdvanced` = '0' WHERE `ttIsAdvanced` = ''",
    "UPDATE `taskTypes` SET `ttType` = 'fog' WHERE `ttType` = ''",
    "UPDATE `tasks` SET `taskBypassBitlocker` = '0' WHERE `taskBypassBitlocker` = ''",
    "UPDATE `tasks` SET `taskWOL` = '0' WHERE `taskWOL` = ''",
    "UPDATE `users` SET `uAllowAPI` = '0' WHERE `uAllowAPI` = ''",
];
// 346
$this->schema[] = [
    // ADR 0021: the audit trail. Two tables, header and detail.
    //
    // Inert at this step. Nothing writes either table and neither is in
    // Route::$validClasses, so this ships as storage and a setting and
    // changes no behavior anywhere. The writers arrive in later merges.
    //
    // Every column is named explicitly rather than derived, because a
    // schema step that does not name its columns has broken the
    // installer's grant probe twice already (steps 336 and 338): the probe
    // reads the step to decide what privileges it needs, fails to work it
    // out, and demands a database root password on a server whose grants
    // are fine.
    //
    // WHY THERE IS NO UNIQUE KEY. `history` carries UNIQUE (hText, hTime)
    // and it is the reason that table cannot be trusted -- two identical
    // actions in the same second collapse into one row, silently, through
    // save()'s INSERT ... ON DUPLICATE KEY UPDATE. An audit trail that
    // discards a row because it resembles its neighbor is not one. The
    // volume argument that key was invented for is answered by retention
    // (FOG_AUDIT_RETENTION_DAYS below) and by not auditing reads at all.
    //
    // DATETIME rather than TIMESTAMP, with a server-side default: TIMESTAMP
    // stops at 2038 and this table is meant to be the long record, and a
    // column that fills itself cannot record the zero date that empty
    // writes used to produce across this schema (GH-1243, step 344).
    "CREATE TABLE IF NOT EXISTS `auditLog` ("
    . "`alID` INT NOT NULL AUTO_INCREMENT,"
    . "`alCreatedTime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    // The actor. 'fog' for a machine-originated write, which is what
    // FOGController::save()'s createdBy auto-fill already produces when no
    // user is valid -- the same convention, not a new one.
    . "`alCreatedBy` VARCHAR(255) NOT NULL DEFAULT '',"
    // 45 characters holds an IPv6 address with an IPv4 tail. `history`
    // uses 50 for the same value; this is the size that is actually right.
    . "`alIP` VARCHAR(45) NOT NULL DEFAULT '',"
    // How the REQUEST authenticated, not how the account is configured.
    // FOG already draws that distinction and documents it:
    // User::sessionAuthSource() is about the request, users.uAuthSource
    // about the account, and the two genuinely differ. An audit row is a
    // fact about a request. Machine paths record the credential kind here
    // -- host-token, node, anonymous -- which is the only actor-like fact
    // they hold.
    . "`alAuthSource` VARCHAR(64) NOT NULL DEFAULT '',"
    // ADR 0020's frame: what kind of event, and what it was about.
    . "`alType` VARCHAR(64) NOT NULL DEFAULT '',"
    . "`alSubjectType` VARCHAR(64) NOT NULL DEFAULT '',"
    . "`alSubjectID` INT NOT NULL DEFAULT 0,"
    // Denormalized on purpose. The subject may be deleted -- most often BY
    // the action being recorded -- and an audit row that can only say
    // "host 41" about a host that no longer exists has lost the fact worth
    // keeping. Same reasoning as taskLog's denormalized host name.
    . "`alSubjectLabel` VARCHAR(255) NOT NULL DEFAULT '',"
    // The permission string that was consulted. EMPTY IS MEANINGFUL: it
    // says no authorization ran, which is what every machine path does, so
    // a query for '' is a query for FOG's whole unauthenticated write
    // surface.
    . "`alPermission` VARCHAR(128) NOT NULL DEFAULT '',"
    // 'unknown' is first deliberately. FOGController::save() writes the
    // first ENUM member for an unset value, so whichever member leads is
    // what an incomplete row claims -- and an audit row must not claim
    // 'allowed' because a writer forgot to set the field (GH-1245).
    . "`alOutcome` ENUM('unknown','allowed','denied','failed','partial') "
    . "NOT NULL DEFAULT 'unknown',"
    // One request, one id, however many rows it produces. Request-scoped
    // static state on the PHP side; see ADR 0021 Decision 3.
    . "`alCorrelationID` VARCHAR(32) NOT NULL DEFAULT '',"
    // How many rows the statement touched. The only outcome a bulk
    // UPDATE ... WHERE can report, and the reason a 400-host group edit is
    // one header rather than 400.
    . "`alAffectedCount` INT NOT NULL DEFAULT 0,"
    // The activity-feed projection (ADR 0021 Decision 1). A flag here is
    // what replaced the third table the original proposal wanted: the feed
    // is these rows filtered, and its prose is built at READ time in the
    // reader's locale from alType and the subject columns, never written
    // as a translated string.
    . "`alRenderable` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,"
    // Untranslated machine detail -- a failure reason, a rejected
    // username. NOT a rendered sentence: a sentence written here is
    // written in the locale of whoever triggered it, which is the defect
    // ADR 0020 exists to undo.
    . "`alText` LONGTEXT NOT NULL,"
    // The impersonation bracket (ADR 0033). alCreatedBy stays the REAL
    // administrator throughout a span -- everything that already asks "what
    // did user X do" reads that column, and flipping it would attribute the
    // target's name to actions they did not take. This column is
    // supplementary: who was being acted AS.
    //
    // Empty is meaningful, the same way alPermission's is: '' says nobody
    // was impersonating, so `alActedAs <> ''` is FOG's entire impersonated
    // write surface in one predicate.
    . "`alActedAs` VARCHAR(255) NOT NULL DEFAULT '',"
    // One span, one id, however many requests it covers -- which is what
    // makes it a different column from alCorrelationID rather than a reuse
    // of it. A correlation id is REQUEST scoped by design; folding the two
    // lifetimes together reads fine and then cannot be untangled.
    . "`alSpanID` VARCHAR(32) NOT NULL DEFAULT '',"
    . "PRIMARY KEY (`alID`),"
    . "KEY `alCreatedTime` (`alCreatedTime`),"
    . "KEY `alCreatedBy` (`alCreatedBy`),"
    . "KEY `alCorrelationID` (`alCorrelationID`),"
    . "KEY `alOutcome` (`alOutcome`),"
    . "KEY `alSubject` (`alSubjectType`,`alSubjectID`),"
    // Indexed because both are read as filters rather than displayed: the
    // span id answers "everything this administrator did while masked" in
    // one seek, and alActedAs answers "has anyone ever acted as this
    // person". A composite of (alType, alSubjectID) is deliberately not
    // added for the sign-in notice -- alSubject already covers it.
    . "KEY `alSpanID` (`alSpanID`),"
    . "KEY `alActedAs` (`alActedAs`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // One row per changed field.
    //
    // Deliberately NOT a foreign key. FOG declares none anywhere, and one
    // here would make the retention sweep's DELETE order load bearing --
    // exactly the kind of thing that fails on a restore onto a server with
    // different settings, in a way that looks nothing like its cause.
    //
    // acSubjectType/acSubjectID repeat the header's because one header can
    // cover many objects: an iterating path that saves 40 hosts writes one
    // header and change rows for each host that landed. Without these,
    // those rows could not say which host they belonged to.
    "CREATE TABLE IF NOT EXISTS `auditChange` ("
    . "`acID` INT NOT NULL AUTO_INCREMENT,"
    . "`acAuditID` INT NOT NULL,"
    . "`acSubjectType` VARCHAR(64) NOT NULL DEFAULT '',"
    . "`acSubjectID` INT NOT NULL DEFAULT 0,"
    . "`acField` VARCHAR(128) NOT NULL DEFAULT '',"
    // Nullable, but ACREDACTED IS THE RECORD, not the NULL. A redacted row
    // carries the field name, redacted = 1, and no value -- not a masked
    // string, not a length, not a hash, because anything derived from a
    // credential is a disclosure with extra steps.
    //
    // These columns were declared expecting a redacted row to hold NULL and
    // they hold '' instead, verified against a live server. That is not a
    // bug in either layer: FOGController::save()'s GH-1245 policy writes
    // emptyValueFor(), which for a text column is '' -- correct in general,
    // since '' is a real value a text column can hold. Fighting it for one
    // table would mean a special case in the ORM. So the flag is what
    // separates "withheld" from "was empty", and it cannot disagree with
    // the values: Redaction::values() returns all three together.
    //
    // The columns stay NULL-able so a future direct writer can express the
    // distinction, and because it costs nothing.
    . "`acOldValue` LONGTEXT NULL DEFAULT NULL,"
    . "`acNewValue` LONGTEXT NULL DEFAULT NULL,"
    . "`acRedacted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,"
    . "PRIMARY KEY (`acID`),"
    . "KEY `acAuditID` (`acAuditID`),"
    . "KEY `acSubject` (`acSubjectType`,`acSubjectID`)"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // 0 = keep forever, which is the only safe default for an upgrade: an
    // admin who has never been asked has not consented to their audit
    // history being deleted. The sweep that reads this arrives with the
    // retention registry (ADR 0021 Decision 9, amended by ADR 0023); this
    // is the first entry in that registry rather than the only one.
    //
    // FOG_SCHEMA is bumped in the same commit. An INSERT here without the
    // bump is silently skipped on every install -- the coarse gate never
    // sends the admin to the updater, so the precise one never runs.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_AUDIT_RETENTION_DAYS','How many days of audit trail to keep. "
    . "0 keeps everything forever, which is the default. Set a number of "
    . "days and a periodic sweep deletes audit rows older than that. "
    . "Shortening this window is itself recorded in the audit trail before "
    . "it takes effect.','0','Logging Settings')",
];
// 347
$this->schema[] = [
    // The other three tables the retention registry ages out. They arrived
    // from three ADRs -- history and userTracking from 0023, imagingLog from
    // 0022, which defers to 0021's mechanism explicitly -- and they are one
    // step because they are one feature: four settings read by one sweep.
    //
    // ALL DEFAULT TO 0, KEEP FOREVER, ON EVERY INSTALL AND EVERY UPGRADE.
    // ADR 0023 Decision 7 wanted new installs to default to a bounded window
    // and upgrades to keep everything; a schema step cannot tell the two
    // apart, so it does the safe half here and the new-install default is
    // the installer's to apply. Deleting on upgrade would be wrong for a
    // specific reason: the administrator never chose to hold this data OR to
    // delete it, and some of them are legally required to retain it.
    //
    // userTracking is called out in its own words because it is the one that
    // is about PEOPLE -- which named person signed in to which machine, and
    // when -- rather than about equipment.
    //
    // Columns named, per the note on step 346: a step that does not name
    // them has broken the installer's grant probe twice.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_HISTORY_RETENTION_DAYS','How many days of administrative "
    . "history to keep. 0 keeps everything forever, which is the default. "
    . "Shortening this window is recorded in the audit trail before it "
    . "takes effect.','0','Logging Settings'),"
    . "('FOG_USERTRACKING_RETENTION_DAYS','How many days of host login "
    . "records to keep. These name the person who signed in to each machine "
    . "and when, so a shorter window here is a privacy control as much as a "
    . "storage one. 0 keeps everything forever, which is the default on new "
    . "installs and upgrades alike. Shortening this window is recorded in "
    . "the audit trail before it takes effect.','0','Logging Settings'),"
    . "('FOG_IMAGINGLOG_RETENTION_DAYS','How many days of imaging history "
    . "to keep, measured from when each task started. 0 keeps everything "
    . "forever, which is the default. Shortening this window is recorded in "
    . "the audit trail before it takes effect.','0','Logging Settings')",
];

// 348
$this->schema[] = [
    // GH-1245, the third instalment: make the schema SAY which columns are
    // optional, instead of leaving it to be inferred.
    //
    // A column declared NOT NULL with no DEFAULT is only mandatory if
    // something enforces it. Under a non-strict sql_mode the server does not
    // -- it downgrades the error to a warning and substitutes an implicit
    // zero value -- so for the nine years PDODB cleared sql_mode, the
    // declaration was a comment rather than a constraint. Removing the clear
    // turned every one of those columns into a real constraint at once, which
    // is how saving FOG settings started failing with error 1364.
    //
    // For the TEXT columns it was never even a decision: MySQL could not
    // attach a DEFAULT to a TEXT or BLOB column until 8.0.13, MariaDB until
    // 10.2.1. `longtext NOT NULL` was the only phrasing the schema language
    // offered, so those columns are mandatory by accident of syntax. FOG's
    // schema still shows it -- 53 optional longtext columns, not one of them
    // carrying a default.
    //
    // WHICH COLUMNS. Not a judgment call: FOG already states its intent in
    // each model's $databaseFieldsRequired, and this is that statement made
    // true in the database. Of the 417 columns that are NOT NULL, carry no
    // DEFAULT and are not AUTO_INCREMENT, 163 stay exactly as they are --
    // 148 the models declare required, plus 15 foreign keys they had not
    // declared but where an INSERT that forgets the key SHOULD fail. The 254
    // below are the rest.
    //
    // WHY THIS CANNOT BREAK A WORKING WRITE. An INSERT that names the column
    // is unaffected; a default applies only to an omitted column. An INSERT
    // that omits it currently FAILS outright on a strict server, so there is
    // no working behavior to change. On a non-strict server it currently
    // gets the server's implicit coercion -- and the defaults chosen here are
    // exactly that coercion ('' for text, 0 for integers, the first member
    // for an enum), which is the same rule FOGBase::emptyValueFor() applies.
    // So both kinds of server end up where they already were, with the
    // difference that the schema now says so.
    //
    // users.uCreateDate is the one column given a live default rather than a
    // zero: a user record created without a date wants now, and writing a
    // zero date is the GH-1245 bug in a different costume. Existing rows are
    // untouched either way -- a DEFAULT never rewrites stored data.
    function () {
        $optional = [
            'auditLog' => [
                'alText'
            ],
            'clientUpdates' => [
                'cuFile', 'cuMD5', 'cuName', 'cuType'
            ],
            'dirCleaner' => [
                'dcPath'
            ],
            'fileDeleteQueue' => [
                'fdqState'
            ],
            'globalSettings' => [
                'settingCategory', 'settingDesc', 'settingValue'
            ],
            'greenFog' => [
                'gfAction', 'gfDays', 'gfHour', 'gfMin'
            ],
            'groups' => [
                'groupBuilding', 'groupCreateBy', 'groupDesc',
                'groupInit', 'groupKernel', 'groupKernelArgs',
                'groupPrimaryDisk'
            ],
            'history' => [
                'hIP', 'hText', 'hUser'
            ],
            'hostMAC' => [
                'hmDesc', 'hmIgnoreClient', 'hmIgnoreImaging',
                'hmPending', 'hmPrimary'
            ],
            'hosts' => [
                'hostADDomain', 'hostADOU', 'hostADPass',
                'hostADPassLegacy', 'hostADUser', 'hostBuilding',
                'hostCreateBy', 'hostDesc', 'hostDevice', 'hostIP',
                'hostImage', 'hostKernel', 'hostKernelArgs',
                'hostPending', 'hostPrinterLevel', 'hostPubKey',
                'hostSecToken', 'hostSecTokenPrev', 'hostUseAD'
            ],
            'hostScreenSettings' => [
                'hssHeight', 'hssOrientation', 'hssOther1', 'hssOther2',
                'hssRefresh', 'hssWidth'
            ],
            'imageGroupAssoc' => [
                'igaPrimary'
            ],
            'images' => [
                'imageBuilding', 'imageCreateBy', 'imageDesc',
                'imageMagnetUri', 'imageProtect', 'imageSize'
            ],
            'imagingLog' => [
                'ilCreatedBy', 'ilType'
            ],
            'inventory' => [
                'iBiosdate', 'iBiosvendor', 'iBiosversion', 'iCaseasset',
                'iCaseman', 'iCaseserial', 'iCasever', 'iCpucurrent',
                'iCpuman', 'iCpumax', 'iCpuversion', 'iGpuproducts',
                'iGpuvendors', 'iHdfirmware', 'iHdmodel', 'iHdserial',
                'iMbasset', 'iMbman', 'iMbproductname', 'iMbserial',
                'iMbversion', 'iMem', 'iOtherTag', 'iOtherTag1',
                'iPrimaryUser', 'iSysman', 'iSysproduct', 'iSysserial',
                'iSystemUUID', 'iSystype', 'iSysversion'
            ],
            'ipxeTable' => [
                'ipxeFailure', 'ipxeFilename', 'ipxeMAC',
                'ipxeManufacturer', 'ipxeProduct', 'ipxeSuccess',
                'ipxeVersion'
            ],
            'LDAPServers' => [
                'lsAdminGroup', 'lsBindDN', 'lsBindPwd', 'lsCreatedBy',
                'lsDesc', 'lsDisplayNameAttr', 'lsDisplayNameEnabled',
                'lsIsLDAPs', 'lsUserFilter', 'lsUserGroup'
            ],
            'location' => [
                'lCreatedBy', 'lDesc', 'lStorageNodeProto',
                'lTftpEnabled'
            ],
            'modules' => [
                'description'
            ],
            'moduleStatusByHost' => [
                'msState'
            ],
            'multicastSessions' => [
                'msAnon5', 'msBasePort', 'msClients', 'msImage',
                'msInterface', 'msIsDD', 'msLogPath', 'msMaxwait',
                'msName', 'msPercent', 'msSessClients', 'msState'
            ],
            'nfsGroupMembers' => [
                'ngmBandwidthLimit', 'ngmIsEnabled', 'ngmIsMasterNode',
                'ngmKey', 'ngmMaxClients', 'ngmMemberDescription',
                'ngmMemberName', 'ngmSSLPath', 'ngmSnapinPath',
                'ngmWebroot'
            ],
            'nfsGroups' => [
                'ngDesc'
            ],
            'ntfy' => [
                'nCredentials'
            ],
            'OIDCProviders' => [
                'opClientSecret', 'opCreatedBy', 'opDesc'
            ],
            'os' => [
                'osDescription'
            ],
            'ou' => [
                'ouCreatedBy', 'ouDesc'
            ],
            'plugins' => [
                'pAnon5', 'pDescription', 'pIcon', 'pInstalled',
                'pLocation', 'pRunfile', 'pState', 'pVersion'
            ],
            'powerManagement' => [
                'pmDom', 'pmDow', 'pmHour', 'pmMin', 'pmMonth',
                'pmOndemand'
            ],
            'printerAssoc' => [
                'paAnon1', 'paAnon2', 'paAnon3', 'paAnon4', 'paAnon5',
                'paIsDefault'
            ],
            'printers' => [
                'pAnon2', 'pAnon3', 'pAnon4', 'pAnon5', 'pConfig',
                'pConfigFile', 'pDefFile', 'pIP', 'pModel', 'pPort'
            ],
            'pxeMenu' => [
                'pxeDesc', 'pxeHotKeyEnable', 'pxeKeySequence',
                'pxeParams'
            ],
            'roles' => [
                'rCreatedBy', 'rDesc'
            ],
            'scheduledTasks' => [
                'stDOM', 'stDOW', 'stDesc', 'stHour', 'stMinute',
                'stMonth', 'stName', 'stOther1', 'stOther2', 'stOther3',
                'stOther4', 'stOther5', 'stShutDown'
            ],
            'schemaVersion' => [
                'vValue'
            ],
            'sites' => [
                'siteDesc'
            ],
            'snapinGroupAssoc' => [
                'sgaPrimary'
            ],
            'snapins' => [
                'sAnon3', 'sArgs', 'sCreator', 'sDesc', 'sReboot',
                'sRunWith', 'sRunWithArgs', 'snapinProtect'
            ],
            'snapinTasks' => [
                'stReturnCode', 'stReturnDetails', 'stState'
            ],
            'supportedOS' => [
                'osName', 'osValue'
            ],
            'taskLog' => [
                'createdBy', 'ip'
            ],
            'tasks' => [
                'taskBPM', 'taskCreateBy', 'taskDataCopied',
                'taskDataTotal', 'taskForce', 'taskIsDebug',
                'taskNFSFailures', 'taskName', 'taskPCT', 'taskPassreset',
                'taskPercentText', 'taskShutdown', 'taskTimeElapsed',
                'taskTimeRemaining', 'taskWOL'
            ],
            'taskStates' => [
                'tsDescription', 'tsIcon'
            ],
            'taskTypes' => [
                'ttDescription', 'ttInitrd', 'ttKernel', 'ttKernelArgs'
            ],
            'userAuths' => [
                'uaPasswordHash', 'uaSelectorHash'
            ],
            'userCleanup' => [
                'ucName'
            ],
            'userGroups' => [
                'ugCreatedBy', 'ugDesc'
            ],
            'users' => [
                'uAPIToken', 'uCreateDate', 'uDisplay', 'uType'
            ],
            'userTracking' => [
                'utAction', 'utAnon3', 'utDesc'
            ],
            'virus' => [
                'vAnon2', 'vHostMAC', 'vMode', 'vName', 'vOrigFile'
            ],
            'windowsKeys' => [
                'wkCreatedBy', 'wkDesc'
            ],
        ];

        // MySQL and MariaDB spell a TEXT/BLOB default differently: MariaDB
        // takes the literal, MySQL requires it parenthesised as an
        // expression and rejects it outright below 8.0.13. Getting this
        // wrong is not subtle -- the ALTER is refused and the step fails --
        // but it is only visible on the server you are not developing on,
        // which is what CI's mysql:8.0 job is for.
        $version = (string) self::$DB->query('SELECT VERSION() AS `v`')
            ->fetch()->get('v');
        $maria = false !== stripos($version, 'mariadb');
        $lobDefaults = $maria;
        if (!$maria) {
            preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m);
            $lobDefaults = count($m) === 4
                && (int) $m[1] * 10000 + (int) $m[2] * 100 + (int) $m[3]
                    >= 80013;
        }

        foreach ($optional as $table => $columns) {
            // Only columns that are actually still missing a default, so a
            // re-run is a read and nothing else. A table that does not exist
            // -- a plugin's, on an install that never had it -- returns
            // nothing and is skipped rather than erroring.
            $rows = self::$DB->query(
                "SELECT `COLUMN_NAME` AS `c`, `COLUMN_TYPE` AS `ty` "
                . "FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND LOWER(`TABLE_NAME`) = :table "
                . "AND `IS_NULLABLE` = 'NO' "
                . "AND `COLUMN_DEFAULT` IS NULL "
                . "AND `EXTRA` NOT LIKE '%auto_increment%'",
                [],
                [':table' => strtolower($table)]
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

            $want = array_map('strtolower', $columns);
            foreach ((array) $rows as $row) {
                if (!isset($row['c'], $row['ty'])
                    || !in_array(strtolower($row['c']), $want, true)
                ) {
                    continue;
                }
                $type = trim($row['ty']);
                $lob = (bool) preg_match(
                    '/^(tiny|medium|long)?(text|blob)\b/i',
                    $type
                );
                if ($lob && !$lobDefaults) {
                    // Nothing sensible to do on MySQL below 8.0.13, and
                    // nothing broken by skipping: insertBatch() backfills
                    // the column and save() writes it explicitly.
                    continue;
                }
                if (preg_match('/^datetime\b/i', $type)) {
                    $default = 'current_timestamp()';
                } elseif (preg_match(
                    '/^(tiny|small|medium|big)?int\b/i',
                    $type
                )) {
                    $default = '0';
                } elseif (preg_match(
                    "/^(enum|set)\\s*\\(\\s*'((?:[^']|'')*)'/i",
                    $type,
                    $member
                )) {
                    $default = "'" . $member[2] . "'";
                } elseif ($lob) {
                    $default = $maria ? "''" : "('')";
                } else {
                    $default = "''";
                }
                self::$DB->query(
                    sprintf(
                        'ALTER TABLE `%s` MODIFY COLUMN `%s` %s NOT NULL '
                        . 'DEFAULT %s',
                        $table,
                        $row['c'],
                        $type,
                        $default
                    )
                );
            }
        }

        return true;
    },
];

// 349
$this->schema[] = [
    // ADR 0020 phase 2, userTracking half: add the frame columns, write
    // nothing to them.
    //
    // `userTracking` records a login or logout on a host. Three of the six
    // frame keys have no column at all today, so a row cannot say who in FOG
    // is responsible, where it arrived from, or which host it was about once
    // that host is deleted.
    //
    // utCreatedBy -- the FOG identity, matching taskLog.createdBy's width.
    // This is NOT utUserName. utUserName is the endpoint's OS account, which
    // is the subject of the event, not its actor; ADR 0020 decision 3 calls
    // that the load-bearing correction and the reason to add a column rather
    // than reinterpret the one that is there. save() auto-fills createdBy
    // once the model maps it, which is phase 3, not this step.
    //
    // utIP -- the origin address. The estate has two widths for this frame
    // key, taskLog.ip varchar(15) and history.hIP varchar(50). Taking the
    // wider one deliberately: 15 characters cannot hold an IPv6 address, and
    // a new column has no reason to inherit that.
    //
    // utHostName -- the denormalized subject label. varchar(16) matches
    // hosts.hostName, which is capped at the NetBIOS limit and cannot
    // outgrow this copy. Same shape and same reason as logHostName in step
    // 341: Route::deletemass('host') removes the host, and a login history
    // that survives with a dangling id and no name is not a history.
    //
    // Every column is nullable or DEFAULT '', nothing writes to them, and no
    // reader knows they exist. An install that stops here behaves exactly as
    // it did before -- this is the reversible half of the ADR's DDL.
    //
    // A closure rather than a bare ALTER for the same reason steps 336, 338
    // and 341 are: ADD COLUMN has no IF NOT EXISTS below MariaDB 10.0.2 /
    // MySQL 8.0.29, so a re-run has to converge on its own rather than
    // error. Every column is named in the probe, because steps 336 and 338
    // broke the installer's grant check by not naming them (GH-336, GH-338).
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'userTracking' "
            . "AND `COLUMN_NAME` IN ('utCreatedBy','utIP','utHostName')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        $adds = [];
        if (!in_array('utCreatedBy', $cols)) {
            $adds[] = "ADD `utCreatedBy` VARCHAR(30) NOT NULL DEFAULT ''";
        }
        if (!in_array('utIP', $cols)) {
            $adds[] = "ADD `utIP` VARCHAR(50) NOT NULL DEFAULT ''";
        }
        if (!in_array('utHostName', $cols)) {
            $adds[] = "ADD `utHostName` VARCHAR(16) NOT NULL DEFAULT ''";
        }
        if (count($adds) > 0) {
            self::$DB->query(
                "ALTER TABLE `userTracking` " . implode(', ', $adds)
            );
        }

        return true;
    },
];

// 350
$this->schema[] = [
    // ADR 0020 phase 2, history half: give `history` a subject and a type,
    // write nothing to them.
    //
    // `history` is the outlier of the three event tables, and its defect is
    // structural rather than cosmetic: it has no subject at all. The entity a
    // row is about exists only inside hText, which is assembled from gettext
    // calls at write time -- so the record of what happened is stored in
    // whatever language the server was set to when it happened, and nothing
    // can query it.
    //
    // hType -- what kind of event, as a stable machine code, matching
    // taskLog.logType's width. DEFAULT '' is deliberate and is the whole
    // rollout: an empty hType marks a row written before this ADR, and that
    // is exactly what phase 4's readers key their prose fallback on. Step 338
    // gave logType a DEFAULT of 'state', which reads as though a real value
    // was recorded when none was, and step 340 had to repair it. Do not give
    // this column a plausible-looking default.
    //
    // hSubjectType -- the subject's class name. `history` needs this and
    // taskLog/userTracking do not: they are always about a Host and can say
    // so as a constant, while `history` is about anything (ADR 0020
    // decision 2).
    //
    // hSubjectID -- the subject's id, nullable. FOGController::save() omits
    // an unset OPTIONAL column whose friendly key ends in "id", so it takes
    // the DEFAULT; for every other key an unset value is written as '',
    // never NULL. Declaring this one NULL DEFAULT NULL and the rest NOT NULL
    // DEFAULT '' says what the ORM will really store, rather than describing
    // a value the writer cannot produce. Same split, same reason, as step
    // 341.
    //
    // hSubjectLabel -- the denormalized label, so the row still names its
    // subject after the subject is deleted. varchar(200) because unlike
    // taskLog and userTracking this table's subject is not always a host:
    // it is sized to the widest name column a subject can have
    // (snapins.sName varchar(200)), not to hosts.hostName varchar(16).
    //
    // Additive and inert, exactly as step 349. Dropping history's
    // UNIQUE (hText, hTime) and widening hText belong to phase 5, a full
    // release cycle after the readers switch, and are deliberately not here.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'history' "
            . "AND `COLUMN_NAME` IN "
            . "('hType','hSubjectType','hSubjectID','hSubjectLabel')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        $adds = [];
        if (!in_array('hType', $cols)) {
            $adds[] = "ADD `hType` VARCHAR(16) NOT NULL DEFAULT ''";
        }
        if (!in_array('hSubjectType', $cols)) {
            $adds[] = "ADD `hSubjectType` VARCHAR(64) NOT NULL DEFAULT ''";
        }
        if (!in_array('hSubjectID', $cols)) {
            $adds[] = "ADD `hSubjectID` INT(11) NULL DEFAULT NULL";
        }
        if (!in_array('hSubjectLabel', $cols)) {
            $adds[] = "ADD `hSubjectLabel` VARCHAR(200) NOT NULL DEFAULT ''";
        }
        if (count($adds) > 0) {
            self::$DB->query(
                "ALTER TABLE `history` " . implode(', ', $adds)
            );
        }

        return true;
    },
];

// 351
$this->schema[] = [
    // ADR 0022 decision 3: taskLog carries the image name, so imagingLog can
    // go.
    //
    // The two logs have always recorded the same events. TaskQueue calls
    // imageLog() and taskLog() in the same methods, on the same checkin and
    // the same completion, behind the same $imagingTask guard
    // (taskqueue.class.php:240/263 and :608/612), and nothing in
    // packages/service writes either. imagingLog held exactly one fact
    // taskLog did not: which image ran.
    //
    // Stored as a NAME rather than an id, for the reason schema 341 gave
    // logHostName the same treatment. The only route from a taskLog row to
    // its image is taskID -> tasks.taskImageID -> images.imageName, and both
    // hops break: Route::deletemass('host') cascades to tasks, and images get
    // deleted too. On the install this was written against, 9 of 56 taskLog
    // rows already had no surviving task.
    //
    // varchar(40) matches images.imageName, which is the widest value this
    // can ever copy. imagingLog's own ilImageName was varchar(64) -- wider
    // than its source and so wider than it needed to be.
    //
    // Guarded closure, same as 336/338/341/349/350: ADD COLUMN has no
    // IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and every column is
    // named in the probe so the installer's grant check still passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'taskLog' "
            . "AND `COLUMN_NAME` IN ('logImageName')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('logImageName', $cols)) {
            self::$DB->query(
                "ALTER TABLE `taskLog` "
                . "ADD `logImageName` VARCHAR(40) NOT NULL DEFAULT ''"
            );
        }

        return true;
    },
];

// 352
$this->schema[] = [
    // ADR 0022 decision 3, second half: imagingLog is retired.
    //
    // Step 351 gave taskLog the one column that made this table distinct.
    // Everything else it held, taskLog already had and in a more durable
    // form -- host id AND denormalized host name (341), a real state column,
    // createTime, createdBy, ip -- and nothing deletes taskLog rows, where
    // imagingLog deleted its own unfinished rows on the next attempt.
    //
    // The rows are NOT migrated. Backfilling them into taskLog needs a task
    // id imagingLog never stored; adding one purely to move rows out of a
    // table being dropped is work for nothing. The cost, accepted
    // deliberately: installs lose whatever imaging history they hold, and the
    // dashboard's images-per-day chart reads empty for the window predating
    // this step.
    //
    // The REST class goes with it and no shim replaces it. /api/imaginglog
    // 404s from here. No 1.6 release has ever shipped, so there is no
    // released API contract to break -- see ADR 0021's status. FogApi keeps
    // its own hardcoded copy of the class list rather than reading
    // system/openapi, so its copy needs syncing by hand.
    Schema::dropTable('imagingLog'),
];

// 353
$this->schema[] = [
    // Two "last seen" facts about a host, deliberately kept apart.
    //
    // hostLastPing   -- FOGPingHosts got a successful connect back.
    //                   "The machine is powered on and reachable."
    // hostLastCheckin -- the FOG client made a request.
    //                   "The agent is installed, running, and can reach us."
    //
    // They are NOT collapsed into a single hostLastSeen. The rollup is
    // MAX(the two) and can be derived in the view any time; the pair cannot
    // be recovered from the rollup. The case that costs support time is
    // exactly the disagreement -- a host that pings fine but stopped
    // checking in has a broken client, and one column erases that.
    //
    // Both NULL-able with a NULL default, never NOT NULL. A NOT NULL
    // DATETIME takes the zero date as its implicit default, and
    // FOGController::save() writing '' into it (the GH-1243/GH-1245 family)
    // turns "never seen" into 0000-00-00, which the display layer then has
    // to special-case forever. NULL means never, and validDate() already
    // renders that as an empty cell.
    //
    // No index on either. Both are write-hot -- hostLastCheckin takes a
    // write on every client module request -- and the only read that would
    // use one is an ORDER BY on the host grid, which is a filesort over a
    // table measured in thousands of rows. Index maintenance on every
    // check-in to save a filesort on a page view is the wrong trade.
    //
    // Guarded closure, same as 336/338/341/349/350/351: ADD COLUMN has no
    // IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and every column is
    // named in the probe so the installer's grant check still passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'hosts' "
            . "AND `COLUMN_NAME` IN ('hostLastPing', 'hostLastCheckin')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('hostLastPing', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostLastPing` DATETIME NULL DEFAULT NULL"
            );
        }
        if (!in_array('hostLastCheckin', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostLastCheckin` DATETIME NULL DEFAULT NULL"
            );
        }

        return true;
    },
    // The ping is not ICMP and never has been: Ping::execute() opens a TCP
    // connection and reports the errno. Port 445 and a 2 second timeout were
    // hardcoded, which made "is this host up?" mean "does this host accept
    // SMB?" -- permanently false for Linux hosts, for Windows with file
    // sharing off, and for anything behind a host firewall. Now that a
    // timestamp is being derived from the answer that guess had to become
    // an administrator's choice.
    //
    // The defaults are exactly the old hardcoded values, so an upgrade
    // changes nothing until someone edits them.
    //
    // FOG_SCHEMA is bumped in the same commit. An INSERT here without the
    // bump is silently skipped on every install -- the coarse gate never
    // sends the admin to the updater, so the precise one never runs.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('PINGHOSTPORT','The TCP port FOGPingHosts connects to when testing "
    . "whether a host is up. This is a TCP connect, not an ICMP echo, so the "
    . "port has to be one the host actually listens on. 445 (SMB) is the "
    . "historical default and suits a Windows estate; 22 is the usual choice "
    . "for Linux hosts.','445','Ping Host Settings'), "
    . "('PINGHOSTTIMEOUT','How many seconds to wait for a host to answer "
    . "before recording it as unreachable. Hosts are now tested in parallel, "
    . "so this is roughly the length of a whole ping cycle rather than a cost "
    . "paid per host.','2','Ping Host Settings')",
];
// 354
$this->schema[] = [
    // ADR 0022 decision 4: an index on each work item's START column.
    //
    // ActivityWindow bounds its union by a time range and orders by the
    // start, so without these "what ran between X and Y" is a full scan per
    // table -- five scans, on the tables that grow fastest on a busy server.
    // `tasks` has an index on `taskCheckIn` and none on `taskCreateTime`,
    // which is the column a window query reads; the other four have nothing
    // on their start column at all.
    //
    // Added WITH the helper rather than before it, which is what the ADR
    // asks for: an index nothing queries is maintenance cost on every insert
    // for no read, and all five of these tables are insert-hot.
    //
    // Plain single-column indexes, not covering ones. The union selects
    // several columns per row, so a covering index would be most of the
    // table; the job here is to FIND the rows in the range, not to answer
    // the whole query from the index.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353: ADD INDEX has no
    // IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and re-running one
    // is error 1061 rather than a no-op. Every table and column is named in
    // the probe so the installer's grant check still passes.
    function () {
        $wanted = [
            ['tasks', 'taskCreateTime', 'idx_taskCreateTime'],
            ['snapinJobs', 'sjCreateTime', 'idx_sjCreateTime'],
            ['snapinTasks', 'stCheckinDate', 'idx_stCheckinDate'],
            ['multicastSessions', 'msStartDateTime', 'idx_msStartDateTime'],
            ['fileDeleteQueue', 'fdqCreateDate', 'idx_fdqCreateDate'],
        ];
        foreach ($wanted as $spec) {
            list($table, $column, $index) = $spec;
            // Matched on the COLUMN, not on the index NAME. A server that
            // already indexes the column under a different name -- hand
            // tuned, or a later step folding it into a composite -- must not
            // get a second index on the same column, which is write cost for
            // no read. SEQ_IN_INDEX = 1 because only a LEADING column is
            // usable for a range scan on it.
            $have = self::$DB->query(
                "SELECT `INDEX_NAME` AS `i` "
                . "FROM `information_schema`.`STATISTICS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND `TABLE_NAME` = '" . $table . "' "
                . "AND `COLUMN_NAME` = '" . $column . "' "
                . "AND `SEQ_IN_INDEX` = 1"
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            if (count((array)$have) > 0) {
                continue;
            }
            self::$DB->query(
                "ALTER TABLE `" . $table . "` "
                . "ADD INDEX `" . $index . "` (`" . $column . "`)"
            );
        }

        return true;
    },
];
// 355
$this->schema[] = [
    // ADR 0020 phase 5. Three changes to `history` and `userTracking` that
    // the ADR held back until a full release cycle after phase 4, so that
    // an install upgrading through phase 4 had one release where readers
    // and writers were both known good before the storage moved.
    //
    // THAT GATE HAS BEEN WAIVED DELIBERATELY, by the maintainer, on
    // 2026-08-22. It cannot be satisfied as written: there is no 1.6
    // release, so "a full release cycle after phase 4" is a date that
    // never arrives on this branch, and phases 2 to 4 all landed in the
    // same unreleased line. The condition the gate was really protecting
    // -- do not move storage out from under a reader that still depends on
    // it -- is met, and met more strongly than the gate asked: phase 4's
    // readers build their sentence from the frame, and this step is the
    // one that removes the last reason they could not.
    //
    // 1. Backfill `userTracking.utHostName`.
    //
    // Same restricted UPDATE ... JOIN step 341 used for taskLog, and it is
    // restricted the same way and for the same two reasons: only rows whose
    // host still exists can be filled at all, and only rows whose copy is
    // still empty are touched, so a re-run is a no-op and a hand
    // correction is never overwritten. Rows whose host is already gone stay
    // empty -- the name is unrecoverable by the time the join fails, which
    // is the whole reason the column exists.
    //
    // 2. `UNIQUE (hText, hTime)` becomes `KEY (hTime)`.
    //
    // ADR 0020 decision 6. A unique index on the text is a lossy
    // deduplicator: two genuinely different events in the same second with
    // the same prose collapse into one row, and INSERT ... ON DUPLICATE KEY
    // UPDATE turns that into a silent overwrite rather than an error. Now
    // that a row carries a type and a subject id, "two identical rows in
    // one second" is a description of two real events.
    //
    // The replacement index is the one the table is actually queried by:
    // every reader of `history` orders by `hTime` DESC -- the activity
    // grid, the dashboard card, History_Report -- and none of them can use
    // a composite whose leading column is the text.
    //
    // The firehose that index was built for is bounded at the WRITER now
    // rather than by discarding rows afterward; see FOGBase::log().
    //
    // 3. `hText` VARCHAR(255) back to TEXT.
    //
    // 255 was never a product decision. Step 3305 narrowed a LONGTEXT to
    // varchar(255) for the sole purpose of making it indexable by the
    // unique key being dropped above, and truncation at 255 has been
    // silently cutting the tail off long entries ever since -- the failure
    // messages, which are the longest rows and the ones worth reading.
    // TEXT rather than back to LONGTEXT: 64KB is past any prose this
    // writes, and it is the smaller row format.
    //
    // Ordering inside the closure is load bearing. The index has to go
    // before the type change: a VARCHAR(255) in a unique index cannot
    // become TEXT while that index exists, because a TEXT column needs a
    // prefix length to be indexed at all.
    //
    // A closure rather than bare ALTERs because none of DROP INDEX, ADD
    // INDEX or MODIFY has IF [NOT] EXISTS on the versions this supports, so
    // a re-run converges by probing instead of erroring.
    function () {
        // 1. userTracking backfill.
        self::$DB->query(
            "UPDATE `userTracking` "
            . "JOIN `hosts` ON `hosts`.`hostID` = `userTracking`.`utHostID` "
            . "SET `userTracking`.`utHostName` = `hosts`.`hostName` "
            . "WHERE `userTracking`.`utHostName` = ''"
        );

        // 2. Swap the unique index for one on the column readers order by.
        $idx = self::$DB->query(
            "SELECT DISTINCT `INDEX_NAME` AS `i` "
            . "FROM `information_schema`.`STATISTICS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'history'"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $names = [];
        foreach ((array)$idx as $row) {
            if (isset($row['i'])) {
                $names[] = $row['i'];
            }
        }
        if (in_array('updateTime', $names)) {
            self::$DB->query(
                "ALTER TABLE `history` DROP INDEX `updateTime`"
            );
        }

        // 3. Widen the text, now that nothing indexes it.
        $type = self::$DB->query(
            "SELECT `DATA_TYPE` AS `t` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'history' AND `COLUMN_NAME` = 'hText'"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $now = '';
        foreach ((array)$type as $row) {
            if (isset($row['t'])) {
                $now = strtolower($row['t']);
            }
        }
        if ('text' !== $now) {
            // No DEFAULT: MySQL and MariaDB both refuse a literal default on
            // a TEXT column, which is why the varchar carried one and this
            // cannot. NOT NULL still holds, so the ORM's '' is unaffected.
            self::$DB->query(
                "ALTER TABLE `history` MODIFY `hText` TEXT NOT NULL"
            );
        }

        // The index the readers actually use. Added after the type change so
        // that a re-run which got as far as the DROP but not the MODIFY
        // still ends in the same place.
        if (!in_array('hTime', $names)) {
            self::$DB->query(
                "ALTER TABLE `history` ADD INDEX `hTime` (`hTime`)"
            );
        }

        return true;
    },
];
// 356
$this->schema[] = [
    // How the ping reached the host, alongside WHETHER it did.
    //
    // hostPingCode has carried the verdict since 1.5 and cannot carry this
    // as well. Once an ICMP echo is tried before the TCP connect, "the host
    // answered" has two causes -- an echo reply, or a connect that completed
    // -- and both would be recorded as errno 0. Nothing in the row could
    // then tell an administrator whether the service on PINGHOSTPORT is
    // actually running, which is the first thing anyone asks after "is it
    // up".
    //
    // varchar, NOT an enum, and that is a scar rather than a preference:
    // FOGController::save() has written '' into columns of every type for
    // years (the sql_mode/GH-1243 family), and '' is not a member of any
    // enum -- it lands as the enum error value and is invisible until
    // something reads it back. A varchar takes '' harmlessly and the
    // readers already treat empty as "unknown". It also leaves room for
    // 'icmp6' without an ALTER when ICMPv6 lands.
    //
    // NULL-able with a NULL default: every existing row predates the column
    // and genuinely has no answer, which is a different fact from "we
    // pinged and could not tell". The grid renders both as unknown; the
    // distinction costs nothing to keep and cannot be recovered later.
    //
    // No index. Same reasoning as hostLastPing in 353 -- written on every
    // cycle for every host, read only by a page that is already fetching
    // the row.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353/354: ADD COLUMN
    // has no IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and the
    // column is named in the probe so the installer's grant check still
    // passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'hosts' "
            . "AND `COLUMN_NAME` IN ('hostPingMethod')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('hostPingMethod', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostPingMethod` VARCHAR(10) NULL DEFAULT NULL"
            );
        }

        return true;
    },
    // An opt-out, not an opt-in. ICMP is the better probe -- it asks "is
    // this machine up" rather than "does this machine run the one service
    // we guessed at" -- so it is on by default and a server that wants the
    // old behavior turns it off.
    //
    // The reason to have the switch at all is that a fleet-wide echo sweep
    // every PINGHOSTSLEEPTIME seconds looks like a host sweep to an IDS,
    // and some sites will be told to stop doing it. Degradation is already
    // automatic when the socket cannot be opened; this is for the case
    // where it CAN and should not be.
    //
    // A 1/0 flag rather than a method name, so the configuration page
    // renders it as a checkbox from the existing map and validates it
    // without a new input type. Registered in fogconfigurationpage's
    // checkbox map in the same commit; a setting missing from that map
    // renders as a free-text box that invites typos.
    //
    // FOG_SCHEMA is bumped in the same commit. An INSERT here without the
    // bump is silently skipped on every install -- the coarse gate never
    // sends the admin to the updater, so the precise one never runs.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('PINGHOSTUSEICMP','Try a real ICMP echo request before falling back "
    . "to the TCP connect on PINGHOSTPORT. ICMP asks whether the machine is "
    . "up rather than whether it runs a particular service, so it reaches "
    . "hosts that answer no TCP port at all. Turn it off if a fleet-wide "
    . "echo sweep is unwelcome on your network; the TCP check then runs on "
    . "its own, exactly as before.','1','Ping Host Settings')",
];

// 357
$this->schema[] = [
    // taskLog joins the retention registry, and the setting that used to
    // point at imagingLog is removed rather than left to mislead.
    //
    // ADR 0022 decision 3 retired imagingLog and moved its one unique fact
    // (the image name) onto taskLog. The retention SETTING it had been given
    // one step earlier, in 347, went nowhere: FOG_IMAGINGLOG_RETENTION_DAYS
    // survived the DROP TABLE, is read by nothing, and -- because it is not
    // in Retention::settingKeys() -- is not even hidden behind `audit.manage`
    // the way the three real windows are. So it renders in Logging Settings
    // for anyone holding `settings.edit`, invites a number, accepts it, and
    // ages out nothing at all. A control that silently does nothing is worse
    // than an absent one, because an administrator who sets it believes the
    // question is answered.
    //
    // Meanwhile taskLog -- which is what imaging history IS now -- was aged
    // out by nothing, and it grows faster than the table it replaced: one row
    // per state transition rather than one per imaging run.
    //
    // THE OLD VALUE IS CARRIED ACROSS, NOT DISCARDED. An administrator who
    // typed 184 into the imagingLog box was answering exactly this question
    // about exactly this data, and the table underneath it changing name is
    // not a reason to make them answer twice. Everyone else has the 0 that
    // step 347 inserted, which stays 0 -- keep forever, ADR 0023 Decision 7,
    // on upgrade and new install alike.
    //
    // INSERT ... SELECT with an aggregate rather than a closure: MAX() over
    // an empty set yields one row of NULL, so the COALESCE supplies the '0'
    // default when the old key is absent (a 1.5 upgrade that never saw step
    // 347) without a second statement or a branch. INSERT IGNORE keeps it
    // idempotent if the step is ever replayed.
    //
    // Columns named, per the note on step 346: a step that does not name them
    // has broken the installer's grant probe twice.
    //
    // FOG_SCHEMA is bumped in the same commit. An INSERT here without the
    // bump is silently skipped on every install -- the coarse gate never
    // sends the admin to the updater, so the precise one never runs.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "SELECT 'FOG_TASKLOG_RETENTION_DAYS', "
    . "'How many days of task history to keep -- the per-task log rows behind "
    . "the Task Management log view, the FOS failure reports, and the "
    . "dashboard''s images-per-day graph. 0 keeps everything forever, which "
    . "is the default. Note that this window also bounds how far back that "
    . "graph can reach: a window shorter than the range you look at flattens "
    . "the far end of it. Replaces the old imaging log window, whose table "
    . "was retired; if you had set one, its value was carried over here. "
    . "Shortening this window is recorded in the audit trail before it takes "
    . "effect.', "
    . "COALESCE(MAX(`settingValue`), '0'), 'Logging Settings' "
    . "FROM `globalSettings` "
    . "WHERE `settingKey` = 'FOG_IMAGINGLOG_RETENTION_DAYS'",
    // Second, and only after the value above is safely copied. A row keyed on
    // settingKey, so this cannot take anything else with it.
    "DELETE FROM `globalSettings` "
    . "WHERE `settingKey` = 'FOG_IMAGINGLOG_RETENTION_DAYS'",
];

// 358
$this->schema[] = [
    // FOGRetentionRunner: the sweep gets a daemon that says what it does.
    //
    // Retention shipped inside FOGPluginRunner (step 329's daemon), above the
    // plugin-system gate but underneath PLUGINRUNNERGLOBALENABLED. That was
    // defensible on cost -- it was the only non-root periodic daemon FOG had,
    // and a ninth unit to run one DELETE an hour looked disproportionate --
    // and wrong on the thing that actually matters, which is what an
    // administrator reads.
    //
    // "FOGPluginRunner" says this daemon is for plugins. A site that installs
    // none switches it off, in the UI or by disabling the unit, and nothing
    // anywhere said that doing so also stopped the audit trail, the
    // administrative history, the host login records and the task log from
    // ever being pruned. Retention already HAS an off switch and it is per
    // table -- 0 days, keep forever. A second one, unrelated and named after
    // something else, is the kind of breakage that comes back as a bug report
    // against a feature working exactly as written.
    //
    // Categories match the other eight services so the runner appears
    // alongside them on the configuration page rather than in a section of
    // its own.
    //
    // THE SLEEP TIME IS THE SWEEP INTERVAL. There is no second schedule held
    // inside the loop the way the old RETENTION_INTERVAL was, so the setting,
    // the log and `systemctl status` agree, and lowering it genuinely raises
    // the catch-up rate -- one pass removes at most Retention::MAX_PER_PASS
    // rows per table, so a first sweep on a long-neglected table finishes in
    // proportionally fewer hours.
    //
    // RETENTIONGLOBALENABLED, not RETENTIONRUNNERGLOBALENABLED: the other
    // three keys name the runner because they configure the process (its log,
    // its tty, its cycle), and this one names the feature because that is what
    // it turns off. An administrator hunting for "how do I stop FOG deleting
    // my logs" is looking for the second word, not the first.
    //
    // Columns named, per the note on step 346: a step that does not name them
    // has broken the installer's grant probe twice.
    //
    // FOG_SCHEMA is bumped in the same commit. An INSERT here without the
    // bump is silently skipped on every install -- the coarse gate never
    // sends the admin to the updater, so the precise one never runs.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('RETENTIONGLOBALENABLED','This setting defines if retention should be "
    . "enabled or not. When it is on, the retention runner deletes rows older "
    . "than the windows set under Logging Settings -- and nothing else in FOG "
    . "deletes them, so turning this off means those tables grow forever. Note "
    . "that each window has its own off switch already: 0 days means keep "
    . "everything forever for that table. (Default is enabled)',"
    . "'1','FOG Linux Service Enabled'),"
    . "('RETENTIONRUNNERSLEEPTIME','The amount of time between retention "
    . "sweeps. This is the sweep interval itself, not a poll around one, so "
    . "lowering it makes a backlog clear proportionally sooner -- a single "
    . "pass removes at most 5000 rows from each table so that it never holds "
    . "locks for long. Value is in seconds. (Default 3600)',"
    . "'3600','FOG Linux Service Sleep Times'),"
    . "('RETENTIONRUNNERLOGFILENAME','Filename to store the retention runner "
    . "log file to. It is written to a retention/ subdirectory of the service "
    . "log path, because this service runs as the web user rather than root. "
    . "(Default fogretentionrunner.log)','fogretentionrunner.log',"
    . "'FOG Linux Service Logs'),"
    . "('RETENTIONRUNNERDEVICEOUTPUT','The tty to output to for the retention "
    . "runner service. (Default /dev/tty3)','/dev/tty3',"
    . "'FOG Linux Service TTY Output')",
];

// 359
$this->schema[] = [
    // ADR 0027: a Bearer token becomes its own credential, stored HASHED.
    //
    // users.uAPIToken stays exactly as it is -- plaintext, shown in the UI,
    // sent as fog-user-token beside fog-api-token. That pair was sound
    // because obtaining either half required an authenticated UI session, so
    // a leaked half is not a way in. What broke that property was GH-1324
    // making the same plaintext value a COMPLETE standalone credential, and
    // the two disclosures found immediately after (GH-1325, GH-1326) are
    // what a plaintext credential costs when any emitter forgets it.
    //
    // So this table is not a replacement for uAPIToken and there is no
    // migration: nothing existing changes, and Bearer stops accepting
    // uAPIToken once it accepts these instead. Each credential ends up with
    // exactly one spelling.
    //
    // atHash is SHA-256 of the token, unsalted, and that is deliberate
    // rather than an oversight. A salt defeats PRECOMPUTATION, which needs a
    // guessable input; these are 512-bit CSPRNG secrets with no dictionary
    // and no constructible table, so a salt buys nothing while costing the
    // ability to look a token up by hash at all -- with a per-row salt you
    // cannot compute the hash until you already know which row to check.
    // CHAR(64) because hex SHA-256 is always 64 characters.
    //
    // THE INVARIANT THAT DECISION RESTS ON: the token must stay
    // CSPRNG-generated and at least 256 bits. Shorten it, make it
    // user-choosable, or derive it from anything predictable, and salting
    // becomes necessary. See APIToken::generate().
    //
    // UNIQUE on atHash is integrity, not just an index: two rows sharing a
    // hash would make a token ambiguous about who it authenticates as.
    //
    // atLastUsed is NULL-able and only ever written with a real datetime.
    // FOG has a standing defect class where save() puts '' into a date
    // column and the cleared sql_mode accepts it as 0000-00-00; "never
    // used" has to stay distinguishable from "used at the epoch".
    "CREATE TABLE IF NOT EXISTS `apiTokens` ("
    . "`atID` INT NOT NULL AUTO_INCREMENT,"
    // The owner. Every token acts with this user's roles -- there are no
    // ownerless tokens, because FOG's authorization is entirely per-user and
    // an unowned token would need a parallel permission model AND would
    // blind auditLog, which keys every row off the acting user.
    . "`atUserID` INT NOT NULL DEFAULT 0,"
    // What the token is for, chosen by whoever created it. The point of N
    // tokens per user is that one integration can be rotated without
    // touching the others, which only works if you can tell them apart.
    . "`atName` VARCHAR(255) NOT NULL DEFAULT '',"
    . "`atHash` CHAR(64) NOT NULL DEFAULT '',"
    // Per-token kill switch, independent of users.uAllowAPI. That flag
    // governs fog-user-token and keeps doing exactly that; this one parks a
    // single integration. Kept as a flag rather than only offering delete so
    // a disabled row still says the token existed and when it was last used
    // -- a deleted one leaves auditLog referring to something nobody can
    // identify.
    . "`atEnabled` ENUM('0','1') NOT NULL DEFAULT '1',"
    . "`atCreatedTime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    . "`atCreatedBy` VARCHAR(255) NOT NULL DEFAULT '',"
    . "`atLastUsed` DATETIME NULL DEFAULT NULL,"
    . "PRIMARY KEY (`atID`),"
    . "UNIQUE KEY `atHash` (`atHash`),"
    . "KEY `atUserID` (`atUserID`)"
    . ") ENGINE=InnoDB "
    . "DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci "
    . "ROW_FORMAT=DYNAMIC",
];
// Guarded for the same reason as steps 312 and 314: working-1.6 and
// dev-branch number the same migration differently, so an install that has
// crossed between them may already carry the column.
$columnuAPIOnly = array_filter(
    (array)DatabaseManager::getColumns(
        'users',
        'uAPIOnly'
    )
);
// 360
$this->schema[] = count($columnuAPIOnly ?: []) ? [] : [
    // An account that holds API credentials and cannot sign in.
    //
    // The case is a service account: an unattended integration wants a
    // token and a set of roles, and nothing else. Until now the only way to
    // give it those was an ordinary account, which meant a working password
    // sitting on the login form for a credential nobody was ever going to
    // type -- and, if the password was left at whatever the creator chose,
    // an interactive way in that nobody was watching.
    //
    // A flag on the account rather than an absent password, because "no
    // password" is not a state FOG can hold: uPass is NOT NULL, an empty
    // hash fails password_verify() by accident rather than by rule, and
    // nothing stops the next admin setting one. This says what is meant.
    //
    // DEFAULT '0' so every existing account is unaffected: the flag has to
    // be set deliberately, and an upgrade must never take a login away from
    // somebody who had one.
    //
    // It is NOT users.uAllowAPI inverted. That flag governs whether the
    // fog-user-token header works for this account; this one governs
    // whether a browser session may be made for it. An account can be any
    // combination of the two, including API-only with uAllowAPI off, which
    // is a service account reachable only through an issued Bearer token.
    "ALTER TABLE `users` "
    . "ADD COLUMN `uAPIOnly` ENUM('0','1') NOT NULL DEFAULT '0'"
];
// Font Awesome 7: icon names stored as data (steps 361-367 below).
// The Font Awesome 7 migration renamed the icon *classes* in PHP and JS, but
// six task types and one task state carry their icon as DATA -- seeded by the
// steps above and rendered as `fas fa-<stored name>` by fog.task.list.js and
// the host and group task menus. Every one of the seven is an FA4 outline
// variant whose `-o` suffix FA7 dropped outright, so after the migration they
// resolve to nothing and the icon renders blank. The prefix is fine: `fa` is
// still the solid alias.
//
// Appended as steps rather than corrected in place. Editing the historical
// steps would fix a fresh install and leave every existing one broken, because
// an install that has already run them never replays them.
//
// One `$this->schema[] = []` per step, which is the ONLY thing that makes a
// statement run on an upgrade. Getting this wrong is not a no-op: FOG_SCHEMA
// must equal count($this->schema), so raising it without adding a step leaves
// every server permanently below it, and DatabaseManager::establish() then
// redirects every request to ?node=schema forever while the updater reports
// nothing to do. Two tests pin it: schema-gate compares FOG_SCHEMA to
// the highest `// N` label, and schema-upgrade-replay compares it to
// the number of elements that really exist, replaying the updater
// from every version a server can hold.
//
// Each is guarded on the old value so an administrator who has already picked
// their own icon for one of these keeps it.
// 361
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttIcon`='square-plus' "
    . "WHERE `ttID`=4 AND `ttIcon`='plus-square-o'",
];
// 362
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttIcon`='hard-drive' "
    . "WHERE `ttID`=5 AND `ttIcon`='hdd-o'",
];
// 363
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttIcon`='circle-arrow-down' "
    . "WHERE `ttID`=15 AND `ttIcon`='arrow-circle-o-down'",
];
// 364
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttIcon`='circle-arrow-up' "
    . "WHERE `ttID`=16 AND `ttIcon`='arrow-circle-o-up'",
];
// 365
$this->schema[] = [
    // Fast/Normal/Full Wipe are read as a set. Normal already holds
    // `hourglass-2`, which FA7 still resolves as an alias of hourglass-half,
    // and Full holds `hourglass`, so `hourglass-start` restores the
    // progression rather than just picking any surviving hourglass.
    "UPDATE `taskTypes` SET `ttIcon`='hourglass-start' "
    . "WHERE `ttID`=18 AND `ttIcon`='hourglass-o'",
];
// 366
$this->schema[] = [
    "UPDATE `taskTypes` SET `ttIcon`='flag' "
    . "WHERE `ttID`=22 AND `ttIcon`='flag-o'",
];
// 367
$this->schema[] = [
    "UPDATE `taskStates` SET `tsIcon`='bookmark' "
    . "WHERE `tsID`=1 AND `tsIcon`='bookmark-o'",
];
// 368
$this->schema[] = [
    // Store a boolean as a boolean.
    //
    // FOG has spelled its two-state columns `enum('0','1')` since the
    // beginning, and it has never spelled all of them that way: `sites`
    // .`siteCatchAll`, `auditLog`.`alRenderable`, `auditChange`.`acRedacted`
    // and `hosts`.`hostInfoLock` are already tinyint(1). Two conventions for
    // one idea, and the older of the two is the one with a trap in it.
    //
    // WHY THE ENUM IS ACTIVELY DANGEROUS, not merely inconsistent. An
    // integer written to an ENUM is a MEMBER INDEX, not a value, and these
    // enums are therefore off by one:
    //
    //     0  ->  index 0, the error value: refused under STRICT_TRANS_TABLES
    //     1  ->  index 1, which is the member '0'  -- i.e. FALSE
    //     2  ->  index 2, which is the member '1'  -- i.e. TRUE
    //
    // So `->set('isEnabled', 1)` means DISABLED if the value ever reaches
    // the server as an integer rather than a string. FOG survives that only
    // because PDODB binds every parameter as PDO::PARAM_STR; it is the
    // reason PDODB::_bind() may not use PDO::PARAM_BOOL, and the reason
    // Schema::defaultLiteral() exists. tinyint(1) has no such trap: 0 is
    // false and 1 is true whether it arrives as a string or an integer.
    // See fogproject#1361 and forum topic 18227.
    //
    // The migration itself -- and the reason it is three statements per
    // column rather than one ALTER -- is Schema::enumToTinyint().
    //
    // WHAT CHANGES FOR CALLERS. PDODB runs with ATTR_EMULATE_PREPARES off,
    // so mysqlnd hands back native types: these columns read back as the
    // integer 1 where they used to read back as the string '1', and the REST
    // API payload changes from "imageEnabled":"1" to "imageEnabled":1.
    // Deliberate -- see docs/adr/0028. Every reader in the tree tests
    // truthiness or casts with (string) first; both spellings are unchanged
    // by this. Downstream consumers that compare the JSON strictly against
    // "1" are not, which is why it lands in a beta.
    //
    // CORE TABLES ONLY. Each bundled plugin owns its own schema (ADR 0009),
    // so LDAPServers, OIDCProviders and location are converted by their own
    // steps in FOGProject/fog-plugins rather than reached into from here.
    //
    // NOT INCLUDED, deliberately: the char(1)/varchar(1) flags
    // (`tasks`.`taskShutdown`, `snapins`.`sReboot`, `hosts`.`hostUseAD`).
    // They look like the same thing and are not -- `hostUseAD` is tri-state,
    // with '' meaning "inherit" as a third value the form renders, so that
    // family needs a per-column reading rather than a sweep.
    function () {
        // The conversion itself is Schema::enumToTinyint() -- shared,
        // because the bundled LDAP, OIDC and location plugins each convert
        // their own columns from their own schema() (ADR 0009) and the
        // three-statement rule above must not be re-implemented per caller.
        return Schema::enumToTinyint(
            [
                'apiTokens' => ['atEnabled'],
                'hostMAC' => [
                    'hmIgnoreClient',
                    'hmIgnoreImaging',
                    'hmPending',
                    'hmPrimary'
                ],
                'hosts' => ['hostEnforce', 'hostPending'],
                'imageGroupAssoc' => ['igaPrimary'],
                'images' => ['imageEnabled', 'imageReplicate'],
                'multicastSessions' => ['msShutdown'],
                'nfsGroupMembers' => ['ngmGraphEnabled'],
                'powerManagement' => ['pmOndemand'],
                'pxeMenu' => ['pxeHotKeyEnable'],
                'snapinGroupAssoc' => ['sgaPrimary'],
                'snapinJobs' => ['sjAbortOnFail'],
                'snapins' => [
                    'sEnabled',
                    'sHideLog',
                    'sPackType',
                    'sReplicate',
                    'sShutdown'
                ],
                'tasks' => ['taskBypassBitlocker', 'taskWOL'],
                'taskTypes' => ['ttIsAdvanced'],
                'users' => ['uAllowAPI', 'uAPIOnly'],
            ]
        );
    },
];
// 369
$this->schema[] = [
    // hosts.hostArch -- the architecture last observed for this host.
    //
    // FOG has always known this and never kept it. iPXE posts `arch` on every
    // boot (default.ipxe sends ${buildarch} with the cpuid promotion that
    // lifts a 32-bit build on a 64-bit CPU to x86_64), IpxeBootMenu reads it to
    // pick the kernel, and then it is discarded. So nothing away from a live
    // boot -- a host edit page, a group kernel assignment, a deploy task --
    // could know what kind of machine it was dealing with, which is what
    // IpxeBootMenu::_fileFitsArch()'s docblock has been complaining about.
    //
    // NULL, not a default. An existing fleet starts unknown and fills in as
    // machines boot; defaulting to x86_64 would assert a fact nobody
    // observed, which is the exact error this column exists to prevent.
    //
    // VARCHAR, not ENUM: iPXE already builds for riscv64 and loong64, and an
    // ENUM makes each new architecture a schema migration. The valid set is
    // enforced in IpxeBootMenu where the whitelist already lives.
    //
    // Advisory only, and deliberately so. boot.php is unauthenticated by
    // necessity -- a booting NIC has no credential -- so this value is
    // attacker-controlled. The boot decision therefore keeps reading the
    // live request, never this column, and a poisoned value costs a wrong
    // warning on a form rather than a wrong kernel.
    //
    // No index. Written at most once per boot per host, read by pages that
    // are already fetching the row.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353/354/355: ADD
    // COLUMN has no IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and
    // the column is named in the probe so the installer's grant check still
    // passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'hosts' "
            . "AND `COLUMN_NAME` IN ('hostArch')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('hostArch', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostArch` VARCHAR(16) NULL DEFAULT NULL"
            );
        }

        return true;
    },
];
// 370
$this->schema[] = [
    // images.imageArch -- the architecture of the machine this image was
    // captured from.
    //
    // The half that makes the host column worth having. With both sides
    // recorded, Host::createImagePackage() can refuse a deploy that cannot
    // work; with only one, it can only ever guess.
    //
    // An image cannot discover its own architecture, and does not need to:
    // a capture requires the host to PXE boot, and step 369's column is
    // written on that same boot before FOS loads. So by the time
    // TaskQueue::_moveUpload() runs, the capturing host's architecture is
    // already recorded and the stamp is a copy, not an inference. No FOS
    // change and no new endpoint were needed for this.
    //
    // NULL for every image captured before this shipped. There is nothing to
    // backfill from -- an old image's architecture is genuinely unknown --
    // and the compatibility check treats unknown as "allow" precisely so
    // upgrading servers keep working.
    //
    // Separate step from 369 rather than one doing both tables, so a partial
    // failure leaves an unambiguous state.
    //
    // Guarded closure, same reasoning as 369 above.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'images' "
            . "AND `COLUMN_NAME` IN ('imageArch')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('imageArch', $cols)) {
            self::$DB->query(
                "ALTER TABLE `images` "
                . "ADD `imageArch` VARCHAR(16) NULL DEFAULT NULL"
            );
        }

        return true;
    },
];
// 371
$this->schema[] = [
    // images.imageSectorSize -- the LOGICAL sector size, in bytes, of the disk
    // this image was captured from. 512 or 4096; NULL when unknown.
    //
    // FOS has refused a cross-sector-size deploy since ADR-0005
    // (validateImageSectorSize in funcs.sh): partition-table and filesystem
    // geometry bake in the source disk's logical sector size and cannot be
    // translated, so deploying a 4Kn image onto a 512-byte disk produces an
    // unbootable machine. The server has never known any of this, so the
    // refusal only ever arrives at the client, minutes into a task, as a
    // failure rather than as something anyone could see beforehand.
    //
    // LOGICAL, not physical, and that is the whole distinction that matters.
    // 512n and 512e both present 512-byte logical sectors and are freely
    // interchangeable as deploy targets; only 4Kn differs. FOS reads the
    // source size with `blockdev --getss` (logical) and records it on the
    // `sector-size:` line of the sfdisk dump, and physical block size is
    // never persisted at capture -- so 512n and 512e are indistinguishable
    // here by construction, and separating them would buy nothing.
    //
    // NULL for every image captured before this, and for any image whose
    // sfdisk dump predates util-linux 2.35 and so carries no `sector-size:`
    // line at all. FOS treats that same absence as "allow the deploy rather
    // than guess"; nothing here may be stricter than the thing doing the
    // actual refusing.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353/354/369/370.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'images' "
            . "AND `COLUMN_NAME` IN ('imageSectorSize')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('imageSectorSize', $cols)) {
            self::$DB->query(
                "ALTER TABLE `images` "
                . "ADD `imageSectorSize` INT(11) NULL DEFAULT NULL"
            );
        }

        return true;
    },
];
// 372
$this->schema[] = [
    // Architecture becomes a row in a lookup table instead of a string on two
    // tables. `hosts.hostArch` and `images.imageArch` (steps 369/370) stored
    // the same three literals in two places with nothing constraining either,
    // which is not how the rest of this schema models a fixed set of values --
    // `imageTypes`, `imagePartitionTypes`, `os` and `taskTypes` are all
    // lookup tables, and the classes reach them through
    // databaseFieldClassRelationships. Two free-text columns holding an
    // enumeration is the odd one out, and it was only two steps old.
    //
    // `archIsAccess` is `taskTypes.ttIsAccess` wearing different values. There
    // it says whether a task type may be started from a host, from a group, or
    // from both; here it says whether an architecture may be picked on a host,
    // on an image, or on both. It is what makes the table worth normalizing
    // rather than just adding a CHECK constraint: an architecture FOS can
    // capture but no host in this fleet can boot (or the reverse) is a real
    // state, and the flag is where an admin says so.
    //
    // The columns stay NULLable and the seeds carry no host or image. NULL is
    // "not recorded" and it has to survive, because Architecture::canRun() treats
    // unknown on either side as ALLOWED -- every host that has not PXE booted
    // since step 369 and every image captured before step 370 reads NULL, and
    // refusing on absence would turn an upgrade into a fleet-wide outage.
    //
    // Order inside the closure is load-bearing: seed, then adopt any value the
    // fleet already holds that the seeds do not cover, then backfill the ids,
    // and only then drop the strings. The adopt pass is what makes the drop
    // lossless by construction rather than lossless because the whitelist in
    // IpxeBootMenu::_recordHostArch() happens to agree with the seed list today.
    function () {
        $DB = self::$DB;
        $cols = function ($table, $names) use ($DB) {
            $quoted = "'" . implode("','", $names) . "'";
            $have = $DB->query(
                "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '$table' "
                . "AND `COLUMN_NAME` IN ($quoted)"
            )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
            $found = [];
            foreach ((array)$have as $row) {
                if (isset($row['c'])) {
                    $found[] = $row['c'];
                }
            }
            return $found;
        };

        $DB->query(
            "CREATE TABLE IF NOT EXISTS `architectures` ("
            . "`archID` mediumint(9) NOT NULL AUTO_INCREMENT,"
            . "`archName` varchar(16) NOT NULL,"
            . "`archDescription` varchar(255) NOT NULL DEFAULT '',"
            . "`archIsAccess` enum('both','host','image') NOT NULL DEFAULT 'both',"
            . "PRIMARY KEY (`archID`),"
            . "UNIQUE KEY `archName` (`archName`)"
            . ') ENGINE=InnoDB AUTO_INCREMENT=1'
            . ' DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci'
            . ' ROW_FORMAT=DYNAMIC'
        );
        // The three iPXE reports. FOS says aarch64 where iPXE says arm64 and
        // amd64 where iPXE says x86_64; Architecture::normalizeName() folds
        // those onto these spellings rather than seeding both, so that a host
        // and an image describing the same machine cannot end up on two rows.
        $DB->query(
            "INSERT IGNORE INTO `architectures` "
            . "(`archID`, `archName`, `archDescription`, `archIsAccess`) "
            . "VALUES "
            . "(1, 'i386', '32-bit x86. Runs on 32-bit-only hardware and, "
            . "unchanged, on x86_64.', 'both'),"
            . "(2, 'x86_64', '64-bit x86, reported as amd64 by some tools.', "
            . "'both'),"
            . "(3, 'arm64', '64-bit ARM, reported as aarch64 by FOS.', 'both')"
        );

        $hostCols = $cols('hosts', ['hostArch', 'hostArchID']);
        $imageCols = $cols('images', ['imageArch', 'imageArchID']);

        if (!in_array('hostArchID', $hostCols)) {
            $DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostArchID` mediumint(9) NULL DEFAULT NULL"
            );
        }
        if (!in_array('imageArchID', $imageCols)) {
            $DB->query(
                "ALTER TABLE `images` "
                . "ADD `imageArchID` mediumint(9) NULL DEFAULT NULL"
            );
        }

        // Adopt, then map, then drop -- per side, because an upgrade that was
        // interrupted between the two ALTERs above is a state this has to
        // survive being re-run in.
        if (in_array('hostArch', $hostCols)) {
            $DB->query(
                "INSERT IGNORE INTO `architectures` (`archName`) "
                . "SELECT DISTINCT `hostArch` FROM `hosts` "
                . "WHERE `hostArch` IS NOT NULL AND `hostArch` <> ''"
            );
            $DB->query(
                "UPDATE `hosts` `h` "
                . "JOIN `architectures` `a` ON `a`.`archName` = `h`.`hostArch` "
                . "SET `h`.`hostArchID` = `a`.`archID` "
                . "WHERE `h`.`hostArch` IS NOT NULL AND `h`.`hostArch` <> ''"
            );
            $DB->query("ALTER TABLE `hosts` DROP COLUMN `hostArch`");
        }
        if (in_array('imageArch', $imageCols)) {
            $DB->query(
                "INSERT IGNORE INTO `architectures` (`archName`) "
                . "SELECT DISTINCT `imageArch` FROM `images` "
                . "WHERE `imageArch` IS NOT NULL AND `imageArch` <> ''"
            );
            $DB->query(
                "UPDATE `images` `i` "
                . "JOIN `architectures` `a` ON `a`.`archName` = `i`.`imageArch` "
                . "SET `i`.`imageArchID` = `a`.`archID` "
                . "WHERE `i`.`imageArch` IS NOT NULL AND `i`.`imageArch` <> ''"
            );
            $DB->query("ALTER TABLE `images` DROP COLUMN `imageArch`");
        }

        return true;
    },
];
// 373
$this->schema[] = [
    // Name the state rows too.
    //
    // Step 341 gave taskLog its own copy of the host and task type so a row
    // could still be read after its task was deleted, and backfilled only the
    // FOS report rows -- `WHERE logType <> 'state'`, explicitly. The reasoning
    // it gave was that state rows "are meaningless without their task anyway".
    // That turned out to be wrong twice over: Task Management's log pane shows
    // them, and the dashboard's per-event count reads them, so a state row
    // whose task is gone renders as a timestamp with two empty columns beside
    // it and counts as neither a capture nor a deploy.
    //
    // TaskLog::recordState() now writes all three on every transition, which
    // fixes the future. This is the past: it fills in the rows whose task is
    // STILL THERE, so they survive that task's eventual deletion instead of
    // joining the unnamed set later. Rows whose task is already gone cannot be
    // recovered by anything -- the name died with the host row that held it --
    // and the log pane renders those with an explicit placeholder rather than
    // a blank cell.
    //
    // Same restricted UPDATE ... JOIN as 341's, with the logType test
    // inverted, and the same `logHostID IS NULL` guard: a re-run is a no-op and
    // a row already written by recordState() is not touched.
    //
    // A closure rather than a bare statement for the same reason 341's backfill
    // is one: TaskLog::TYPE_STATE has to be resolved when the step RUNS, not
    // when this file is included. The schema updater includes schema.php in a
    // context that shims what a step needs (see tests/schema-upgrade-replay
    // .test.php) and the FOG classes are not part of it, so a constant read at
    // array-construction time is a fatal before any step has run.
    function () {
        self::$DB->query(
            "UPDATE `taskLog` "
            . "JOIN `tasks` ON `tasks`.`taskID` = `taskLog`.`taskID` "
            . "LEFT JOIN `hosts` "
            . "ON `hosts`.`hostID` = `tasks`.`taskHostID` "
            . "LEFT JOIN `taskTypes` "
            . "ON `taskTypes`.`ttID` = `tasks`.`taskTypeID` "
            . "SET `taskLog`.`logHostID` = `tasks`.`taskHostID`, "
            . "`taskLog`.`logHostName` = COALESCE(`hosts`.`hostName`, ''), "
            . "`taskLog`.`logTaskTypeName` = COALESCE(`taskTypes`.`ttName`, '') "
            . "WHERE `taskLog`.`logType` = '" . TaskLog::TYPE_STATE . "' "
            . "AND `taskLog`.`logHostID` IS NULL"
        );

        return true;
    },
];

// 374
$this->schema[] = [
    // Widen the stored pxeMenu param blocks past three NICs.
    //
    // The mac0/mac1/mac2 enumeration is not only in code -- six of these
    // blocks ship as `pxeMenu`.`pxeParams` DATA (step 182, and 129 before it),
    // and _menuOpt() emits whatever the row says verbatim. So fixing
    // _chainParams() and the installer's default.ipxe leaves every existing
    // site's menu items still posting at most three MACs, which is what made
    // a host registered under only its fourth NIC unfindable.
    //
    // Two additions per row, matching _chainParams():
    //   - macboot, ${netX/mac}, the NIC iPXE actually booted from. An
    //     ADDITION to mac0, not a replacement: netX is a pointer at one of
    //     net0..netN, so substituting it would drop net0 on a machine that
    //     booted off net1. boot.php unions every mac* field and array_unique()s
    //     the result, so the overlap costs nothing. It goes ABOVE the chain
    //     because the chain short-circuits to :bootme on the first absent
    //     interface, which on a single-NIC machine is net1.
    //   - net3..net7, so the enumeration reaches eight interfaces.
    //
    // Guarded on the row still matching what we shipped, byte for byte. These
    // rows are user-writable from iPXE Menu Customization, and a site that has
    // edited one has made a deliberate choice; an untouched row provably has
    // not. A customized row keeps its three NICs rather than losing the edit,
    // and re-running is a no-op because the old value no longer matches.
    //
    // A closure rather than seven literal statements: the old and the new
    // value differ by one line in the middle of a nine-line blob, and writing
    // both out per menu entry is fourteen near-identical paragraphs in which a
    // single wrong character silently means "match nothing, change nothing".
    function () {
        // pxeName => the boolean flag that row's params block carries.
        $menus = [
            'fog.deployimage' => 'qihost',
            'fog.quickdel' => 'delhost',
            'fog.keyreg' => 'keyreg',
            'fog.debug' => 'debugAccess',
            'fog.multijoin' => 'sessionJoin',
            'fog.advancedlogin' => 'advLog',
            'fog.approvehost' => 'approveHost',
        ];
        $head = "login\n"
            . "params\n"
            . 'param mac0 ${net0/mac}' . "\n"
            . 'param arch ${arch}' . "\n"
            . 'param username ${username}' . "\n"
            . 'param password ${password}' . "\n";
        $oldTail = 'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme'
            . "\n"
            . 'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme';
        $newTail = 'isset ${netX/mac} && param macboot ${netX/mac} ||';
        for ($nic = 1; $nic <= 7; $nic++) {
            $newTail .= "\n" . sprintf(
                'isset ${net%1$d/mac} && param mac%1$d ${net%1$d/mac}'
                . ' || goto bootme',
                $nic
            );
        }
        foreach ($menus as $pxeName => $flag) {
            $body = $head . sprintf('param %s 1', $flag) . "\n";
            self::$DB->query(
                'UPDATE `pxeMenu` SET `pxeParams` = :new '
                . 'WHERE `pxeName` = :name AND `pxeParams` = :old',
                [],
                [
                    ':new' => $body . $newTail,
                    ':name' => $pxeName,
                    ':old' => $body . $oldTail,
                ]
            );
        }

        return true;
    },
];

// 375
$this->schema[] = [
    // Green FOG's configuration outlives Green FOG itself.
    //
    // The module's server endpoint went with 565caa40c "Remove legacy client
    // stuff", which deleted the GF class and left service/greenfog.php calling
    // it -- a guaranteed fatal on every request until that file was deleted
    // too.
    //
    // Most of the surface was already inert, in two independent ways that
    // both predate this step: FOGBase::getGlobalModuleStatus() builds the
    // module list from a HARDCODED $services array that has never listed
    // greenfog, and the host, group and service-configuration pages each
    // carried it in a $notWhere exclusion list on top of that. So there has
    // been no per-host or per-group Green FOG toggle to tick for some time.
    //
    // One thing was not inert. FOG_CLIENT_GREENFOG_ENABLED still had a
    // globalSettings row, under its own "FOG Client - Green Fog" category,
    // so FOG Configuration rendered a real checkbox that saved a real value
    // that nothing has read since the module was removed. That is the same
    // failure FOG_PLUGINSYS_DIR was removed for in step 326: a setting that
    // lies is worse than no setting.
    //
    // The `modules` row and its moduleStatusByHost answers go with it. They
    // are what the exclusion lists existed to hide, and those lists are
    // removed in the same commit -- keeping the row would put Green FOG back
    // in the group and host module lists, which build their keys from this
    // table rather than from $services.
    //
    // Deleted rather than left as dead rows because the module cannot come
    // back: there is no GF class to restore. Ordered with the per-host
    // answers first, so no window exists where a row references a module id
    // nothing declares. Both are keyed on an exact value, so neither can take
    // anything else with it.
    //
    // msModuleID is a VARCHAR. Step 34 seeded these rows with the short name
    // and a later step rewrote them to the numeric id, so a server upgraded
    // across that boundary can hold either spelling; both are matched.
    //
    // The seed steps that created all three are deliberately NOT edited.
    // schema.php is a replay log, and step 326 set the precedent -- it
    // deletes FOG_PLUGINSYS_DIR while leaving the INSERT that creates it at
    // line 749 intact. A fresh install creates these and then removes them
    // one step later, which costs nothing and keeps the history readable.
    "DELETE FROM `moduleStatusByHost` "
    . "WHERE `msModuleID` IN ('5', 'greenfog')",
    "DELETE FROM `modules` WHERE `short_name` = 'greenfog'",
    "DELETE FROM `globalSettings` "
    . "WHERE `settingKey` = 'FOG_CLIENT_GREENFOG_ENABLED'",
];

// 376
$this->schema[] = [
    // hosts.hostSbState -- what this machine last told us about its own
    // Secure Boot posture, and when we heard it.
    //
    // ADR 0008 scopes the Secure Boot enrollment task to machines that are
    // NOT currently enforcing, because a machine that is enforcing will not
    // boot FOS and so cannot run the task that would make it trust us. Until
    // now there was no way to find those machines: an admin guessed, and a
    // wrong guess staged a certificate on a box where the task could never
    // run. This is the column that turns that constraint from something a
    // technician remembers into something the server can check.
    //
    // Reported by iPXE, on every PXE boot, not by FOS. That is the whole
    // point of siting it here: FOS runs when someone schedules a task, which
    // may be months away or never, whereas iPXE runs every time the machine
    // netboots. Same reasoning as step 369's hostArch, and the value arrives
    // on the same request.
    //
    // The six values are FOG\Boot\SecureBootState's constants, five of which
    // are FOS's own sbState() names taken verbatim
    // (usr/share/fog/lib/secureboot-funcs.sh) so the two reporters cannot
    // drift into two vocabularies for one fact:
    //
    //   unknown    nothing has reported yet -- server-side only
    //   nonefi     booted BIOS/CSM; Secure Boot is not a concept here
    //   noefivars  UEFI, but the variables could not be read
    //   setup      Setup Mode -- db is writable, enrollment is unattended
    //   enforcing  User Mode, Secure Boot ON  -- the task cannot run
    //   disabled   User Mode, Secure Boot OFF -- the ADR 0008 case
    //
    // setup and disabled are NOT collapsed into one "off". Turning Secure
    // Boot off leaves the platform key in place, so db still refuses a
    // write; only Setup Mode accepts one. fog.enrollsb branches on exactly
    // that, and it is the difference between an enrollment that finishes with
    // nobody at the keyboard and one that needs a human at MokManager.
    //
    // NULL, not a default, and read as "unknown". An existing fleet starts
    // unreported and fills in as machines boot. Defaulting to anything else
    // would assert a fact nobody observed -- and "disabled" is the dangerous
    // direction specifically, because it is the value that makes a host look
    // like a valid enrollment target.
    //
    // VARCHAR, not ENUM: ENUM makes every new state a schema migration, and
    // an int written to an ENUM is a member INDEX rather than a value, which
    // this project has been bitten by before. The valid set is enforced in
    // SecureBootState, where the parsing already lives.
    //
    // ADVISORY ONLY, and this is the constraint that matters most. boot.php
    // is unauthenticated by necessity -- a booting NIC has no credential --
    // so this value is attacker-controlled. Nothing may ever read it as a
    // security control: it exists for targeting, filtering and display. The
    // worst a spoofed "disabled" buys is a wasted task, and a spoofed
    // "enforcing" buys nothing at all. See ADR 0029.
    //
    // hostSbStateTime is stamped by the server from its own clock, never
    // taken from the request. A client-supplied timestamp on a
    // client-supplied observation is two lies for the price of one, and the
    // question this column answers -- "how stale is this?" -- is only
    // meaningful in the server's own time base.
    //
    // No index on either. Written at most once per boot per host, read by
    // pages that are already fetching the row -- the same trade as
    // hostLastPing in 353 and hostArch in 369.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353/354/355/369: ADD
    // COLUMN has no IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and
    // every column is named in the probe so the installer's grant check
    // still passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'hosts' "
            . "AND `COLUMN_NAME` IN ('hostSbState', 'hostSbStateTime')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('hostSbState', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostSbState` VARCHAR(16) NULL DEFAULT NULL"
            );
        }
        if (!in_array('hostSbStateTime', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostSbStateTime` DATETIME NULL DEFAULT NULL"
            );
        }

        return true;
    },
];

// 377
$this->schema[] = [
    // hosts.hostSbEnrolled / hostSbEnrollCert / hostSbEnrollVia -- the record
    // of an enrollment having been PERFORMED.
    //
    // The other half of 376, and a different kind of fact. 376 is an
    // observation: the machine said what it was, and nobody may edit it.
    // This is a record of an action, and it IS editable, because one of the
    // three ways a certificate gets enrolled cannot write it itself.
    //
    //   db      fog.enrollsb wrote the platform's db in Setup Mode. Finished
    //           on the machine, nobody at the keyboard.
    //   mok     a MOK request was staged AND has since been confirmed.
    //   manual  a technician enrolled it from a USB stick. Hand-entered;
    //           there is no path by which FOG could learn this.
    //
    // and the fourth value, which is why this is not a boolean:
    //
    //   mok-pending  a MOK request was staged and NOT yet confirmed.
    //
    // That distinction is load-bearing and easy to lose. fog.enrollsb has
    // three exits -- already-trusted, enrolled-via-db, and staged-a-MOK --
    // and all three currently end with the same argument-free completion
    // POST, so the server cannot tell them apart. Writing "enrolled" on a
    // staged MOK would be a lie an admin acts on: they would turn Secure
    // Boot on in firmware and the machine would stop booting, because
    // nothing has confirmed the key at MokManager yet. So FOS reports which
    // branch it took, and until it does, this column stays NULL rather than
    // guessing.
    //
    // hostSbEnrollCert is the SHA-256 of the enrolled certificate, colon-
    // formatted and upper case -- 95 characters. It is here because "was
    // this machine ever enrolled" is not the question an admin has. The
    // question is "does this machine trust the certificate I am serving
    // today", and an enrollment date alone goes stale in silence when the
    // certificate rotates: FOG has PKI zones, a multi-server CA, and certs
    // that expire.
    //
    // Both sides of that comparison already exist and already agree, which
    // is why this costs one column and no new computation.
    // FOGConfigurationPage::secureBoot() renders
    // strtoupper(implode(':', str_split(hash_file('sha256', $certfile), 2)))
    // and FOS's sbCertFingerprint() is `sha256sum | toupper | colon-split`
    // over the same DER file. The check is string equality.
    //
    // Editable, deliberately, and therefore carrying even less authority
    // than 376's observation -- a human typed it. Same rule applies: display
    // and targeting only, never a security control. See ADR 0029.
    //
    // Last write wins when sources disagree, with no precedence rule. A
    // technician correcting a wrong machine-written record is legitimate,
    // and a rule that let the machine outrank the human would create a value
    // nobody could fix. What makes that safe is showing provenance rather
    // than arbitrating it: hostSbEnrollVia is rendered next to the date, so
    // "enrolled 2026-03-14 (entered by hand)" and "(reported by task)" are
    // visibly different claims.
    //
    // VARCHAR(95) sized to the value, not rounded up: 32 bytes as two hex
    // digits each plus 31 separators. A wider column would silently accept
    // something that is not a SHA-256 fingerprint.
    //
    // No index. Read on the host page and in the grid, both of which already
    // have the row.
    //
    // Separate step from 376 rather than one doing both halves, so a partial
    // failure leaves an unambiguous state -- same reasoning as 369/370.
    //
    // Guarded closure, same as 376 above.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'hosts' "
            . "AND `COLUMN_NAME` IN "
            . "('hostSbEnrolled', 'hostSbEnrollCert', 'hostSbEnrollVia')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('hostSbEnrolled', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostSbEnrolled` DATETIME NULL DEFAULT NULL"
            );
        }
        if (!in_array('hostSbEnrollCert', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostSbEnrollCert` VARCHAR(95) NULL DEFAULT NULL"
            );
        }
        if (!in_array('hostSbEnrollVia', $cols)) {
            self::$DB->query(
                "ALTER TABLE `hosts` "
                . "ADD `hostSbEnrollVia` VARCHAR(16) NULL DEFAULT NULL"
            );
        }

        return true;
    },
];

// 378
$this->schema[] = [
    // auditChange.acSubjectLabel -- which OBJECT a change row is about.
    //
    // A change row said WHICH FIELD moved and never WHICH OBJECT: it carries
    // acSubjectType and acSubjectID, so the modal printed `setting#496`. For
    // most models the field name carries enough of the sense to survive that
    // -- `name`, `mac`, `ip` -- but globalSettings has exactly one editable
    // column, so every settings edit in the install read `value | 1 | 0` and
    // named nothing at all. The identity of a setting is its key, and the
    // key was in hand at write time and thrown away.
    //
    // Denormalized rather than resolved when the page is read, which is the
    // decision `history` already made for the same reason one table over:
    // hSubjectLabel, ADR 0020 phase 3, docblocked "so the row still names
    // its subject after the subject is deleted". A join goes blank the day
    // the host is removed, which is the day the row matters most.
    //
    // VARCHAR(200) matching hSubjectLabel exactly, and sized the same way --
    // to the widest name column a subject can have (snapins.sName
    // varchar(200)), not to hosts.hostName varchar(16).
    //
    // No index. It is read only alongside the rows for one header, which
    // acAuditID already indexes.
    //
    // Additive and inert: existing rows keep '' and the modal falls back to
    // the type#id it prints today. Guarded closure, same shape as 376/377.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'auditChange' "
            . "AND `COLUMN_NAME` = 'acSubjectLabel'"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('acSubjectLabel', $cols)) {
            self::$DB->query(
                "ALTER TABLE `auditChange` "
                . "ADD `acSubjectLabel` VARCHAR(200) NOT NULL DEFAULT ''"
            );
        }

        return true;
    },
];

// 379
$this->schema[] = [
    // taskLog.createTime -- the column every windowed report bounds on, and
    // the one index ADR 0022 step 354 did not add.
    //
    // Step 354 indexed the START column of all five WORK ITEM tables so
    // ActivityWindow could find a range without scanning. taskLog is not a
    // work-item table -- it is the EVENT table, one row per state transition
    // -- so it was correctly out of that step's scope and consequently has
    // never had an index on its time column at all. It ships with
    // `PRIMARY KEY (id)` and `KEY taskID (taskID)` and nothing else.
    //
    // That was survivable while nothing read it by time. It no longer is.
    // ADR 0022 decision 3 retired imagingLog and made taskLog the record of
    // what was imaged, so DashboardPage::get30day() already scans the whole
    // table on every dashboard load -- `WHERE createTime BETWEEN ? AND ?`
    // with no index to find the range with -- and ADR 0030 puts every
    // report rollup on the same bound. taskLog is the fastest-growing table
    // on a busy server and the one whose retention window is longest, so
    // the scan gets worse exactly where the reports get used.
    //
    // Single column, not composite. The rollups filter further --
    // `logImageName <> ''`, `taskStateID <> canceled` -- but neither is an
    // equality, so neither is usable as a leading or trailing key part. The
    // range on createTime is what has to be found; the rest is a filter over
    // the rows it returns.
    //
    // Guarded closure, same shape as 354 and 376-378: ADD INDEX has no
    // IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and re-running one
    // is error 1061 rather than a no-op. Matched on the COLUMN rather than
    // the index name, and on SEQ_IN_INDEX = 1, for the reason 354 gives --
    // a server that already leads an index with this column, hand-tuned or
    // folded into a later composite, must not get a second one on it.
    function () {
        $have = self::$DB->query(
            "SELECT `INDEX_NAME` AS `i` "
            . "FROM `information_schema`.`STATISTICS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` = 'taskLog' "
            . "AND `COLUMN_NAME` = 'createTime' "
            . "AND `SEQ_IN_INDEX` = 1"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (count((array)$have) < 1) {
            self::$DB->query(
                "ALTER TABLE `taskLog` "
                . "ADD INDEX `idx_taskLogCreateTime` (`createTime`)"
            );
        }

        return true;
    },
];

// 380
$this->schema[] = [
    // Type parity between five child columns and the parents they point at.
    //
    // A foreign key requires the two columns to be the SAME type -- not a
    // compatible one, the same one. MariaDB refuses a mediumint child
    // against an int parent with errno 150, which surfaces as
    // "Can't create table ... (errno: 150)" and says nothing about which
    // column it means. Five of the 87 relationships in
    // commons/schema-constraints.php cannot be declared until this lands,
    // so it goes first and it touches no data.
    //
    // Four of them widen the CHILD, which is the accumulation of two
    // different eras of id column: the association tables were written when
    // mediumint was the house default and the tables they point at were
    // later widened to int without their references following.
    //
    //   snapinGroupAssoc.sgaSnapinID       -> snapins.sID       int(11)
    //   snapinGroupAssoc.sgaStorageGroupID -> nfsGroups.ngID    int(11)
    //   imageGroupAssoc.igaImageID         -> images.imageID    int(11)
    //   imageGroupAssoc.igaStorageGroupID  -> nfsGroups.ngID    int(11)
    //
    // The fifth runs the other way: moduleStatusByHost.msModuleID is
    // int(11) and modules.id is mediumint(9). The PARENT is widened, not
    // the child narrowed. Both directions produce a legal constraint and
    // the trial ran the narrowing successfully, but narrowing is only safe
    // while no value exceeds 8388607 and it would rebuild a 56,000-row
    // table to save nothing; widening a 13-row table of ids is additive,
    // unconditionally reversible in the sense that matters, and leaves the
    // id column no narrower than anything that references it. Nothing else
    // in the schema references modules.id.
    //
    // Guarded on DATA_TYPE so a server that already matches -- a fresh
    // install, or a re-run -- does no work. MODIFY COLUMN rebuilds the
    // table, so it is not free even when it changes nothing.
    //
    // See docs/development/foreign-keys.md, Phase C item 1, and ADR 0031.
    function () {
        $widen = [
            ['snapinGroupAssoc', 'sgaSnapinID', 'int', 'INT(11) NOT NULL'],
            ['snapinGroupAssoc', 'sgaStorageGroupID', 'int', 'INT(11) NOT NULL'],
            ['imageGroupAssoc', 'igaImageID', 'int', 'INT(11) NOT NULL'],
            ['imageGroupAssoc', 'igaStorageGroupID', 'int', 'INT(11) NOT NULL'],
            ['modules', 'id', 'int', 'INT(11) NOT NULL AUTO_INCREMENT'],
        ];

        foreach ($widen as $w) {
            list($table, $column, $want, $definition) = $w;
            $row = self::$DB->query(
                "SELECT `DATA_TYPE` AS `t` "
                . "FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND `TABLE_NAME` = '$table' "
                . "AND `COLUMN_NAME` = '$column'"
            )->fetch(\PDO::FETCH_ASSOC)->get();
            if (!is_array($row) || !isset($row['t'])) {
                // No such table or column on this server. Nothing to do --
                // a later step or a fresh install builds it correctly.
                continue;
            }
            if (strtolower((string)$row['t']) === $want) {
                continue;
            }
            self::$DB->query(
                "ALTER TABLE `$table` "
                . "MODIFY COLUMN `$column` $definition"
            );
        }

        return true;
    },
];

// 381
$this->schema[] = [
    // The orphan sweep. THE ONLY DESTRUCTIVE STEP IN THIS SERIES.
    //
    // Nine years of PHP-only referential integrity leave rows whose parent
    // is gone. ADD CONSTRAINT refuses on them at errno 1452 -- "Cannot add
    // or update a child row" -- and reports the constraint, not the rows,
    // so the constraint steps that follow cannot land until the ground is
    // cleared. Doing it here rather than inside a constraint step is
    // deliberate: a DELETE and an ALTER in one step means a 1452 arrives
    // with nothing to say which of the two caused it, and the DELETE is
    // the one thing in the whole series that cannot be undone.
    //
    // WHAT IT DELETES, AND WHY ONLY THIS. Every relationship below is
    // classified CASCADE in commons/schema-constraints.php, meaning the
    // child row has no existence independent of its parent -- a junction
    // row, or a 1:1 satellite. If the parent were deleted today the
    // constraint would delete the child; the parent is already gone, so
    // the row is one the system has already decided it does not keep.
    // Nothing else is swept:
    //
    //   - RESTRICT relationships are configuration references. An orphan
    //     there means a host points at a missing architecture, not that
    //     the host is junk, and deleting it would be catastrophic. Those
    //     are handled by steps 7 and 8 of the series, by converting the
    //     `0` sentinel and repointing.
    //   - SET NULL relationships likewise: the fix is to null the column,
    //     which needs the column to be nullable first.
    //   - audit and history rows are NEVER swept. ADR 0021 stores the
    //     subject's name on the row precisely so the trail outlives the
    //     subject; a sweep here would delete the record of the deletion.
    //   - plugin tables sweep at plugin install, not here. Core must not
    //     delete rows belonging to a plugin that may not be installed.
    //
    // ORDER IS LOAD BEARING, and this is the part a rehearsal found rather
    // than reasoning. Deleting multicastSessionsAssoc's orphans BEFORE the
    // orphaned tasks strands a fresh set behind it, because deleting the
    // task orphans the association row that pointed at it. Same inversion
    // for snapinTasks and snapinJobs. On a 5000-host fixture that took an
    // otherwise clean run to 85 constraints added / 2 refused, both 1452 on
    // rows the sweep itself had just created. The depth is COMPUTED from
    // the list below rather than hand-sorted, because a hand-sorted list is
    // exactly what an editor gets silently wrong.
    //
    // WHAT IT RECORDS. A per-table count to the PHP error log, so the
    // upgrade transcript carries it, AND one auditLog row with the totals,
    // so an admin can find it afterward from the UI. That is ADR 0021's
    // whole point: a one-time destructive act during an upgrade is exactly
    // the thing that must not be silent. Neither write can fail the step --
    // Audit::record() does not throw and returns false on failure.
    //
    // LEFT JOIN rather than NOT IN: NOT IN evaluates to UNKNOWN for every
    // row if the subquery yields a single NULL, which deletes nothing and
    // reports success. No CASCADE relationship carries a `0` sentinel, so
    // there is no value to exclude here; the sentinel columns are all
    // RESTRICT or SET NULL and belong to later steps.
    //
    // See docs/development/foreign-keys.md, Phase C item 5, and ADR 0031.
    function () {
        $rels = [
            ['groupMembers', 'gmHostID', 'hosts', 'hostID'],
            ['groupMembers', 'gmGroupID', 'groups', 'groupID'],
            ['hostMAC', 'hmHostID', 'hosts', 'hostID'],
            ['snapinAssoc', 'saHostID', 'hosts', 'hostID'],
            ['snapinAssoc', 'saSnapinID', 'snapins', 'sID'],
            ['snapinGroupAssoc', 'sgaSnapinID', 'snapins', 'sID'],
            ['snapinGroupAssoc', 'sgaStorageGroupID', 'nfsGroups', 'ngID'],
            ['imageGroupAssoc', 'igaImageID', 'images', 'imageID'],
            ['imageGroupAssoc', 'igaStorageGroupID', 'nfsGroups', 'ngID'],
            ['printerAssoc', 'paHostID', 'hosts', 'hostID'],
            ['printerAssoc', 'paPrinterID', 'printers', 'pID'],
            ['moduleStatusByHost', 'msHostID', 'hosts', 'hostID'],
            ['moduleStatusByHost', 'msModuleID', 'modules', 'id'],
            ['multicastSessionsAssoc', 'msID', 'multicastSessions', 'msID'],
            ['multicastSessionsAssoc', 'tID', 'tasks', 'taskID'],
            ['siteHostMembers', 'shmSiteID', 'sites', 'siteID'],
            ['siteHostMembers', 'shmHostID', 'hosts', 'hostID'],
            ['siteGroupMembers', 'sgmSiteID', 'sites', 'siteID'],
            ['siteGroupMembers', 'sgmGroupID', 'groups', 'groupID'],
            ['siteUserMembers', 'sumSiteID', 'sites', 'siteID'],
            ['siteUserMembers', 'sumUserID', 'users', 'uId'],
            ['siteUserGroupMembers', 'sugmSiteID', 'sites', 'siteID'],
            ['siteUserGroupMembers', 'sugmUserGroupID', 'userGroups', 'ugID'],
            ['siteRoleGrants', 'srgSiteID', 'sites', 'siteID'],
            ['siteRoleGrants', 'srgRoleID', 'roles', 'rID'],
            ['siteUserGroupGrants', 'suggSiteID', 'sites', 'siteID'],
            ['siteUserGroupGrants', 'suggGroupID', 'userGroups', 'ugID'],
            ['roleUserAssoc', 'ruaRoleID', 'roles', 'rID'],
            ['roleUserAssoc', 'ruaUserID', 'users', 'uId'],
            ['roleUserGroupAssoc', 'rugRoleID', 'roles', 'rID'],
            ['roleUserGroupAssoc', 'rugGroupID', 'userGroups', 'ugID'],
            ['rolePermissions', 'rpRoleID', 'roles', 'rID'],
            ['userGroupMembers', 'ugmGroupID', 'userGroups', 'ugID'],
            ['userGroupMembers', 'ugmUserID', 'users', 'uId'],
            ['inventory', 'iHostID', 'hosts', 'hostID'],
            ['hostScreenSettings', 'hssHostID', 'hosts', 'hostID'],
            ['hostAutoLogOut', 'haloHostID', 'hosts', 'hostID'],
            ['powerManagement', 'pmHostID', 'hosts', 'hostID'],
            ['greenFog', 'gfHostID', 'hosts', 'hostID'],
            ['apiTokens', 'atUserID', 'users', 'uId'],
            ['userAuths', 'uaUserID', 'users', 'uId'],
            // nfsGroupMembers.ngmGroupID is deliberately NOT swept.
            //
            // Every other entry on this list is a row with no meaning
            // without its parent. A storage node is the opposite: it holds
            // its own hostname, credentials, paths, interface, bandwidth
            // limit and enable flag, none of it recoverable from the group.
            // A node whose group is missing is a BROKEN row -- a node always
            // belongs to a group -- but the repair is to assign it to one,
            // which only an administrator can choose, not to delete a real
            // node's configuration on their behalf.
            //
            // Tom's own server carries exactly such a row (`fognode1.lan`,
            // enabled, group 0). The RESTRICT constraint the map declares
            // for this column is refused while it exists, and names it in
            // the log. That is the intended outcome: loud, actionable, and
            // destructive of nothing.
            ['tasks', 'taskHostID', 'hosts', 'hostID'],
            ['snapinJobs', 'sjHostID', 'hosts', 'hostID'],
            ['snapinTasks', 'stJobID', 'snapinJobs', 'sjID'],
            ['snapinTasks', 'stSnapinID', 'snapins', 'sID'],
        ];

        // Which tables exist here. A server can be missing one -- a plugin
        // table shares no namespace with these, but a table retired by a
        // later ADR would still be named in the frozen list above, and a
        // DELETE against a missing table is a fatal, not a skip.
        $present = [];
        $rows = self::$DB->query(
            "SELECT `TABLE_NAME` AS `t` FROM `information_schema`.`TABLES` "
            . "WHERE `TABLE_SCHEMA` = DATABASE()"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ((array)$rows as $row) {
            if (isset($row['t'])) {
                $present[strtolower((string)$row['t'])] = true;
            }
        }

        $rels = array_values(
            array_filter(
                $rels,
                function ($r) use ($present) {
                    return isset($present[strtolower($r[0])])
                        && isset($present[strtolower($r[2])]);
                }
            )
        );

        // Dependency depth per CHILD TABLE. A table whose parent is itself
        // swept must run after it, or the parent's sweep strands rows the
        // child's sweep has already passed. Fixpoint rather than a single
        // pass so a longer chain than today's two would still order right;
        // capped so a cycle cannot hang an upgrade.
        $childTables = [];
        $depth = [];
        foreach ($rels as $r) {
            $childTables[strtolower($r[0])] = true;
            $depth[strtolower($r[0])] = 1;
        }
        for ($pass = 0; $pass < 10; $pass++) {
            $moved = false;
            foreach ($rels as $r) {
                $parent = strtolower($r[2]);
                $child = strtolower($r[0]);
                if (!isset($childTables[$parent]) || $parent === $child) {
                    continue;
                }
                if ($depth[$child] < $depth[$parent] + 1) {
                    $depth[$child] = $depth[$parent] + 1;
                    $moved = true;
                }
            }
            if (!$moved) {
                break;
            }
        }
        usort(
            $rels,
            function ($a, $b) use ($depth) {
                return $depth[strtolower($a[0])] <=> $depth[strtolower($b[0])];
            }
        );

        $perTable = [];
        $total = 0;
        foreach ($rels as $r) {
            list($child, $column, $parent, $pcolumn) = $r;
            self::$DB->query(
                "DELETE `c` FROM `$child` `c` "
                . "LEFT JOIN `$parent` `p` ON `c`.`$column` = `p`.`$pcolumn` "
                . "WHERE `p`.`$pcolumn` IS NULL"
            );
            $n = (int)self::$DB->affectedRows();
            if ($n < 1) {
                continue;
            }
            if (!isset($perTable[$child])) {
                $perTable[$child] = 0;
            }
            $perTable[$child] += $n;
            $total += $n;
        }

        if ($total < 1) {
            return true;
        }

        $parts = [];
        foreach ($perTable as $table => $n) {
            error_log(
                sprintf(
                    'FOG orphan sweep: deleted %d row(s) from `%s` whose '
                    . 'parent record no longer existed. They were already '
                    . 'unreachable -- nothing read them and nothing could '
                    . 'have.',
                    $n,
                    $table
                )
            );
            $parts[] = sprintf('%s: %d', $table, $n);
        }

        Audit::record(
            [
                'type' => 'schema.orphan.sweep',
                'subjectType' => 'schema',
                'subjectID' => 0,
                'subjectLabel' => _('Foreign key preparation'),
                'outcome' => Audit::ALLOWED,
                'affectedCount' => $total,
                'renderable' => 1,
                'text' => sprintf(
                    /* translators: %1$d row count, %2$s per-table breakdown */
                    _('Removed %1$d association and satellite row(s) whose '
                    . 'parent record no longer existed, so that foreign key '
                    . 'constraints could be declared. Per table: %2$s'),
                    $total,
                    implode(', ', $parts)
                ),
            ]
        );

        return true;
    },
];

// 382
$this->schema[] = [
    // ADR 0031 group 1: host-owned junctions and satellites.
    //
    // The first constraints FOG has ever declared. 14 of them across 10
    // tables -- the association rows and 1:1 satellites that belong to a
    // host, plus the other side of each junction so a deleted group,
    // snapin, printer or module cannot leave one either:
    //
    //   groupMembers        gmHostID -> hosts,  gmGroupID -> groups
    //   hostMAC             hmHostID -> hosts
    //   snapinAssoc         saHostID -> hosts,  saSnapinID -> snapins
    //   printerAssoc        paHostID -> hosts,  paPrinterID -> printers
    //   moduleStatusByHost  msHostID -> hosts,  msModuleID -> modules
    //   inventory           iHostID -> hosts
    //   hostScreenSettings  hssHostID -> hosts
    //   hostAutoLogOut      haloHostID -> hosts
    //   powerManagement     pmHostID -> hosts
    //   greenFog            gfHostID -> hosts
    //
    // ALL CASCADE, AND NOTHING AN ADMIN CAN SEE CHANGES. Route::deletemass()
    // already deletes every one of these when a host goes; the constraint is
    // that statement made true in the database instead of remembered in one
    // function. What it buys is the path that FORGETS -- an API delete, a
    // plugin, a future call site -- which is the entire argument of ADR 0031.
    //
    // The declaration itself lives in commons/schema-constraints.php, which
    // carries all 87 relationships with an `enabled` flag; this group's 14
    // are the ones flipped on. The step does not repeat them, because a
    // second copy is a second thing to get wrong.
    //
    // WHY A STEP AT ALL, when SchemaReconciler already applies constraints
    // after every update run. Because that run has to HAPPEN: the admin only
    // reaches the schema page while `mySchema < FOG_SCHEMA`, so a group that
    // is only a flag flip in a PHP file reaches a server that is already up
    // to date exactly never. An indexed step is what moves the count, and
    // having moved it, it may as well be the thing that does the work and
    // says so in the replay log. The reconcile that follows a few lines
    // later in SchemaUpdaterPage::update() then finds them present and plans
    // nothing -- it is the standing repair for a constraint dropped by hand
    // or lost to a restore, not the mechanism that lands one.
    //
    // Steps 380 and 381 are the precondition: 380 made two of these column
    // pairs the same type, and 381 removed the orphans that would otherwise
    // refuse at 1452.
    //
    // NEVER FAILS THE UPDATE. applyConstraints() collects a refusal into
    // SchemaReconciler::constraintFailures() and logs it with a pointer at
    // bin/fk-orphan-scan.php rather than returning an error, because
    // aborting here would strand a server on ?node=schema over data that is
    // otherwise intact. A missing constraint means FOG is still relying on
    // deletemass() alone, which is where it has been for a decade.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(1);

        return true;
    },
];

// 383
$this->schema[] = [
    // ADR 0031 group 2: identity -- users, roles, user groups and sites.
    //
    // 21 constraints across 12 tables. Every one CASCADE, and every one on
    // a table whose rows describe a RELATIONSHIP between two identity
    // objects rather than an object of its own:
    //
    //   siteHostMembers       shmSiteID -> sites,  shmHostID -> hosts
    //   siteGroupMembers      sgmSiteID -> sites,  sgmGroupID -> groups
    //   siteUserMembers       sumSiteID -> sites,  sumUserID -> users
    //   siteUserGroupMembers  sugmSiteID -> sites, sugmUserGroupID -> userGroups
    //   siteRoleGrants        srgSiteID -> sites,  srgRoleID -> roles
    //   siteUserGroupGrants   suggSiteID -> sites, suggGroupID -> userGroups
    //   roleUserAssoc         ruaRoleID -> roles,  ruaUserID -> users
    //   roleUserGroupAssoc    rugRoleID -> roles,  rugGroupID -> userGroups
    //   rolePermissions       rpRoleID -> roles
    //   userGroupMembers      ugmGroupID -> userGroups, ugmUserID -> users
    //   apiTokens             atUserID -> users
    //   userAuths             uaUserID -> users
    //
    // THIS IS THE GROUP WHERE A LEFTOVER ROW IS AN ACCESS DECISION, which is
    // why it goes second rather than later. Route::deletemass() already says
    // so in as many words for the site tables -- a membership row left by a
    // deleted host can put an unrelated NEW host into that site, and a stale
    // grant "leaks a whole population" rather than one object. Every one of
    // those cleanups stays; this makes them true for the paths that never
    // call deletemass().
    //
    // ONE BEHAVIOR ADDITION, and it is the reason this group is worth more
    // than tidiness: `userAuths` is NOT in deletemass('user')'s list. That
    // table holds live remember-me credentials -- a selector hash, a
    // password hash and an expiry -- so deleting a user leaves a working
    // persistent-login row behind today. It is not directly exploitable:
    // ProcessLogin verifies the hashes and then requires the User to load
    // and be valid, so a deleted owner fails closed. What it is exposed to
    // is id reuse, the same hazard the site cleanup above is written
    // against: if the id is later handed to a new account, a surviving
    // cookie authenticates its holder AS that account. deletemass('user')
    // deletes apiTokens for exactly this reason and records why. This does
    // for userAuths what that entry does for tokens, and does it in the one
    // place no call site can skip. No PHP change accompanies it -- a
    // redundant delete would be a second thing to keep in step with the
    // first.
    //
    // Everything else here PINS what deletemass() already does:
    // role -> rolePermissions + the two role associations; usergroup ->
    // userGroupMembers + roleUserGroupAssoc; site -> its four membership
    // lists; user -> roleUserAssoc, userGroupMembers, apiTokens. Nothing an
    // admin can observe changes for any of them.
    //
    // Preconditions already landed: step 381 swept the orphans (all 21 of
    // these are CASCADE, so they were in its list) and step 380 fixed the
    // only type mismatches. Measured on the live 1.6 database, all 21 are
    // clean -- 0 orphans, no type or collation difference.
    //
    // Same mechanism and the same failure policy as step 382: the map in
    // commons/schema-constraints.php carries the declarations, this flips
    // its group on, and applyConstraints() reports a refusal rather than
    // failing the update.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(2);

        return true;
    },
];

// 384
$this->schema[] = [
    // ADR 0031 group 3: storage -- groups, nodes, and what they carry.
    //
    // Four constraints across two tables:
    //
    //   imageGroupAssoc   igaStorageGroupID -> nfsGroups, igaImageID -> images
    //   snapinGroupAssoc  sgaStorageGroupID -> nfsGroups, sgaSnapinID -> snapins
    //
    // This step originally declared a fifth, nfsGroupMembers.ngmGroupID ->
    // nfsGroups, ON DELETE CASCADE. That was wrong: a storage node outlives
    // its group and CASCADE would have destroyed one's whole configuration
    // when a group was deleted. The map now classes it config/SET NULL and
    // it lands with group 5; step 385 removes the constraint from any
    // database that got this far before the correction.
    //
    // THIS IS THE GROUP THE SURVEY'S ORPHAN COUNTS ACTUALLY NAMED.
    // Route::deletemass() has a case for host, group, image, module,
    // printer, snapin, user, role, usergroup and site. It has NO case for
    // storagegroup or storagenode, and neither StorageGroup nor StorageNode
    // overrides destroy() -- so deleting a storage group cascades to
    // nothing, in every path, including the UI. Every orphan found in the
    // live 1.6 database outside moduleStatusByHost traces to that: storage
    // group 3 and node 4 were deleted at some point and nfsGroupMembers,
    // multicastSessions and three columns of tasks still pointed at them.
    //
    // So unlike groups 1 and 2, this one does NOT pin behavior FOG already
    // has. It supplies behavior FOG never had, and it supplies it in the
    // only place that cannot be skipped. That is also why it is worth
    // landing before the PHP: with these constraints on, a storage group
    // delete does the right thing everywhere, and whatever remains for
    // deletemass() to do is then a measured gap rather than a guessed one.
    //
    // The two nfsGroups references are why step 380 exists: all four
    // ...GroupAssoc columns were mediumint(9) against an int(11) parent and
    // could not be declared until that widened them.
    //
    // WHAT DELIBERATELY STAYS UNCONSTRAINED. nfsFailures carries nfGroupID
    // and nfNodeID and takes no key: it is a failure record, and ADR 0021's
    // rule applies to it exactly as it does to taskLog -- deleting a node
    // must not delete the record of it having failed. The multicast and
    // fileDeleteQueue references to storage are RESTRICT with a `0`
    // sentinel, so they cannot be declared until the sentinel becomes NULL;
    // they land with group 5.
    //
    // Same mechanism and failure policy as steps 382 and 383.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(3);

        return true;
    },
];

// 385
$this->schema[] = [
    // Correction, not a new group.
    //
    // Step 384 created `fk_nfsGroupMembers_ngmGroupID` ON DELETE CASCADE.
    // The map now declares that relationship config/SET NULL and leaves it
    // disabled until the sentinel conversion makes the column nullable, so
    // the constraint the database holds is one nothing asks for any more.
    //
    // Running the reconciler is enough to remove it, but only because this
    // step is landing alongside the change that taught planConstraints() to
    // compare the DECLARATION rather than the name. Before that it read
    // names out of information_schema, saw `fk_nfsGroupMembers_ngmGroupID`
    // present, and called the relationship done -- so an action correction
    // was a permanent no-op on every server that had already applied the
    // old one. Names do not encode ON DELETE.
    //
    // It will only ever drop a constraint carrying the name
    // SchemaReconciler::constraintName() generates for a relationship the
    // map lists. One added by hand does not carry that name.
    //
    // GROUP 0 -- no relationship carries it, so this step ADDS nothing. It
    // is a retirement step and only a retirement step. Retirements are
    // never filtered by group, precisely so a wrong declaration can be
    // corrected from whichever step runs next; adds are, so that this one
    // cannot reach forward into group 5 and try constraints whose columns
    // steps 386 and 387 have not prepared yet. Leaving it unfiltered was
    // measured doing exactly that: seven group 5 constraints attempted
    // here, five refused -- three at errno 150 for a SET NULL over a NOT
    // NULL column, two at 1452 over rows step 387 had not swept -- all of
    // which then applied cleanly at step 388 anyway. Noise on every
    // upgrade, for nothing.
    //
    // WHY THE CASCADE WAS WRONG. A storage node is not a satellite of its
    // group. It holds its own hostname, credentials, root/FTP/snapin paths,
    // interface, bandwidth limit, max clients and enable flag, none of it
    // recoverable from the group. Under CASCADE, deleting a storage group
    // would have taken every node's configuration with it, silently -- a
    // destructive behavior change nobody asked for.
    //
    // The relationship is RESTRICT: a node always belongs to a group (a
    // group may have no nodes; a node may not have no group), so deleting a
    // group that still has nodes is refused until they are moved. It is
    // NOT SET NULL -- there is no legitimate "no group" state to spell.
    //
    // Step 381's orphan sweep lost the same relationship, because deleting
    // a real node's configuration is not the repair for a missing group.
    // Assigning it to one is, and only an administrator can pick which.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(0);

        return true;
    },
];

// 386
$this->schema[] = [
    // The `0` sentinel becomes a real NULL.
    //
    // Nine columns across five tables spell "no reference" as the integer 0.
    // Nothing named 0 exists in any of the parent tables -- `taskStates`
    // holds ids 1 to 6, `images` and `nfsGroups` start at 1 -- so a 0 is a
    // reference to a row that cannot exist. No foreign key can be declared
    // over that: ADD CONSTRAINT validates existing rows and fails 1452, and
    // even on an empty table the next write of a 0 would be refused.
    //
    // NULL is what SQL has for "no reference". It is also what the ORM
    // already wants: save()'s GH-1245 branch asks
    // FOGBase::columnIsNullable() and writes NULL rather than 0 for an empty
    // optional *id -- reading the answer out of commons/schema-expected.php,
    // which is why the manifest change in this commit is behavior and not
    // documentation. Making the column nullable is what turns that branch
    // on; the explicit writes of 0 elsewhere are changed in the same commit.
    //
    // WHICH COLUMNS. A column converts when FOG can actually produce a 0 in
    // it -- rows that hold one today, or a code path that writes one. That
    // test rather than a census, because a census of two installs is a
    // snapshot and the columns holding no zeros today are exactly the ones
    // where a rare path would put one tomorrow.
    //
    //   hosts.hostImage              rows on both installs; RegisterClient,
    //                                Image::destroy, Route's image delete
    //   images.imageOSID             22 of 22 images on the 1.5 install
    //   scheduledTasks.stImageID     rows
    //   tasks.taskImageID            rows
    //   tasks.taskNFSGroupID         rows
    //   tasks.taskNFSMemberID        rows
    //   tasks.taskLastMemberID       rows
    //   multicastSessions.msSenderNode  rows; MulticastManager, MulticastTask
    //   multicastSessions.msState    written 0 at session creation by
    //                                Group, Host and imagemanagement
    //
    // nfsGroupMembers.ngmGroupID was on this list and is deliberately NOT.
    // A storage node always belongs to a group -- a group may have no nodes,
    // a node may not have no group. So `0` there is not "no reference", it
    // is a broken row, and NULL would only make the breakage permanent and
    // legal. The column stays NOT NULL and takes a RESTRICT constraint with
    // group 5; a row still holding 0 makes that constraint be REFUSED and
    // named in the log, which is the correct outcome. Only the administrator
    // knows which group such a node belongs to, so nothing here guesses one.
    //
    // WHAT DELIBERATELY DOES NOT CONVERT, and stays NOT NULL:
    // images.imageTypeID, images.imagePartitionTypeID,
    // scheduledTasks.stTaskTypeID, multicastSessions.msNFSGroupID,
    // fileDeleteQueue.fdqStorageGroupID, tasks.taskStateID, tasks.taskTypeID,
    // snapinJobs.sjStateID and snapinTasks.stState. No row holds a 0 and no
    // code path writes one. "No type" and "no state" are not states a task
    // or an image can legitimately be in, so NOT NULL plus RESTRICT is the
    // stronger and more honest declaration, and it is a group 5 decision
    // rather than a conversion. Two of them carry a latent risk noted in
    // docs/development/foreign-keys.md: tasks.taskStateID and
    // snapinTasks.stState are not in their model's
    // $databaseFieldsRequired, so save() would write 0 for an empty value
    // and RESTRICT would then refuse it at runtime. That is handled with
    // the constraint, not here.
    //
    // NULLABLE COLUMN + REQUIRED IN THE MODEL is the pairing worth naming.
    // images.imageOSID becomes nullable so the 22 OS-less images an upgrade
    // brings across can be represented and constrained, while `osID` stays
    // in Image::$databaseFieldsRequired so save() still refuses to create a
    // new image without one. The database tolerates the history; the ORM
    // prevents new instances of it.
    //
    // IRREVERSIBLE. After this runs, "was 0" and "was NULL" are the same
    // information. There is no down migration and the survey's rollback
    // section says so: restore from backup. It is separated from every
    // constraint step for exactly that reason -- a constraint that fails
    // must never leave a half-converted column behind.
    //
    // ORDER. MODIFY first, then UPDATE: a NOT NULL column will not take a
    // NULL. Guarded on IS_NULLABLE so a fresh install and a re-run do no
    // work; MODIFY COLUMN rebuilds the table even when it changes nothing.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        $convert = [
            ['hosts', 'hostImage', 'INT(11) NULL DEFAULT NULL'],
            ['images', 'imageOSID', 'MEDIUMINT(9) NULL DEFAULT NULL'],
            ['scheduledTasks', 'stImageID', 'INT(11) NULL DEFAULT NULL'],
            ['tasks', 'taskImageID', 'INT(11) NULL DEFAULT NULL'],
            ['tasks', 'taskNFSGroupID', 'INT(11) NULL DEFAULT NULL'],
            ['tasks', 'taskNFSMemberID', 'INT(11) NULL DEFAULT NULL'],
            ['tasks', 'taskLastMemberID', 'INT(11) NULL DEFAULT NULL'],
            ['multicastSessions', 'msSenderNode', 'INT(11) NULL DEFAULT NULL'],
            ['multicastSessions', 'msState', 'INT(11) NULL DEFAULT NULL'],
        ];

        $perColumn = [];
        $total = 0;
        foreach ($convert as $c) {
            list($table, $column, $definition) = $c;
            $row = self::$DB->query(
                "SELECT `IS_NULLABLE` AS `n` "
                . "FROM `information_schema`.`COLUMNS` "
                . "WHERE `TABLE_SCHEMA` = DATABASE() "
                . "AND `TABLE_NAME` = '$table' "
                . "AND `COLUMN_NAME` = '$column'"
            )->fetch(\PDO::FETCH_ASSOC)->get();
            if (!is_array($row) || !isset($row['n'])) {
                // No such table or column on this server -- a fresh install
                // builds it nullable from the manifest.
                continue;
            }
            if (strtoupper((string)$row['n']) === 'NO') {
                self::$DB->query(
                    "ALTER TABLE `$table` "
                    . "MODIFY COLUMN `$column` $definition"
                );
            }
            self::$DB->query(
                "UPDATE `$table` SET `$column` = NULL WHERE `$column` = 0"
            );
            $n = (int) self::$DB->affectedRows();
            if ($n > 0) {
                $perColumn["$table.$column"] = $n;
                $total += $n;
            }
        }

        foreach ($perColumn as $what => $n) {
            error_log(
                sprintf(
                    'FOG schema 386: converted %d `0` sentinel(s) to NULL in %s',
                    $n,
                    $what
                )
            );
        }

        if ($total > 0) {
            Audit::record(
                [
                    'type' => 'schema.sentinel.convert',
                    'subjectType' => 'schema',
                    'subjectId' => 386,
                    'summary' => sprintf(
                        _('Converted %1$d `0` reference(s) to NULL across %2$d column(s)'),
                        $total,
                        count($perColumn)
                    ),
                    'detail' => json_encode($perColumn),
                    'affectedCount' => $total,
                    'renderable' => 1,
                ]
            );
        }

        return true;
    },
];

// 387
$this->schema[] = [
    // One sweep, for the one relationship group 5 turns into a CASCADE.
    //
    // multicastSessions.msNFSGroupID becomes ON DELETE CASCADE in step 388.
    // ADD CONSTRAINT validates the rows already there, so a session whose
    // storage group was deleted before the constraint existed answers 1452
    // and the constraint is refused -- on every upgrade, forever, for a row
    // the declared rule would itself have removed.
    //
    // Same reasoning and the same shape as step 381: sweep first, in its own
    // step, so a constraint failure can never leave half-swept data. Kept
    // out of 381's frozen list rather than added to it, because that list is
    // the record of what THAT step deleted and rewriting it would make the
    // audit row it wrote a lie.
    //
    // WHY DELETING IS RIGHT HERE. A multicast session is work performed by a
    // storage group. It carries no configuration of its own, it cannot be
    // re-pointed at another group, and the imaging record it produced lives
    // in taskLog, which takes no constraint at all (ADR 0021). A session
    // whose group is gone can never run and can never be read usefully --
    // the group name, interface and node all resolve to nothing.
    //
    // Contrast nfsGroupMembers, which is deliberately NOT swept anywhere: a
    // storage node holds its own credentials and paths, so a missing group
    // there is a broken row to be repaired by assigning one, not a row to
    // delete. See step 381.
    //
    // Measured on the live 1.6 install: 1 session of 16, from storage group
    // 3, completed 2026-07-27. The 1.5 install has none.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        $have = self::$DB->query(
            "SELECT `TABLE_NAME` AS `t` "
            . "FROM `information_schema`.`TABLES` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() "
            . "AND `TABLE_NAME` IN ('multicastSessions', 'nfsGroups')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        if (!is_array($have) || count($have) < 2) {
            // A server that has not built both tables yet. A fresh install
            // creates them empty, so there is nothing to sweep.
            return true;
        }

        self::$DB->query(
            "DELETE `c` FROM `multicastSessions` `c` "
            . "LEFT JOIN `nfsGroups` `p` "
            . "ON `c`.`msNFSGroupID` = `p`.`ngID` "
            . "WHERE `p`.`ngID` IS NULL"
        );
        $n = (int) self::$DB->affectedRows();
        if ($n < 1) {
            return true;
        }

        error_log(
            sprintf(
                'FOG schema 387: deleted %d multicast session(s) whose'
                . ' storage group no longer exists',
                $n
            )
        );
        Audit::record(
            [
                'type' => 'schema.orphan.sweep',
                'subjectType' => 'schema',
                'subjectId' => 387,
                'summary' => sprintf(
                    _('Deleted %1$d multicast session(s) with no storage group'),
                    $n
                ),
                'detail' => json_encode(['multicastSessions' => $n]),
                'affectedCount' => $n,
                'renderable' => 1,
            ]
        );

        return true;
    },
];

// 388
$this->schema[] = [
    // ADR 0031 group 5: references to configuration with a life of its own.
    //
    // Twelve constraints across six tables, and the first group whose
    // actions are not all the same. The question every one of them answers
    // is the same though: when the parent goes, does the child outlive it?
    //
    //   SET NULL -- the child survives, minus the reference
    //     hosts.hostImage            -> images
    //     hosts.hostArchID           -> architectures
    //     images.imageArchID         -> architectures
    //     scheduledTasks.stImageID   -> images
    //     multicastSessions.msSenderNode -> nfsGroupMembers
    //
    //   RESTRICT -- the parent cannot go while the child names it
    //     images.imageOSID           -> os
    //     images.imageTypeID         -> imageTypes
    //     images.imagePartitionTypeID -> imagePartitionTypes
    //     scheduledTasks.stTaskTypeID -> taskTypes
    //     fileDeleteQueue.fdqStorageGroupID -> nfsGroups
    //     nfsGroupMembers.ngmGroupID -> nfsGroups
    //
    //   CASCADE -- the child is work performed by the parent
    //     multicastSessions.msNFSGroupID -> nfsGroups
    //
    // THREE ACTIONS CHANGED from the survey's first pass, all on the same
    // test rather than on a feel:
    //
    //   scheduledTasks.stImageID  RESTRICT -> SET NULL. Deleting an image
    //     already unassigns hosts and cancels live tasks rather than being
    //     refused; nothing touches scheduledTasks, so a schedule outlives
    //     its image and fails every time it fires. Refusing the delete over
    //     a forgotten schedule is worse than leaving that schedule visible
    //     and editable.
    //
    //   multicastSessions.msNFSGroupID  RESTRICT -> CASCADE. Under RESTRICT
    //     one completed session would pin its storage group forever, so a
    //     group that had ever run a multicast could never be deleted.
    //
    //   multicastSessions.msSenderNode  RESTRICT -> SET NULL. It records
    //     which node ran the session, not what the session belongs to.
    //
    // BEHAVIOR THIS ADDS THAT FOG DID NOT HAVE. Groups 1 to 3 mostly pinned
    // decisions Route::deletemass() already made. This group does not:
    //
    //   - deleting an OS, image type, partition type or task type that is
    //     still referenced is now REFUSED at 1451 where it used to succeed
    //     and leave images unreadable;
    //   - deleting a storage group with storage nodes in it is refused --
    //     a node always belongs to a group, so it has to be moved first;
    //   - deleting a storage group with queued file deletions is refused
    //     until the queue drains;
    //   - deleting an image now also clears any scheduled task naming it,
    //     which nothing did before.
    //
    // Every one of those is a loud refusal replacing a silent orphan, which
    // is the trade ADR 0031 exists to make. Refusals surface as an error on
    // the delete, not as a failed update.
    //
    // Preconditions, all landed: step 380 matched the column types, 386
    // converted the `0` sentinels that RESTRICT and SET NULL could not
    // tolerate, and 387 swept the one multicast session whose group was
    // already gone.
    //
    // Passes its own group number. Without it this call would apply every
    // enabled relationship in the map -- see applyConstraints()'s docblock.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(5);

        return true;
    },
];

// 389
$this->schema[] = [
    // Repair, for the four rows group 6 cannot be declared over.
    //
    // Two different repairs, because the two kinds of row are not alike.
    //
    // DELETED -- multicastSessionsAssoc rows whose session is gone. A
    // junction row with no parent has no meaning, which is exactly the rule
    // step 381 applied to this table already. One row survives that sweep on
    // the live install, and it is one THIS SERIES created: step 387 deletes
    // the multicast session whose storage group had been removed, and 381
    // had already run by then. Swept here rather than by editing 387,
    // because 387's audit row records what 387 deleted and rewriting it
    // would make that record false.
    //
    // NULLED -- the three columns that record which storage served a task.
    // A task is the record of what was imaged onto a host. Deleting one
    // because the storage node it used has since been removed would destroy
    // that record to satisfy a reference that is no longer interesting; the
    // constraint is SET NULL for the same reason, so nulling the rows that
    // predate it is simply applying that rule to history. Measured on the
    // live 1.6 install: 1 row each, all three pointing at storage group 3
    // and node 4, deleted at some point before this work began. They are the
    // orphans the original survey found.
    //
    // The two multicast tables are not symmetrical and that is deliberate:
    // multicastSessionsAssoc is a junction (delete), tasks is a record
    // (keep, minus the reference).
    //
    // Guarded on the tables existing, and both statements are naturally
    // idempotent -- a second run matches nothing.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        $tables = self::$DB->query(
            "SELECT `TABLE_NAME` AS `t` "
            . "FROM `information_schema`.`TABLES` "
            . "WHERE `TABLE_SCHEMA` = DATABASE()"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $have = [];
        foreach ((array) $tables as $row) {
            if (isset($row['t'])) {
                $have[strtolower($row['t'])] = true;
            }
        }

        $repaired = [];

        if (isset($have['multicastsessionsassoc'], $have['multicastsessions'])) {
            self::$DB->query(
                "DELETE `c` FROM `multicastSessionsAssoc` `c` "
                . "LEFT JOIN `multicastSessions` `p` "
                . "ON `c`.`msID` = `p`.`msID` "
                . "WHERE `p`.`msID` IS NULL"
            );
            $n = (int) self::$DB->affectedRows();
            if ($n > 0) {
                $repaired['multicastSessionsAssoc.msID (deleted)'] = $n;
            }
        }

        $null = [
            ['tasks', 'taskNFSGroupID', 'nfsGroups', 'ngID'],
            ['tasks', 'taskNFSMemberID', 'nfsGroupMembers', 'ngmID'],
            ['tasks', 'taskLastMemberID', 'nfsGroupMembers', 'ngmID'],
        ];
        foreach ($null as $n2) {
            list($child, $column, $parent, $pcolumn) = $n2;
            if (!isset($have[strtolower($child)], $have[strtolower($parent)])) {
                continue;
            }
            self::$DB->query(
                "UPDATE `$child` `c` "
                . "LEFT JOIN `$parent` `p` "
                . "ON `c`.`$column` = `p`.`$pcolumn` "
                . "SET `c`.`$column` = NULL "
                . "WHERE `c`.`$column` IS NOT NULL "
                . "AND `p`.`$pcolumn` IS NULL"
            );
            $n = (int) self::$DB->affectedRows();
            if ($n > 0) {
                $repaired["$child.$column (nulled)"] = $n;
            }
        }

        if (count($repaired) < 1) {
            return true;
        }

        $total = 0;
        foreach ($repaired as $what => $n) {
            $total += $n;
            error_log(
                sprintf('FOG schema 389: repaired %d row(s) in %s', $n, $what)
            );
        }
        Audit::record(
            [
                'type' => 'schema.orphan.sweep',
                'subjectType' => 'schema',
                'subjectId' => 389,
                'summary' => sprintf(
                    _('Repaired %1$d orphaned work row(s) across %2$d column(s)'),
                    $total,
                    count($repaired)
                ),
                'detail' => json_encode($repaired),
                'affectedCount' => $total,
                'renderable' => 1,
            ]
        );

        return true;
    },
];

// 390
$this->schema[] = [
    // ADR 0031 group 6: tasks and the work that hangs off them. The last
    // core group -- sixteen constraints across six tables, after which every
    // core relationship the map declares is in the database.
    //
    //   CASCADE   tasks.taskHostID -> hosts
    //             snapinJobs.sjHostID -> hosts
    //             snapinTasks.stJobID -> snapinJobs
    //             snapinTasks.stSnapinID -> snapins
    //             multicastSessionsAssoc.msID -> multicastSessions
    //             multicastSessionsAssoc.tID -> tasks
    //   SET NULL  tasks.taskImageID -> images
    //             tasks.taskNFSGroupID -> nfsGroups
    //             tasks.taskNFSMemberID -> nfsGroupMembers
    //             tasks.taskLastMemberID -> nfsGroupMembers
    //   RESTRICT  tasks.taskStateID -> taskStates
    //             tasks.taskTypeID -> taskTypes
    //             snapinJobs.sjStateID -> taskStates
    //             snapinTasks.stState -> taskStates
    //             fileDeleteQueue.fdqState -> taskStates
    //             multicastSessions.msState -> taskStates
    //
    // MOSTLY THIS PINS WHAT deletemass() ALREADY DOES. Deleting a host
    // already deletes its tasks, snapin jobs and snapin tasks; deleting a
    // snapin already deletes its snapin tasks; deleting an image already
    // clears taskImageID (it cancels the live tasks and leaves the finished
    // ones as the host's imaging record). Those seven change nothing
    // observable and close the non-page paths -- the REST API's DELETE
    // funnels to deletemass(), but a plugin, a daemon or a hand-run query
    // does not.
    //
    // WHAT IS NEW is the six RESTRICTs on taskStates and taskTypes. Those
    // are seed rows nobody deletes in normal use; deleting one now fails at
    // 1451 instead of rendering every task that used it unreadable.
    //
    // THE THREE STORAGE REFERENCES ARE SET NULL, NOT RESTRICT, which is a
    // change from the survey's first pass. They record which storage served
    // a task, not what the task belongs to. Under RESTRICT one finished task
    // would pin its storage group or node until retention pruned the task --
    // months -- so emptying a group would not be enough to let you delete
    // it. Nullable as of step 386; a task minus its storage reference is
    // still a complete record of what was imaged onto which host.
    //
    // tasks.taskStateID could not be declared honestly until the path that
    // created a task with no state was fixed. Host::createTasking's
    // SINGLE_SNAPIN to ALL_SNAPINS conversion set three fields and saved, so
    // save() filled taskStateID with 0 -- not a taskStates row, so the task
    // never appeared in Active Tasks and never ran. Fixed in this commit,
    // and `stateID` added to Task::$databaseFieldsRequired so no future path
    // can do it silently.
    //
    // Preconditions: step 386 made the four nullable columns nullable, and
    // step 389 repaired the four orphaned rows -- three nulled, one junction
    // row deleted.
    //
    // See docs/development/foreign-keys.md and ADR 0031.
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(6);

        return true;
    },
];
// 391
// Per-user preferences, as an opaque key/value store.
//
// The first consumer is DataTables' own saved state -- column order, which
// columns are showing, page length, sort -- which every grid throws away on
// reload today because registerTable sets stateSave:false. DataTables already
// serializes that state and exposes stateSaveCallback/stateLoadCallback to
// put it somewhere, so this table deliberately does NOT model "column
// positions": it stores whatever the client hands it, under a key, for one
// user. That is what keeps the schema from having to track DataTables'
// internals, which change between its major versions.
//
// upKey is varchar(190) rather than the house 254 because it is half of a
// UNIQUE KEY: utf8mb3 costs 3 bytes a character, and 254 would put the index
// over InnoDB's 767-byte prefix limit on the older row formats FOG still
// supports. 190 * 3 + 4 = 574.
//
// The UNIQUE KEY is what makes a write an upsert rather than an append -- see
// UserPref::store(). That is normally a bug (a create silently overwriting an
// existing row); here overwriting the previous value of the same preference
// for the same user IS the operation.
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `userPrefs` ( "
    . "`upID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`upUserID` int(11) NOT NULL DEFAULT 0, "
    . "`upKey` varchar(190) NOT NULL DEFAULT '', "
    // Nullable rather than NOT NULL: a TEXT column cannot portably carry a
    // literal DEFAULT (MySQL 8 refuses one outright), so NOT NULL with no
    // default would make any INSERT that omitted the value error 1364 on a
    // strict server -- which is what tests/optional-columns-carry-defaults
    // exists to catch, and did.
    . "`upValue` longtext DEFAULT NULL, "
    . "`upCreatedTime` datetime NOT NULL DEFAULT current_timestamp(), "
    . "`upModifiedTime` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`upID`), "
    . "UNIQUE KEY `upUserKey` (`upUserID`,`upKey`), "
    . "KEY `upUserID` (`upUserID`) "
    // One line: the collation gate reads the CREATE statement line by line,
    // so splitting CHARSET from COLLATE reads to it as a bare charset.
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 392
// The foreign key for the table step 391 just created, per ADR 0031.
//
// Group 7. A number rather than a name: the named groups belong to plugins,
// whose constraints are applied by a step in the plugin's own schema().
//
// Unlike groups 1 through 6 this one has nothing to migrate. Those phase in
// constraints over tables that already hold years of data, so each is a
// sweep plus a flip; a table created empty one step earlier cannot hold an
// orphan, so there is no sweep to sequence before this.
//
// A preference is a satellite of the user it belongs to and means nothing
// without them, so CASCADE -- the same call as apiTokens and userAuths.
$this->schema[] =
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(7);

        return true;
    };
// 393
// Named, saved filters for a grid.
//
// Separate from userPrefs on purpose. A preference is one opaque value per
// (user, key) and every write replaces the whole of it; filters are a LIST
// that gets added to, renamed and deleted one at a time. Holding that list as
// a JSON array in a single preference would make every add a read-modify-write
// of the whole array, so two tabs saving at once would lose one silently --
// exactly the race the (user, key) upsert exists to avoid for a single value.
//
// sfValue is OPAQUE, the same stance as userPrefs.upValue: it holds
// DataTables' own searchBuilder state, whose shape belongs to DataTables and
// changes between its major versions. Nothing server-side decides anything on
// the strength of what is inside it.
//
// OWNERSHIP had to be decided now rather than retrofitted:
//
//   sfUserID <id>   private to that user, and shareable BY them -- see the
//                   three grant tables at step 394.
//   sfUserID NULL   the filter is GLOBAL, owned by the install rather than by
//                   a person, and offered to everyone on that grid. NULL
//                   rather than a 0 sentinel so the foreign key at step 395
//                   can be a real one; 0 has no row in `users`.
//
// sfCreatorID records who MADE it and is kept for globals, because "who do I
// ask about this filter" is otherwise unanswerable, and a column added later
// would be empty for every filter that already existed. It is SET NULL rather
// than CASCADE, which is also what makes a global outlive the person who
// wrote it -- a site-wide filter must not vanish because somebody left.
//
// The UNIQUE KEY enforces "one name per grid per owner" for PRIVATE filters
// only. MySQL does not treat two NULLs as equal in a unique index, so it does
// NOT constrain global names -- SavedFilterManager::store() checks that case
// explicitly rather than pretending the index covers it.
//
// Column widths are set by that index, not by taste: it is
// int + varchar(128) + varchar(64) in utf8mb3, which is 4 + 386 + 193 = 583
// bytes and fits under InnoDB's 767-byte prefix limit on the older row
// formats FOG still supports. Two varchar(190) columns -- the userPrefs width
// -- would come to 1148 and only work on DYNAMIC.
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `savedFilters` ( "
    . "`sfID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`sfUserID` int(11) DEFAULT NULL, "
    . "`sfCreatorID` int(11) DEFAULT NULL, "
    . "`sfTable` varchar(128) NOT NULL DEFAULT '', "
    . "`sfName` varchar(64) NOT NULL DEFAULT '', "
    // Nullable for the same reason userPrefs.upValue is: a TEXT column cannot
    // portably carry a literal DEFAULT, so NOT NULL with no default would
    // error 1364 on a strict server for any INSERT that omitted it.
    . "`sfValue` longtext DEFAULT NULL, "
    . "`sfCreatedTime` datetime NOT NULL DEFAULT current_timestamp(), "
    . "`sfModifiedTime` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`sfID`), "
    . "UNIQUE KEY `sfOwnerTableName` (`sfUserID`,`sfTable`,`sfName`), "
    . "KEY `sfTable` (`sfTable`), "
    . "KEY `sfCreatorID` (`sfCreatorID`) "
    // One line: the collation gate reads the CREATE statement line by line,
    // so splitting CHARSET from COLLATE reads to it as a bare charset.
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 394
// Who a filter is shared with, when it is shared with somebody specific.
//
// Global is the blunt instrument: it puts an entry in every single user's
// picker on that grid, so it takes a permission nobody holds by default.
// These three tables are the narrow version, which needs no permission at
// all -- sharing a filter you own with a named colleague, group or role is
// ordinary collaboration, not an administrative act, and it can only ADD one
// named entry to the picker of somebody you could already name.
//
// THREE junction tables rather than one polymorphic grant table with a
// target type. FOG has both shapes: the plugin grant tables (ldapUserGrant,
// oidcUserGrant) are polymorphic and are recorded in the constraint map as
// class 'poly', action 'none' -- meaning the database cannot enforce them at
// all, because a column pointing at three different parents cannot carry a
// foreign key. ADR 0031 exists to shrink that set, not grow it. Three tables
// cost more DDL once and every reference in them is declared and enforced.
//
// The UNIQUE KEY on each makes a re-share a no-op rather than a duplicate,
// which matters because the share editor sends the whole target list.
//
// Note what is NOT here: an approval state. "Share it with my manager, who
// approves it" is a conversation, and the model that supports it is simply
// that the share list is editable -- share with one person, then widen to
// the groups and roles that need it. Encoding approval as a column would
// mean a filter that exists but cannot be used, which is a workflow nobody
// asked for.
$this->schema[] = [
    "CREATE TABLE IF NOT EXISTS `savedFilterUserAssoc` ( "
    . "`sfuaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`sfuaFilterID` int(11) NOT NULL, "
    . "`sfuaUserID` int(11) NOT NULL, "
    . "PRIMARY KEY (`sfuaID`), "
    . "UNIQUE KEY `sfuaFilterUser` (`sfuaFilterID`,`sfuaUserID`), "
    . "KEY `sfuaUserID` (`sfuaUserID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `savedFilterGroupAssoc` ( "
    . "`sfgaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`sfgaFilterID` int(11) NOT NULL, "
    . "`sfgaUserGroupID` int(11) NOT NULL, "
    . "PRIMARY KEY (`sfgaID`), "
    . "UNIQUE KEY `sfgaFilterGroup` (`sfgaFilterID`,`sfgaUserGroupID`), "
    . "KEY `sfgaUserGroupID` (`sfgaUserGroupID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `savedFilterRoleAssoc` ( "
    . "`sfraID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`sfraFilterID` int(11) NOT NULL, "
    . "`sfraRoleID` int(11) NOT NULL, "
    . "PRIMARY KEY (`sfraID`), "
    . "UNIQUE KEY `sfraFilterRole` (`sfraFilterID`,`sfraRoleID`), "
    . "KEY `sfraRoleID` (`sfraRoleID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];
// 395
// The foreign keys for the four tables steps 393 and 394 just created, per
// ADR 0031.
//
// Group 8, and like group 7 it has nothing to migrate: tables created empty
// one step earlier cannot hold an orphan, so there is no sweep to sequence
// before the flip.
//
// Three actions, chosen separately:
//
//  - savedFilters.sfUserID CASCADE. A private filter is a satellite of its
//    owner and means nothing without them.
//  - savedFilters.sfCreatorID SET NULL. Provenance on a row that may belong
//    to the whole install; a shared filter must outlive its author.
//  - every grant column CASCADE. A share is meaningless once either end of
//    it is gone, and leaving the row would silently offer a filter to a
//    group id that has since been reused.
$this->schema[] =
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(8);

        return true;
    };
// 396
$this->schema[] = [
    // The UTC storage boundary.
    //
    // From this step on, every value FOG writes into a date column is UTC:
    // storageTimeZone() answers UTC once this row exists, and the database
    // session runs at +00:00 so NOW() and DEFAULT current_timestamp() agree
    // with it. FOG_TZ_INFO stops being a storage zone and becomes what its
    // name always suggested -- the install's default DISPLAY zone.
    //
    // Nothing already stored is converted. It cannot be: up to five clocks
    // have written these columns (PHP through FOG_TZ_INFO, MySQL NOW(),
    // MySQL DEFAULT current_timestamp(), the display-zone regression fixed
    // in #1491, and the fog-client's own clock), and no sweep can know which
    // wrote any given row. So instead of guessing, this records the instant
    // the convention changed and every reader compares against it. See
    // docs/development/utc-storage-boundary.md.
    //
    // Its own table rather than a globalSettings row: the configuration page
    // renders every row of that table with no WHERE, and `setting` is a
    // routed class, so the value would be one careless edit away from
    // changing the meaning of every timestamp in the install. Rather than a
    // column on schemaVersion, whose seed INSERT is positional -- a third
    // column makes a fresh install die on 1136 -- and whose entire contract
    // with the installer and the updater is "one integer".
    "CREATE TABLE IF NOT EXISTS `storageEpoch` ( "
    . "`seID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`seBoundary` datetime DEFAULT NULL, "
    . "`seZone` varchar(64) NOT NULL DEFAULT '', "
    . "`seDbZone` varchar(64) NOT NULL DEFAULT '', "
    . "`seSchema` int(11) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`seID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Written once and never again. The guard is a row count rather than an
    // INSERT IGNORE on a fixed id: re-running the step on an install that
    // already has a boundary must not move it, and moving it is the one
    // change that would silently re-interpret every date in the database.
    //
    // seZone and seDbZone are recorded because they are what lets a reader
    // say what a pre-boundary value MEANT, and what would let a future
    // maintainer narrow the 26-hour band deliberately rather than
    // rediscover why it is wide. UTC_TIMESTAMP() rather than NOW(): the
    // session zone is not yet pinned at this point in the upgrade.
    function () {
        $existing = self::$DB
            ->query('SELECT COUNT(`seID`) AS `c` FROM `storageEpoch`')
            ->fetch()
            ->get('c');
        if ((int)$existing > 0) {
            return true;
        }
        // Read straight out of globalSettings rather than through
        // getSetting(): a schema step runs with the settings cache in
        // whatever state the rest of the upgrade left it, and this value
        // has to be the one that was in force, not a default.
        $zone = (string)self::$DB
            ->query(
                'SELECT `settingValue` FROM `globalSettings` '
                . "WHERE `settingKey` = 'FOG_TZ_INFO'"
            )
            ->fetch()
            ->get('settingValue');
        self::$DB->query(
            'INSERT INTO `storageEpoch` '
            . '(`seBoundary`, `seZone`, `seDbZone`, `seSchema`) '
            . 'SELECT UTC_TIMESTAMP(), '
            . self::$DB->sanitize($zone) . ', '
            . '@@global.system_time_zone, 396'
        );

        return true;
    },
];
// 397
// Repairs FOG_VIEW_DEFAULT_SCREEN, which step 17 seeded as a page NAME.
//
// The setting used to mean "which screen does a section open on", and its
// two values were LIST and SEARCH -- which is still exactly what it means
// on 1.5, so this repair is 1.6-only and there is nothing to port. In 1.6
// it was repurposed into the grids' rows-per-page default and the
// Configuration page began rendering it as a 10/25/50/100/All picker, but
// step 17 was never corrected. An upgraded install is fine because it
// carries the numeric row its 1.5 self already held; a FRESH 1.6 install
// seeded the literal string SEARCH into what is now a row count.
//
// The damage is not cosmetic. The value reaches the browser through the
// hidden #pageLength input, where registerTable() does parseInt() on it --
// so a fresh install handed every grid pageLength: NaN. Infinite scroll
// happened to survive it, because Scroller needs a finite chunk and already
// had a fallback for "All"; classic paging had no such guard and got the
// NaN. fog.common.js now normalizes the value too, so the two halves cover
// each other rather than either being the only thing standing between a bad
// setting and a broken grid.
//
// The value is only touched when it is one of the two names step 17 could
// have produced. An admin who has chosen a number keeps it, including a
// number the picker does not offer, because "not in the dropdown" is not the
// same as "invalid" and silently resetting somebody's choice to fix our own
// seed would be the worse bug. Collation is case-insensitive, so the two
// literals also cover a lowercased copy.
//
// The description is rewritten unconditionally: that text is ours, not the
// admin's, and the old wording documents behavior that no longer exists.
$this->schema[] = [
    "UPDATE `globalSettings` "
    . "SET `settingDesc` = 'This setting defines how many rows a management "
    . "list shows to a user who has not yet saved a layout of their own. "
    . "Column order, visibility, sort and row count are remembered per user "
    . "once changed, so this is the starting default rather than a fixed "
    . "limit.' "
    . "WHERE `settingKey` = 'FOG_VIEW_DEFAULT_SCREEN'",
    "UPDATE `globalSettings` "
    . "SET `settingValue` = '25' "
    . "WHERE `settingKey` = 'FOG_VIEW_DEFAULT_SCREEN' "
    . "AND `settingValue` IN ('SEARCH','LIST')",
];
// 398
$this->schema[] = [
    // Impersonation (ADR 0033). Two columns on auditLog, and a setting.
    //
    // alCreatedBy is deliberately NOT touched. It stays the REAL
    // administrator for every row written during a span, because everything
    // that already asks "what did user X do" reads it, and flipping it would
    // attribute the target's name to actions they did not take -- which is
    // worse than no record at all, since it destroys repudiation for the one
    // person who cannot disprove it. alActedAs is supplementary: who was
    // being acted AS. Empty means nobody, so `alActedAs <> ''` is the whole
    // impersonated write surface.
    //
    // alSpanID rather than reusing alCorrelationID: a correlation id is
    // REQUEST scoped and its docblock commits to that, while a span covers
    // many requests. The bracket -- an impersonation.start row, an
    // impersonation.end row and everything between -- joins on this one
    // value, so "what did this admin do while impersonating Sarah on the
    // 14th" is one indexed seek rather than a time-range guess.
    //
    // AN UNCLOSED SPAN IS CLOSED AT READ TIME, NOT BY A SWEEP. A browser
    // that is simply closed leaves a start with no end, and the reader
    // resolves it as the earliest of: its own end row, the auth.logout row
    // for the same session, or the start plus the inactivity timeout. No
    // daemon stamps it, deliberately -- that would make a row's truth depend
    // on whether a job ran, which breaks on a database restored onto a
    // server with different settings. Same reasoning ADR 0021 gives for
    // declining a foreign key on auditChange.
    //
    // Guarded closure, same as 336/338/341/349/350/351/353/354: ADD COLUMN
    // has no IF NOT EXISTS below MariaDB 10.0.2 / MySQL 8.0.29, and every
    // column is named in the probe so the installer's grant check still
    // passes.
    function () {
        $have = self::$DB->query(
            "SELECT `COLUMN_NAME` AS `c` FROM `information_schema`.`COLUMNS` "
            . "WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'auditLog' "
            . "AND `COLUMN_NAME` IN ('alActedAs','alSpanID')"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $cols = [];
        foreach ((array)$have as $row) {
            if (isset($row['c'])) {
                $cols[] = $row['c'];
            }
        }
        if (!in_array('alActedAs', $cols)) {
            self::$DB->query(
                "ALTER TABLE `auditLog` "
                . "ADD `alActedAs` VARCHAR(255) NOT NULL DEFAULT '', "
                . "ADD KEY `alActedAs` (`alActedAs`)"
            );
        }
        if (!in_array('alSpanID', $cols)) {
            self::$DB->query(
                "ALTER TABLE `auditLog` "
                . "ADD `alSpanID` VARCHAR(32) NOT NULL DEFAULT '', "
                . "ADD KEY `alSpanID` (`alSpanID`)"
            );
        }

        return true;
    },
    // Whether the impersonated user is told, on their next sign-in, that an
    // administrator viewed their account.
    //
    // A SETTING AND NOT A COLUMN, because no column is needed: "has anyone
    // acted as me since I last signed in" is already answerable from rows
    // this table holds -- the impersonation.start events and the auth.login
    // events, both indexed on alSubject. Taking a column now would have been
    // paying for a decision nothing forces, and the answer would still have
    // had to come from these rows.
    //
    // Defaults ON. An administrator who has never been asked has not decided
    // that the people they impersonate should be kept in the dark, and the
    // recoverable mistake is telling someone something true.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_IMPERSONATION_NOTIFY','Tell a user, the next time they sign in, "
    . "that an administrator viewed their account by impersonating them. The "
    . "audit trail records every impersonation either way; this controls only "
    . "whether the person is told.','1','Logging Settings')",
];
// 399
$this->schema[] = [
    // Retire the stale `site` plugins row -- GH-1543.
    //
    // Step 334 already tried this and CANNOT succeed on an upgrade, which is
    // the only path it exists for. Its gate reads `plugins`.`pLocation` and
    // returns early when that is empty:
    //
    //     if ('' === $loc || 0 !== strncmp($loc, $bundled, strlen($bundled)))
    //
    // but `pLocation` is a RENAME of `pAnon3` (the CHANGE earlier in this
    // file), and 1.5 never wrote `pAnon3` -- it declares the column in
    // plugin.class.php's field map and assigns it nothing. So on every row
    // carried across from 1.5 the value is the empty string, the first arm
    // matches, and the DELETE is unreachable. Verified against a real 1.5.10
    // database: the one `plugins` row has `pAnon3` of length 0.
    //
    // A step runs once, so 334 cannot be corrected in place -- anything
    // already past it will never revisit it. Hence a new step, and one that
    // reaches the same conclusion without `pLocation`.
    //
    // THE GATE IS THE TABLES, NOT THE FILESYSTEM. Step 332 migrates the
    // plugin's rows into core's `sites` and then DROPS `site` and its
    // association tables, but only when the migrated counts match exactly --
    // when they disagree it keeps them deliberately. So `sites` present AND
    // `site` absent is precisely "the migration ran and was believed", which
    // is the condition under which the plugin's row is stale. If the counts
    // disagreed, `site` still exists, this does nothing, and the row stays --
    // which is the right answer, because an admin whose data did not migrate
    // needs the plugin listed.
    //
    // Reading tables rather than the plugin directory also drops step 334's
    // one genuine worry -- an unmounted external plugin root making every
    // plugin look absent at once -- since no path is consulted at all. The
    // plugin's code no longer ships: it was deleted from fog-plugins when
    // core took over sites.
    function () {
        $has = function ($table) {
            $row = self::$DB->query(
                "SELECT COUNT(*) AS `n` FROM `information_schema`.`TABLES`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND LOWER(`TABLE_NAME`) = :t",
                [],
                [':t' => $table]
            )->fetch(\PDO::FETCH_ASSOC)->get();
            return (int)($row['n'] ?? 0) > 0;
        };
        if (!$has('sites') || $has('site')) {
            return true;
        }
        self::$DB->query(
            "DELETE FROM `plugins` WHERE LOWER(`pName`) = 'site'"
        );
        return true;
    },
];

// 400
$this->schema[] = [
    // `multicastSessions`.`msShutdown` survived as enum('0','1') on any
    // server that came from 1.5, which is every server the boolean
    // conversion was written for.
    //
    // WHY IT ESCAPED. The column is `msAnon3` renamed, and the two branches'
    // schema step arrays share positions up to 263 and diverge from 264. A
    // 1.5 database's schemaVersion counts against dev-branch's array, so an
    // upgrade to 1.6 treats 264-277 as already applied and skips them --
    // including that rename. When the boolean sweep ran it looked for
    // `msShutdown`, found no such column, and moved on; SchemaReconciler's
    // rename pass then produced the column afterward, preserving the enum
    // type it had all along. Observed on a real 1.5.10 upgrade, reported by
    // SchemaReconciler::shapeDrift() as
    // `enum('0','1') NOT NULL` where the manifest says `tinyint(1) NOT NULL`.
    //
    // ONLY THIS COLUMN, and that is checked rather than assumed: of every
    // rename in the skipped range -- plugins pAnon1..pAnon4 and
    // multicastSessions msAnon3/msAnon4 -- `msShutdown` is the one whose
    // target column also appears in the boolean map. The rest are LONGTEXT
    // and INTEGER, whose types no later step changes.
    //
    // Safe to run on a server that is already correct: enumToTinyint()
    // matches `enum('0','1')` exactly and skips anything else, so this is a
    // single information_schema read and no ALTER on a healthy database.
    // That idempotence is why the fix is a re-run of the shared helper
    // rather than a bespoke ALTER -- ADR 0028's three-statement rule (widen
    // to VARCHAR, normalize the values, narrow to TINYINT) is not
    // re-implemented here.
    function () {
        return Schema::enumToTinyint(
            ['multicastSessions' => ['msShutdown']]
        );
    },
];
// 401
$this->schema[] = [
    // `roles`.`rName` is declared UNIQUE by the manifest and is missing that
    // index on any server whose `roles` table was created by the 1.5
    // accesscontrol plugin, which did not declare one. Native RBAC adopted
    // the existing table rather than rebuilding it, so the gap came across
    // the upgrade intact and nothing since has restored it.
    //
    // DUPLICATES ARE RENAMED, NEVER DELETED, and that is the whole design.
    // Six tables reference `roles`.`rID` with ON DELETE CASCADE --
    // rolePermissions, roleUserAssoc, roleUserGroupAssoc, siteRoleGrants,
    // and the plugins' ldapGroupRoleAssoc and oidcGroupRoleAssoc. Deleting
    // one duplicate row would therefore silently remove that role's
    // permissions, its user and group assignments, its site grants and its
    // directory-group mappings. That is an access-control change, and a
    // schema step running unattended in the middle of an upgrade is the
    // worst possible place to make one. Renaming keeps every row and every
    // grant exactly as it was: nobody gains or loses access, and the admin
    // is left with two distinguishable roles to merge by hand if they want.
    //
    // The FIRST holder of a name keeps it, so anything resolving a role by
    // name still finds the row it finds today. (Nothing in core does -- every
    // reference is by rID -- but the plugins are a separate repository and
    // this costs nothing to guarantee.)
    //
    // COLLATION MATTERS HERE. `rName` is utf8mb3_general_ci, so the UNIQUE
    // index treats 'Admins' and 'admins' as the same value. The duplicate
    // search below is a plain GROUP BY for exactly that reason: it inherits
    // the column's own collation and so finds precisely the pairs the index
    // will reject. A binary or case-sensitive comparison would pass over
    // case-variant duplicates and leave ADD UNIQUE to fail with 1062.
    //
    // IT NEVER ABORTS THE UPGRADE. If anything still collides after the
    // renames -- a name too long to disambiguate, or a rename that lands on
    // a name someone already used -- the index is skipped and the reason is
    // logged, following schema step 332's precedent with the site tables. A
    // missing UNIQUE index means FOG carries on as it has for years; an
    // aborted schema update strands the admin on ?node=schema over data that
    // is entirely intact.
    function () {
        $has = function ($table) {
            $row = self::$DB->query(
                "SELECT COUNT(*) AS `n` FROM `information_schema`.`TABLES`"
                . " WHERE `TABLE_SCHEMA` = DATABASE()"
                . " AND LOWER(`TABLE_NAME`) = :t",
                [],
                [':t' => $table]
            )->fetch(\PDO::FETCH_ASSOC)->get();
            return (int)($row['n'] ?? 0) > 0;
        };
        if (!$has('roles')) {
            return true;
        }
        // Probed rather than guarded with IF NOT EXISTS, which ADD INDEX
        // does not have on the server versions FOG supports.
        $idx = self::$DB->query(
            "SELECT DISTINCT `INDEX_NAME` AS `i`"
            . " FROM `information_schema`.`STATISTICS`"
            . " WHERE `TABLE_SCHEMA` = DATABASE()"
            . " AND LOWER(`TABLE_NAME`) = 'roles' AND `NON_UNIQUE` = 0"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        foreach ((array)$idx as $row) {
            if (isset($row['i']) && 0 === strcasecmp($row['i'], 'rName')) {
                return true;
            }
        }

        // Every row except the lowest-id holder of each repeated name. The
        // GROUP BY runs in the column's own collation, so this is exactly
        // the set the UNIQUE index would reject.
        $dupes = self::$DB->query(
            "SELECT `r`.`rID` AS `id`, `r`.`rName` AS `name`"
            . " FROM `roles` `r`"
            . " JOIN (SELECT `rName`, MIN(`rID`) AS `keep` FROM `roles`"
            . " GROUP BY `rName` HAVING COUNT(*) > 1) `d`"
            . " ON `d`.`rName` = `r`.`rName`"
            . " WHERE `r`.`rID` <> `d`.`keep`"
            . " ORDER BY `r`.`rID`"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $renamed = [];
        foreach ((array)$dupes as $row) {
            if (!isset($row['id'], $row['name'])) {
                continue;
            }
            // rID is unique, so the suffix is too. Truncated to fit
            // varchar(255) from the LEFT, keeping the tail: the suffix is
            // what makes the value unique, so it is the part that must
            // survive.
            $suffix = sprintf(' (duplicate %d)', (int)$row['id']);
            $base = (string)$row['name'];
            $keep = 255 - strlen($suffix);
            if (strlen($base) > $keep) {
                $base = substr($base, 0, $keep);
            }
            $new = $base . $suffix;
            self::$DB->query(
                "UPDATE `roles` SET `rName` = :n WHERE `rID` = :id",
                [],
                [':n' => $new, ':id' => (int)$row['id']]
            );
            $renamed[] = sprintf('%s -> %s', $row['name'], $new);
        }

        if (count($renamed)) {
            error_log(
                sprintf(
                    'FOG schema 401: renamed %d duplicate role name(s) so'
                    . ' that the UNIQUE index the manifest declares could be'
                    . ' restored. No role, permission or assignment was'
                    . ' removed: %s',
                    count($renamed),
                    implode('; ', $renamed)
                )
            );
            Audit::record(
                [
                    'type' => 'schema.role.rename',
                    'subjectType' => 'schema',
                    'subjectId' => 401,
                    'summary' => sprintf(
                        /* translators: %d is a count of roles */
                        _('Renamed %d duplicate role name(s) to restore the'
                        . ' unique-name guarantee. No access was changed.'),
                        count($renamed)
                    ),
                    'detail' => json_encode(['renamed' => $renamed]),
                    'affectedCount' => count($renamed),
                    'renderable' => 1,
                ]
            );
        }

        // Re-checked rather than assumed: a rename can in principle collide
        // with a name that was already in use.
        $still = self::$DB->query(
            "SELECT COUNT(*) AS `n` FROM (SELECT `rName` FROM `roles`"
            . " GROUP BY `rName` HAVING COUNT(*) > 1) `d`"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        if ((int)($still['n'] ?? 0) > 0) {
            error_log(
                sprintf(
                    'FOG schema 401: %d role name(s) still collide after'
                    . ' renaming, so the UNIQUE index was NOT added. Nothing'
                    . ' was deleted and the upgrade continues; rename them by'
                    . ' hand and the index is added on the next update.',
                    (int)$still['n']
                )
            );
            return true;
        }

        self::$DB->query(
            "ALTER TABLE `roles` ADD UNIQUE KEY `rName` (`rName`)"
        );
        return true;
    },
];

// 402
$this->schema[] = [
    // Retire the persistentgroups plugin: drop its TRIGGER, then its row.
    //
    // ADR 0038 decision 14. The plugin existed to make a group assignment
    // stick to hosts added later, which core now does by resolving group
    // grants instead of copying them. Deleting the plugin's code is not
    // enough on its own, and that is the whole reason this step exists:
    //
    //   THE TRIGGER OUTLIVES THE PLUGIN. Bundled plugins live in
    //   packages/web/lib/plugins, which configureHttpd() rm -rf's and
    //   re-lays on every upgrade, so removing it from fog-plugins does
    //   remove the code. It does not touch the database. `persistentGroups`
    //   is an AFTER INSERT trigger on `groupMembers` and would go on firing
    //   forever, silently, with nothing left on disk to explain it.
    //
    // Unlike the stale `site` row step 399 cleaned up, this one is ACTIVE
    // rather than cosmetic. Verified 2026-09-01 against a clone of a live
    // 1.6 database, in a throwaway container: the trigger copies 13 `hosts`
    // columns -- `hostADPass` among them -- plus locationAssoc, printerAssoc,
    // snapinAssoc and moduleStatusByHost rows from a "template" host onto
    // every host added to a matching group, and creates snapinJobs and
    // snapinTasks rows, i.e. it queues software onto the machine.
    //
    // It is also substantially BROKEN on 1.6 data, which is worth knowing
    // when reading a bug report from before this ran. The printerAssoc and
    // moduleStatusByHost copies carry no ON DUPLICATE KEY UPDATE, so a
    // collision raises 1062 inside an AFTER INSERT trigger and rolls back
    // the INSERT INTO groupMembers that fired it -- the host is not added at
    // all. 85 of 86 hosts on the database tested carried moduleStatusByHost
    // rows, so for a group using the template convention nearly every add
    // failed with a database error.
    //
    // THE DROP IS UNCONDITIONAL, the row delete is not.
    //
    // DROP TRIGGER IF EXISTS is already a no-op on a server that never
    // installed the plugin, so gating it would only add a way to be wrong.
    // Deleting the `plugins` row is gated on evidence, on step 399's
    // pattern and for step 399's reason: step 334 tried to retire the `site`
    // row by reading `plugins`.`pLocation`, which 1.5 never wrote, so its
    // gate matched the empty string on every upgraded row and the DELETE was
    // unreachable. A step runs once and cannot be corrected in place.
    //
    // Here the evidence is the trigger itself -- a fact this database
    // carries, not a column whose value depends on which branch wrote it --
    // read BEFORE the drop, because afterward there is nothing to read.
    // No filesystem path is consulted, so an unmounted external plugin root
    // cannot make this decide anything.
    function () {
        $row = self::$DB->query(
            "SELECT COUNT(*) AS `n` FROM `information_schema`.`TRIGGERS`"
            . " WHERE `TRIGGER_SCHEMA` = DATABASE()"
            . " AND `TRIGGER_NAME` = 'persistentGroups'"
        )->fetch(\PDO::FETCH_ASSOC)->get();
        $hadTrigger = (int)($row['n'] ?? 0) > 0;

        // TRIGGER_SCHEMA = DATABASE(), never a literal. The plugin's own
        // location-copy branch hardcoded `table_schema = 'fog'` against
        // information_schema.tables, which is SERVER-global: it asked about
        // whatever database on the server happened to be named `fog` and
        // then wrote to its own. Both failure directions are reachable on a
        // box hosting two FOG databases, which is not hypothetical.
        self::$DB->query("DROP TRIGGER IF EXISTS `persistentGroups`");

        if ($hadTrigger) {
            self::$DB->query(
                "DELETE FROM `plugins` WHERE LOWER(`pName`) = 'persistentgroups'"
            );
        }
        return true;
    },
];

// 403
$this->schema[] = [
    // ADR 0038 decisions 1 and 4/5: the two tables that let a GROUP own a
    // grant, instead of a group assignment copying rows onto whichever hosts
    // happened to be members when a button was pressed.
    //
    // These are the declarative half of the split. `snapinAssoc` and
    // `printerAssoc` keep meaning exactly what they meant -- a HOST-direct
    // association -- and nothing already in them is migrated or touched
    // (decision 18). These tables start empty and nothing reads them until
    // the resolver ships, which is what makes this step reversible: drop
    // them and no data is lost, because no data was ever moved in.
    //
    // Why a group-side table at all, rather than a `gsaHostID`-style flag on
    // the existing ones: the whole defect is that a group grant has no
    // representation of its own. It exists today only as its side effects on
    // member hosts, so a host added later cannot see it and a host removed
    // keeps it. A row keyed by group is the smallest thing that makes the
    // grant a fact about the group.
    "CREATE TABLE IF NOT EXISTS `groupSnapinAssoc` ( "
    . "`gsaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`gsaGroupID` int(11) NOT NULL, "
    . "`gsaSnapinID` int(11) NOT NULL, "
    // Explicit, and explicitly NOT defaulted to 0 for everything the way
    // saSequence is. ADR 0038 decision 6: saSequence defaults to 0 and
    // Host::loadSnapins() orders by sequence alone, so every row sitting at 0
    // comes back in whatever order the engine chose. The resolver breaks that
    // tie on the association id, but the group side gets a real sequence from
    // the start so the ordering is an admin's decision rather than an
    // accident that has to be untangled later.
    . "`gsaSequence` int(11) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`gsaID`), "
    . "UNIQUE KEY `gsaGroupSnapin` (`gsaGroupID`,`gsaSnapinID`), "
    . "KEY `gsaSnapinID` (`gsaSnapinID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    "CREATE TABLE IF NOT EXISTS `groupPrinterAssoc` ( "
    . "`gpaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`gpaGroupID` int(11) NOT NULL, "
    . "`gpaPrinterID` int(11) NOT NULL, "
    // tinyint(1), NOT the varchar(2) printerAssoc.paIsDefault still carries.
    // A new boolean column added as anything else is what
    // tests/booleans-are-tinyint.test.php exists to refuse, and there is no
    // legacy value here to be compatible with.
    . "`gpaIsDefault` tinyint(1) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`gpaID`), "
    . "UNIQUE KEY `gpaGroupPrinter` (`gpaGroupID`,`gpaPrinterID`), "
    . "KEY `gpaPrinterID` (`gpaPrinterID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];

// 404
// The foreign keys for the two tables step 403 just created, per ADR 0031.
//
// Group 9. Like groups 7 and 8 it has nothing to migrate: tables created
// empty one step earlier cannot hold an orphan, so there is no sweep to
// sequence before the flip.
//
// Every column CASCADE, and the reasoning is the same on all four. A group
// grant is meaningless once either end of it is gone -- a deleted group has
// no grants, and a deleted snapin or printer cannot be granted. Leaving the
// row would silently offer a grant against an id that has since been reused,
// which on the printer side means the resolver hands a machine somebody
// else's printer.
$this->schema[] =
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(9);

        return true;
    };

// 405
$this->schema[] = [
    // ADR 0038 decision 6: the explicit order column, so that a rename never
    // changes what runs.
    //
    // The resolved order for a host is host-direct snapins first, then
    // group-granted ones with the GROUPS ordered by this column, then
    // `groupName`, then `groupID`. Ordering on name alone was rejected: an
    // admin renaming a group must not silently reorder installs on a thousand
    // machines, and there is no way to notice that they have.
    //
    // Defaulting every existing group to 0 is deliberate. It means an install
    // that never sets this behaves alphabetically, which is the answer an
    // admin can predict, and it makes this step pure addition -- nothing
    // reads the column until the resolver ships.
    "ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS "
    . "`groupOrder` int(11) NOT NULL DEFAULT 0",
    "ALTER TABLE `groups` ADD INDEX IF NOT EXISTS "
    . "`groupOrder` (`groupOrder`)",
];
// 406
$this->schema[] = [
    // GH-328: the ClamAV virus scan is removed.
    //
    // It has not worked on 1.6 for its whole life. FOS `bin/fog.av` posts
    // its findings to `service/av.php`, and that endpoint does not exist in
    // this tree -- 1.5 has it, 1.6 never carried it across. So the scan
    // boots, updates its definitions, runs, and throws every result away
    // against a 404. Nothing writes the `virus` table on 1.6 and nothing
    // reads it either: there is no Virus model, manager, report or page,
    // only the rows a 1.5 install left behind. What remained was two task
    // types (21 and 22) offering a scan that could not report.
    //
    // Id 9 is the original 1.x row, and step 33 truncated `taskTypes` and
    // reseeded it without one -- so no current server has it. It is named
    // here anyway because the delete costs nothing and a database restored
    // from a pre-33 dump would otherwise keep an entry for a feature this
    // release does not have.
    //
    // THE ROWS HAVE TO GO IN THIS ORDER. Both `tasks`.`taskTypeID` and
    // `scheduledTasks`.`stTaskTypeID` reference `taskTypes`.`ttID` ON DELETE
    // RESTRICT (ADR 0031, groups 6 and 5), and 1451 is not in Schema's
    // skippable list -- so deleting the parent first aborts the update on
    // any server that has ever queued a scan. Children first, parent last.
    //
    // WHAT IS LOST, said plainly. A scheduled scan can never run again, so
    // deleting it removes nothing an admin can still use. A `tasks` row is
    // history, and this destroys it -- but the run itself survives where the
    // project already decided run history lives: `taskLog` carries
    // `logTaskTypeName` as text, takes no constraint at all (ADR 0021), and
    // is written by TaskLog::recordState() on every state change. That is
    // the same argument ADR 0022 decision 3 used to retire `imagingLog`.
    // The `virus` table's rows are 1.5-era leftovers that 1.6 has had no way
    // to display since the upgrade.
    //
    // Menus need no code change: every task list is built from the
    // `taskTypes` table itself (FOGPageRender::taskTypeAccordion() reads it
    // through Route::getList('tasktype')), so removing the rows removes the
    // entries from the host page, the group page, the queue-task modal and
    // the API in one move.
    //
    // Counts are recorded rather than silently swallowed, following step
    // 401: an admin who upgrades should be able to find out what went.
    function () {
        $counts = [];
        foreach ([
            'scheduledTasks' => 'stTaskTypeID',
            'tasks' => 'taskTypeID',
        ] as $table => $column) {
            $row = self::$DB->query(
                "SELECT COUNT(*) AS `n` FROM `$table`"
                . " WHERE `$column` IN (9, 21, 22)"
            )->fetch(\PDO::FETCH_ASSOC)->get();
            $counts[$table] = (int)($row['n'] ?? 0);
            if ($counts[$table] > 0) {
                self::$DB->query(
                    "DELETE FROM `$table` WHERE `$column` IN (9, 21, 22)"
                );
            }
        }

        self::$DB->query(
            "DELETE FROM `taskTypes` WHERE `ttID` IN (9, 21, 22)"
        );

        $removed = $counts['tasks'] + $counts['scheduledTasks'];
        if ($removed > 0) {
            error_log(
                sprintf(
                    'FOG schema 406: removed the ClamAV virus scan (GH-328).'
                    . ' %d task(s) and %d scheduled task(s) referencing task'
                    . ' types 9, 21 and 22 were deleted so the task types'
                    . ' themselves could be. The runs remain in taskLog.',
                    $counts['tasks'],
                    $counts['scheduledTasks']
                )
            );
            Audit::record(
                [
                    'type' => 'schema.tasktype.remove',
                    'subjectType' => 'schema',
                    'subjectId' => 406,
                    'summary' => sprintf(
                        /* translators: %1$d tasks, %2$d scheduled tasks */
                        _('Removed the virus scan task types. %1$d task(s)'
                        . ' and %2$d scheduled task(s) were deleted with'
                        . ' them; their run history remains in the task'
                        . ' log.'),
                        $counts['tasks'],
                        $counts['scheduledTasks']
                    ),
                    'detail' => json_encode($counts),
                    'affectedCount' => $removed,
                    'renderable' => 1,
                ]
            );
        }

        return true;
    },
    // Neither read nor written on 1.6, and its only declared relationship
    // (vHostMAC -> hostMAC.hmMAC) was class 'poly' and applied nothing.
    Schema::dropTable('virus'),
];

// 407
$this->schema[] = [
    // ADR 0038 decision 3, revised: modules become the third declarative
    // grant, beside snapins and printers.
    //
    // The original decision kept them imperative on the grounds that a module
    // carries an enabled/disabled state a snapin does not, so two groups
    // granting the same module -- one enabled, one disabled -- would be a
    // contradiction with no correct answer. That argument did not survive
    // reading the code: nothing ever wrote `msState = 0`, two earlier schema
    // steps DELETE any that exist, and the client ignores the column outright,
    // so the disabled state it turned on was not reachable.
    //
    // The revision keeps the state AND makes it real, with an order of
    // precedence rather than a contradiction: the most specific writer wins.
    // A host row saying 0 is an explicit "not on this machine" and beats every
    // group grant. A host row saying 1 is a host-direct enable. NO row is the
    // host expressing nothing, which is what lets a group grant reach it.
    //
    // Empty on creation and nothing migrated, exactly as step 403 did for the
    // other two (decision 18). A group's module tab today is derived from its
    // members' own rows, so migrating it would be inventing grants out of a
    // display. Every host keeps precisely the modules it has.
    "CREATE TABLE IF NOT EXISTS `groupModuleAssoc` ( "
    . "`gmaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`gmaGroupID` int(11) NOT NULL, "
    . "`gmaModuleID` int(11) NOT NULL, "
    . "PRIMARY KEY (`gmaID`), "
    . "UNIQUE KEY `gmaGroupModule` (`gmaGroupID`,`gmaModuleID`), "
    . "KEY `gmaModuleID` (`gmaModuleID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];

// 408
// The foreign keys for the table step 407 just created, per ADR 0031.
//
// Group 10, and like groups 7, 8 and 9 it has nothing to sweep first: a table
// created empty one step earlier cannot hold an orphan.
//
// Both CASCADE. A grant is meaningless once either end is gone, and leaving
// the row would offer a grant against an id that has since been reused -- a
// host would silently gain whichever module inherited the number.
$this->schema[] =
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(10);

        return true;
    };

// 409
$this->schema[] = [
    // `msState` stops being decoration and starts being the precedence rule,
    // so it is converted to the boolean it always described. ADR 0028: a
    // boolean is tinyint(1), and tests/booleans-are-tinyint.test.php refuses
    // anything else for a column that holds one.
    //
    // A DIRECT MODIFY is correct here and the ENUM caution on step 368 does
    // not apply. That step's three-statement dance exists because converting
    // an ENUM straight to tinyint converts BY INDEX -- '0' becomes 1 and '1'
    // becomes 2, both truthy, silently. This column is already varchar(1), so
    // the conversion is by VALUE and '1' becomes 1.
    //
    // The UPDATE first, because varchar can hold things tinyint cannot. Every
    // row on every server should already read '1' -- that is the finding this
    // whole revision rests on -- but '' is what the column DEFAULTS to, so an
    // insert that omitted it would reach the ALTER as an empty string and
    // fail the upgrade. Anything that is not an explicit '0' is normalized to
    // '1', which is the meaning it has had since the column was created:
    // present means enabled.
    "UPDATE `moduleStatusByHost` SET `msState` = '1' WHERE `msState` <> '0'",
    // DEFAULT 1, not 0. A writer that forgets the column is asking for the
    // behavior this column has always had, and 0 is now the strongest
    // statement in the system -- it overrides every group. Defaulting to it
    // would let an omission silently switch a module off across a fleet.
    "ALTER TABLE `moduleStatusByHost` MODIFY `msState` tinyint(1) "
    . "NOT NULL DEFAULT 1",
];

// 410
$this->schema[] = [
    // ADR 0038: Power Management becomes the fourth grant.
    //
    // It was the last control on the group page that still FANNED OUT. Saving
    // a schedule wrote one `powerManagement` row per host that happened to be
    // a member at that instant, recorded nothing about where the rows came
    // from, and so could not be replayed: a host added afterward got no
    // schedule, a host removed kept one forever, and "Delete All" reached only
    // the current membership. The group page's own text said "to all hosts in
    // this group", which was true at the moment of the press and false by the
    // next membership change.
    //
    // THERE IS NO `gpmOndemand` COLUMN, and its absence is the design.
    // `powerManagement.pmOndemand` marks a row that is not a schedule at all
    // -- an immediate shutdown, reboot or wake, consumed and deleted on the
    // next client check-in. That is a TASK: it acts on the membership at the
    // moment you start it, which is exactly what a task should do and exactly
    // what a grant must not do. A grant of "shut down immediately" would fire
    // again for every host that joined the group afterward. Immediate actions
    // therefore stay a fan-out and move to the task surface; only the
    // SCHEDULE, which is a standing statement about the group, becomes a row
    // here.
    //
    // The unique key mirrors `powerManagement`.`cron` for the same reason it
    // exists there: insertBatch() UPSERTS, so saving an identical schedule
    // twice must be a no-op rather than a second row that fires the same
    // action a second time.
    //
    // Empty on creation and nothing migrated, exactly as steps 403 and 407 did
    // for the other three grants (decision 18). The rows on hosts today are
    // indistinguishable from ones an admin set per-host -- that is the whole
    // defect -- so there is nothing to migrate FROM. Every host keeps
    // precisely the schedules it has, and a group gains its grant when someone
    // sets one.
    "CREATE TABLE IF NOT EXISTS `groupPowerManagement` ( "
    . "`gpmID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`gpmGroupID` int(11) NOT NULL, "
    . "`gpmMin` varchar(50) NOT NULL DEFAULT '', "
    . "`gpmHour` varchar(50) NOT NULL DEFAULT '', "
    . "`gpmDom` varchar(50) NOT NULL DEFAULT '', "
    . "`gpmMonth` varchar(50) NOT NULL DEFAULT '', "
    . "`gpmDow` varchar(50) NOT NULL DEFAULT '', "
    . "`gpmAction` enum('shutdown','reboot','wol') NOT NULL, "
    . "PRIMARY KEY (`gpmID`), "
    . "UNIQUE KEY `gpmCron` "
    . "(`gpmGroupID`,`gpmMin`,`gpmHour`,`gpmDom`,`gpmMonth`,`gpmDow`,`gpmAction`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci "
    . "ROW_FORMAT=DYNAMIC",
];

// 411
// The foreign key for the table step 410 just created, per ADR 0031.
//
// Group 11, and like groups 7 through 10 it has nothing to sweep first: a
// table created empty one step earlier cannot hold an orphan.
//
// `satellite`, not `junction`, and CASCADE. It matches
// `powerManagement`.`pmHostID`, which is the same shape one level down: a
// schedule is wholly owned by the thing it schedules and has no meaning
// without it. Leaving the row behind would offer a schedule against a group
// id that has since been reused, and every host in the group that inherited
// the number would silently start shutting down.
$this->schema[] =
    function () {
        \FOG\Db\SchemaReconciler::applyConstraints(11);

        return true;
    };

// 412
$this->schema[] = [
    // U-Boot's `pxe get` fetches pxelinux.cfg/01-<mac> relative to the TFTP
    // root, and tftpd-hpa serves chrooted to that root (-s $tftpdirdst). Until
    // this step UbootTftpSync wrote under FOG_TFTP_PXE_KERNEL_DIR, which is the
    // HTTP-served kernel directory (<webroot>/service/ipxe/), outside the
    // chroot -- so the file existed and TFTP could never serve it (forums
    // topic 18229). The root is distro-dependent (/tftpboot, /var/lib/tftpboot,
    // /srv/tftp, ...) and only the installer knows it, so it publishes it as
    // TFTP_ROOT_DIR the same way it publishes TFTP_PXE_KERNEL_DIR; the
    // defined() guard is for a tree updated by git pull with a config.class.php
    // the installer has not regenerated yet.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_TFTP_ROOT_DIR','The directory the TFTP server serves from "
    . "(its chroot). U-Boot boards without wget fetch their "
    . "pxelinux.cfg/01-<mac> boot file from here.','"
    . (defined('TFTP_ROOT_DIR') ? TFTP_ROOT_DIR : '/tftpboot')
    . "','TFTP Server')",
];

// 413
$this->schema[] = [
    // #198: a booting machine can now be identified by what its firmware
    // reports as well as by its MAC. getHostItem() looks the four SMBIOS
    // fields up on EVERY boot.php request, and `inventory` had no index on
    // any of them -- the lookup was a table scan per boot. One key per field
    // the resolver filters on (SmbiosIdentity::FIELDS).
    //
    // Prefixed at 191 characters, not the full 250/255. 191 x 4 bytes fits
    // the 767-byte key limit of InnoDB's older row formats even under
    // utf8mb4, so the ALTER cannot be refused by whichever charset and row
    // format an old install's table still carries. No real identifier is
    // anywhere near that long, so the prefix costs nothing.
    "ALTER TABLE `inventory` "
    . "ADD KEY `iSystemUUID` (`iSystemUUID`(191)), "
    . "ADD KEY `iSysserial` (`iSysserial`(191)), "
    . "ADD KEY `iMbserial` (`iMbserial`(191)), "
    . "ADD KEY `iCaseasset` (`iCaseasset`(191))",
    // The switch that decides how far the firmware identity goes. Ships in
    // `log`: the MAC keeps deciding and every disagreement is written to
    // the error log, so a site learns what its vendors' firmware really
    // reports before any of it is trusted. `enforce` makes a unique SMBIOS
    // match win over the MAC; `off` ignores the values entirely. The first
    // attempt at this (2018) shipped straight to enforce, met MSI boards
    // that all report FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF, and was
    // reverted wholesale. This setting is what turns that into a toggle.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_HOST_IDENTIFY_SMBIOS','How much to trust the SMBIOS identity "
    . "(UUID, system serial, board serial, chassis asset tag) a booting "
    . "machine reports, next to its MAC address. Off: MAC only. Log: the MAC "
    . "decides and every disagreement is written to the web server error "
    . "log. Enforce: a unique firmware match wins over the MAC. Run Log "
    . "first and read the log before choosing Enforce.','log',"
    . "'General Settings')",
];

// 414
$this->schema[] = [
    // What FOG has decided about each file in the FOS boot directory.
    //
    // The filesystem stays the inventory: existence, size and mtime are read
    // live on every listing, so a file copied in by hand appears and one
    // deleted by hand disappears with nothing to reconcile. This table holds
    // only what the directory cannot tell us --
    //
    //   bfRole            the answer bootFileRole() read out of the bytes,
    //                     cached so a page render costs a stat rather than a
    //                     4KiB read of every file in the directory.
    //   bfKernelVersion   the banner the x86 setup header points at. Also
    //                     just a cache; it is re-readable at any time.
    //   bfReleaseTag      the FOS release. NOT a cache -- this one may be
    //                     unrecoverable. It lives in an extended attribute,
    //                     PHP has no xattr reader (the PECL extension is
    //                     absent everywhere and this codebase has never used
    //                     it), and `attr` is unavailable to the web user on
    //                     any SELinux-enforcing RHEL-family server, on a
    //                     mount without user_xattr, and wherever the package
    //                     was skipped. So it is stored the first time it can
    //                     be read at all and served from here afterward.
    //   bfPinned          "no pruner may delete this". Nothing on disk can
    //                     carry an admin's intent.
    //
    // bfSize and bfMtime are NOT the inventory -- they are the cache key.
    // A file whose stat has moved is re-read; one whose stat matches is
    // trusted.
    //
    // Its own table rather than globalSettings rows, for the reason step 397
    // gives for storageEpoch: the configuration page renders every row of
    // that table with no WHERE and `setting` is a routed class, so per-file
    // records there would be one careless edit away from changing what an
    // unrelated file means. Keyed on the filename because that is what every
    // caller has in hand -- a host's hostKernel column, a dropdown's posted
    // value, a pruner's directory entry. Content identity is bfChecksum's
    // job, and two names sharing a checksum are the same kernel twice.
    "CREATE TABLE IF NOT EXISTS `bootFile` ( "
    . "`bfID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`bfName` varchar(191) NOT NULL DEFAULT '', "
    . "`bfSize` bigint(20) NOT NULL DEFAULT 0, "
    . "`bfMtime` datetime DEFAULT NULL, "
    . "`bfChecksum` varchar(64) NOT NULL DEFAULT '', "
    . "`bfRole` varchar(20) NOT NULL DEFAULT 'unclassified', "
    . "`bfKernelVersion` varchar(191) NOT NULL DEFAULT '', "
    . "`bfReleaseTag` varchar(191) NOT NULL DEFAULT '', "
    . "`bfInspected` datetime DEFAULT NULL, "
    . "`bfPinned` tinyint(1) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`bfID`), "
    . "UNIQUE KEY `bfName` (`bfName`), "
    . "KEY `bfRole` (`bfRole`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];

// 415
$this->schema[] = [
    // Memtest86+ 8.10 replaces the 2013 Memtest86+ 5.01 ISO that memdisk
    // loaded. The new file boots on both legacy BIOS and UEFI, which the
    // memdisk chain never could (#321). Only a value still at the old
    // default is moved: a site that pointed this at its own file keeps it.
    "UPDATE `globalSettings` SET `settingValue`='mt86plus_x86_64' "
    . "WHERE `settingKey`='FOG_MEMTEST_KERNEL' "
    . "AND `settingValue`='memtest.bin'",
];

// 416
$this->schema[] = [
    // fog-agent enrollment (docs/design in the fog-agent repo, section 4;
    // wire contract in its docs/design/protocol-v1.md).
    //
    // An agent that has no certificate yet presents its firmware identity,
    // its MACs and a CSR. Nothing is issued until one of three approvals
    // happens: an admin clicks Approve, a valid enrollment token was
    // presented, or this server itself imaged the host within
    // FOG_AGENT_ENROLL_DEPLOY_WINDOW hours. Until then the request waits
    // here, verbatim, so that what gets signed on approval is exactly what
    // was presented and not a re-read of anything the agent could change in
    // between.
    //
    // One row per KEY (aeFingerprint is the sha256 of the SubjectPublicKeyInfo),
    // not per request: an agent repeats the identical request every few
    // minutes while it waits, and the repeat refreshes the row rather than
    // adding one. A denied key stays denied across repeats for the same
    // reason.
    //
    //   aeHostID     0 until the request is bound to a host. Set at approval,
    //                or immediately when the identity resolved to a host and
    //                the request is merely waiting for a click.
    //   aeIdentity   the SMBIOS tuple, smbios version and MAC list as the
    //                agent sent them, JSON. Kept raw: canonicalization is
    //                SmbiosIdentity's job at read time, the same as for boot.
    //   aeReason     why it is waiting: unknown-host, known-host-no-agent,
    //                rebind, identity-conflict. Shown to the admin.
    //   aeState      pending, issued, denied.
    //   aeCert       the issued leaf plus its chain, PEM. Filled at approval
    //                so the agent's next poll can collect it; cleared once
    //                collected so a database read does not hand out a
    //                certificate twice.
    "CREATE TABLE IF NOT EXISTS `agentEnrollment` ( "
    . "`aeID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`aeHostID` int(11) NOT NULL DEFAULT 0, "
    . "`aeFingerprint` varchar(64) NOT NULL DEFAULT '', "
    . "`aeCSR` text NOT NULL, "
    . "`aeIdentity` text NOT NULL DEFAULT '', "
    . "`aeHostname` varchar(191) NOT NULL DEFAULT '', "
    . "`aeOS` varchar(20) NOT NULL DEFAULT '', "
    . "`aeArch` varchar(20) NOT NULL DEFAULT '', "
    . "`aeAgentVersion` varchar(50) NOT NULL DEFAULT '', "
    . "`aeRemoteIP` varchar(45) NOT NULL DEFAULT '', "
    . "`aeReason` varchar(32) NOT NULL DEFAULT '', "
    . "`aeState` varchar(16) NOT NULL DEFAULT 'pending', "
    . "`aeCert` text NOT NULL DEFAULT '', "
    . "`aeCreated` datetime DEFAULT NULL, "
    . "`aeUpdated` datetime DEFAULT NULL, "
    . "`aeDecided` datetime DEFAULT NULL, "
    . "`aeDecidedBy` varchar(191) NOT NULL DEFAULT '', "
    . "`aeDecidedVia` varchar(16) NOT NULL DEFAULT '', "
    . "PRIMARY KEY (`aeID`), "
    . "UNIQUE KEY `aeFingerprint` (`aeFingerprint`), "
    . "KEY `aeState` (`aeState`), "
    . "KEY `aeHostID` (`aeHostID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Enrollment tokens: an admin's pre-approval, minted in the UI and baked
    // into an installer or an image's bootstrap file. Only the sha256 of the
    // token is stored, so a database read does not yield a usable token.
    // atUses counts down; -1 means unlimited until atExpires.
    "CREATE TABLE IF NOT EXISTS `agentEnrollToken` ( "
    . "`atID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`atName` varchar(191) NOT NULL DEFAULT '', "
    . "`atHash` varchar(64) NOT NULL DEFAULT '', "
    . "`atUses` int(11) NOT NULL DEFAULT 1, "
    . "`atExpires` datetime DEFAULT NULL, "
    . "`atCreatedBy` varchar(191) NOT NULL DEFAULT '', "
    . "`atCreated` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`atID`), "
    . "UNIQUE KEY `atHash` (`atHash`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // What the host knows about its agent. The fingerprint is the binding:
    // a client certificate whose key does not hash to this value is not this
    // host's agent, whatever its subject says. Same shape as the Secure Boot
    // enrollment columns above it in the host table.
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostAgentFingerprint` varchar(64) NOT NULL DEFAULT '', "
    . "ADD COLUMN `hostAgentNotAfter` datetime DEFAULT NULL, "
    . "ADD COLUMN `hostAgentVersion` varchar(50) NOT NULL DEFAULT '', "
    . "ADD COLUMN `hostAgentCheckin` datetime DEFAULT NULL, "
    . "ADD KEY `hostAgentFingerprint` (`hostAgentFingerprint`)",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_AGENT_ENROLL_DEPLOY_WINDOW','Hours after this server completes "
    . "a deploy to a host during which an agent presenting that host''s "
    . "firmware identity is enrolled without an admin approving it. The "
    . "deploy was the approval. 0 disables the shortcut and every "
    . "enrollment waits for a click or a token.','24','General Settings')",
];
// 417
$this->schema[] = [
    // fog-agent snapins (design 0001 section 7, protocol-v1 "Snapins").
    // A snapin's return-code table: one `code=class` per line, class one
    // of success, reboot, retry, failed. Empty means the installer
    // defaults (0 and 1707 success, 3010 and 1641 reboot, 1618 retry).
    // The server reads a task's exit code against it for the agent and
    // the legacy client alike, so an MSI that answers 3010 is a success
    // that needs a reboot instead of a failed job.
    "ALTER TABLE `snapins` ADD COLUMN IF NOT EXISTS `sReturnCodes` text NULL",
    // What a run came to: success, reboot, retry, failed, or when the
    // payload never ran, hash_mismatch, timeout, cannot_run. Beside the
    // raw exit code, which stays the program's own.
    "ALTER TABLE `snapinTasks` ADD COLUMN IF NOT EXISTS `stStatus` varchar(16) NOT NULL DEFAULT '' AFTER `stReturnCode`",
    // Installers put the useful line well past 250 characters; the agent
    // reports the last 4 KB of output.
    "ALTER TABLE `snapinTasks` MODIFY COLUMN `stReturnDetails` text NOT NULL",
];
// 418
$this->schema[] = [
    // fog-agent software management (design 0003). Software is desired
    // state, not a task: a package id plus a version policy, held on the
    // host by a package manager (Chocolatey first) and reported back with
    // the version the host actually has. Snapins stay as they are.
    "CREATE TABLE IF NOT EXISTS `software` ( "
    . "`swID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`swName` varchar(200) NOT NULL, "
    . "`swDesc` longtext NOT NULL DEFAULT '', "
    // Which package manager knows the package: choco now, others later
    // behind the same interface.
    . "`swBackend` varchar(16) NOT NULL DEFAULT 'choco', "
    . "`swPackage` varchar(255) NOT NULL, "
    // '' any version, 'latest' tracks the source, else an exact pin.
    . "`swVersion` varchar(64) NOT NULL DEFAULT '', "
    . "`swState` varchar(8) NOT NULL DEFAULT 'present', "
    . "`swSource` varchar(255) NOT NULL DEFAULT '', "
    . "`swArgs` varchar(255) NOT NULL DEFAULT '', "
    . "`swTimeout` int(11) NOT NULL DEFAULT 900, "
    // The same code=class table snapins carry (schema 417).
    . "`swReturnCodes` text NULL, "
    . "`swEnabled` tinyint(1) NOT NULL DEFAULT 1, "
    . "`swCreateDate` timestamp NOT NULL DEFAULT current_timestamp(), "
    . "`swCreator` varchar(50) NOT NULL DEFAULT '', "
    . "PRIMARY KEY (`swID`), "
    . "UNIQUE KEY `swName` (`swName`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Host-direct assignment, ordered like snapinAssoc.
    "CREATE TABLE IF NOT EXISTS `softwareAssoc` ( "
    . "`swaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`swaHostID` int(11) NOT NULL, "
    . "`swaSoftwareID` int(11) NOT NULL, "
    . "`swaSequence` int(11) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`swaID`), "
    . "UNIQUE KEY `swaHostSoftware` (`swaHostID`,`swaSoftwareID`), "
    . "KEY `swaSoftwareID` (`swaSoftwareID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Group grant, the ADR 0038 shape: a fact about the group, resolved
    // per host after its direct assignments and deduplicated.
    "CREATE TABLE IF NOT EXISTS `groupSoftwareAssoc` ( "
    . "`gswaID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`gswaGroupID` int(11) NOT NULL, "
    . "`gswaSoftwareID` int(11) NOT NULL, "
    . "`gswaSequence` int(11) NOT NULL DEFAULT 0, "
    . "PRIMARY KEY (`gswaID`), "
    . "UNIQUE KEY `gswaGroupSoftware` (`gswaGroupID`,`gswaSoftwareID`), "
    . "KEY `gswaSoftwareID` (`gswaSoftwareID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // What each host last reported per entry: one row, refreshed in
    // place, so the host's Software tab is a current picture rather than
    // a history.
    "CREATE TABLE IF NOT EXISTS `softwareStatus` ( "
    . "`sstID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`sstHostID` int(11) NOT NULL, "
    . "`sstSoftwareID` int(11) NOT NULL, "
    . "`sstInstalledVersion` varchar(64) NOT NULL DEFAULT '', "
    // converged, installed, upgraded, removed, failed, retry, reboot,
    // timeout, cannot_run.
    . "`sstStatus` varchar(16) NOT NULL DEFAULT '', "
    . "`sstReturnCode` int(11) NOT NULL DEFAULT 0, "
    . "`sstDetails` text NOT NULL DEFAULT '', "
    . "`sstChecked` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`sstID`), "
    . "UNIQUE KEY `sstHostSoftware` (`sstHostID`,`sstSoftwareID`), "
    . "KEY `sstSoftwareID` (`sstSoftwareID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // 417 widened stReturnDetails without a default, which a strict server
    // refuses on an INSERT that omits it (the legacy client's does).
    "ALTER TABLE `snapinTasks` MODIFY COLUMN `stReturnDetails` text NOT NULL DEFAULT ''",
    // How often a host re-checks its software set when nothing changed on
    // the server. Six hours: often enough to catch a removed package the
    // same working day, rare enough that choco is not a poll-loop cost.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_SOFTWARE_DRIFT_INTERVAL','Seconds between a host''s checks of "
    . "its software set when the set has not changed. The check runs "
    . "choco against every entry, so keep it hours, not minutes.',"
    . "'21600','FOG Client')",
];
// 419
$this->schema[] = [
    // 418 tried to seed the software module row at id 13, which step 223
    // had already given to powermanagement, so INSERT IGNORE dropped it on
    // every server that has that step. Seed by short name instead and let
    // the id be whatever is free; nothing keys on the number.
    "INSERT INTO `modules` (`name`, `short_name`, `description`) "
    . "SELECT 'Software', 'software', 'This setting will enable or disable "
    . "the software management module on this specific host. If the module "
    . "is globally disabled, this setting is ignored.' "
    . "FROM DUAL WHERE NOT EXISTS "
    . "(SELECT 1 FROM `modules` WHERE `short_name` = 'software')",
    // The global switch every module has (FOGBase::getGlobalModuleStatus).
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_CLIENT_SOFTWARE_ENABLED','This setting defines if the agent''s "
    . "software management module should be enabled on client computers. "
    . "It holds each host to its assigned software through the host''s "
    . "package manager. (Valid values: 0 or 1).','1','FOG Client')",
    // The two TEXT defaults 418 carries in its final form but not on a
    // server that ran it before they were added: a strict server refuses
    // an INSERT that omits a NOT NULL column with no default, and the
    // legacy client's snapin close-out omits stReturnDetails.
    "ALTER TABLE `snapinTasks` MODIFY COLUMN `stReturnDetails` text NOT NULL DEFAULT ''",
    "ALTER TABLE `softwareStatus` MODIFY COLUMN `sstDetails` text NOT NULL DEFAULT ''",
];
// 420
$this->schema[] = [
    // Chocolatey bootstrap (fog-agent design 0003 section 8). Off by
    // default: the agent runs the fetched script as SYSTEM, so an admin
    // opts in by naming the script, the community one or a copy they host.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_SOFTWARE_CHOCO_BOOTSTRAP_URL','URL of Chocolatey''s install "
    . "script for hosts that have software assigned but no Chocolatey. "
    . "Empty leaves such hosts reporting cannot run. The public script is "
    . "https://community.chocolatey.org/install.ps1; a copy on a server "
    . "you control works too. The agent runs it as SYSTEM.','','FOG Client')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_SOFTWARE_CHOCO_NUPKG_URL','Optional URL of the chocolatey "
    . ".nupkg the bootstrap script installs from (its chocolateyDownloadUrl), "
    . "for hosts with no route to the community feed. Empty uses the "
    . "script''s default.','','FOG Client')",
];
// 421
$this->schema[] = [
    // fog-agent inventory and installed-software reporting (design 0006).
    // What the agent last reported per host, per fact kind: hsSource is
    // part of identity because an OS package list enumerates the same
    // name under more than one manager, and hsVersion because two
    // versions can be installed at once and an upgrade is one version
    // removed, another added -- the history a report wants. hsRemovedAt
    // NULL is "installed now"; a row is never deleted, only closed out.
    "CREATE TABLE IF NOT EXISTS `hostSoftware` ( "
    . "`hsID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hsHostID` int(11) NOT NULL, "
    . "`hsName` varchar(255) NOT NULL, "
    . "`hsVersion` varchar(128) NOT NULL DEFAULT '', "
    . "`hsPublisher` varchar(255) NOT NULL DEFAULT '', "
    . "`hsSource` varchar(16) NOT NULL DEFAULT '', "
    . "`hsArch` varchar(16) NOT NULL DEFAULT '', "
    . "`hsInstallDate` date DEFAULT NULL, "
    . "`hsFirstSeen` datetime DEFAULT NULL, "
    . "`hsLastSeen` datetime DEFAULT NULL, "
    . "`hsRemovedAt` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hsID`), "
    . "UNIQUE KEY `hsHostNameSrcVer` (`hsHostID`,`hsName`,`hsSource`,`hsVersion`), "
    . "KEY `hsName` (`hsName`), "
    . "KEY `hsHostRemoved` (`hsHostID`,`hsRemovedAt`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // What the server last heard from a host, per fact kind (inventory,
    // software): the hash it holds and when. Doubles as "when did we last
    // hear facts from this host". A missing row is the want_* signal --
    // the agent has never successfully reported that kind.
    "CREATE TABLE IF NOT EXISTS `hostFactState` ( "
    . "`hfsID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hfsHostID` int(11) NOT NULL, "
    . "`hfsKind` varchar(16) NOT NULL, "
    . "`hfsHash` varchar(64) NOT NULL DEFAULT '', "
    . "`hfsUpdated` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hfsID`), "
    . "UNIQUE KEY `hfsHostKind` (`hfsHostID`,`hfsKind`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // The one gate for both: an installed-program list is mildly
    // sensitive, so a site can turn collection off. Hardware inventory
    // and software share it for v1 (design 0006 section 2, "Gate").
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_AGENT_INVENTORY_ENABLED','This setting defines if the agent "
    . "collects and reports hardware inventory and installed software for "
    . "its host. When disabled, the server never requests a report and "
    . "ignores one if it arrives. (Valid values: 0 or 1).','1','FOG Client')",
];

// 422
$this->schema[] = [
    // fog-agent user tracking as sessions (design 0008). One row per logon
    // with two ends, replacing the login/logout EVENT pairs in
    // `userTracking` -- which is not touched, and keeps working for 1.5
    // clients and the Activity page.
    //
    // The event log cannot answer "who is logged in now": a logout needs a
    // network round trip at the moment the machine is going away, so six of
    // eleven sessions on the lab server have no logout at all. Here the open
    // set is re-reported and whatever stops being reported is closed --
    // marked `inferred`, dated to husLastSeen, because "we never found out"
    // and "logged out at 11:54" are different facts.
    //
    // husSessionKey plus husStartedAt is identity: a second logon by the
    // same user is a distinct session, not an ambiguous second event.
    // husUserName and husDomain are separate and unmangled, unlike the
    // legacy table, which lowercases and strips the domain and so merges
    // CORP\jsmith with LAB\jsmith.
    "CREATE TABLE IF NOT EXISTS `hostUserSession` ( "
    . "`husID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`husHostID` int(11) NOT NULL, "
    . "`husSessionKey` varchar(191) NOT NULL DEFAULT '', "
    . "`husUserName` varchar(255) NOT NULL DEFAULT '', "
    . "`husDomain` varchar(255) NOT NULL DEFAULT '', "
    . "`husUserSID` varchar(191) NOT NULL DEFAULT '', "
    . "`husType` varchar(32) NOT NULL DEFAULT '', "
    . "`husState` varchar(32) NOT NULL DEFAULT '', "
    . "`husRemoteHost` varchar(255) NOT NULL DEFAULT '', "
    . "`husStartedAt` datetime NOT NULL, "
    . "`husEndedAt` datetime DEFAULT NULL, "
    . "`husEndReason` varchar(32) NOT NULL DEFAULT '', "
    . "`husLastSeen` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`husID`), "
    . "UNIQUE KEY `husHostKeyStart` (`husHostID`,`husSessionKey`,`husStartedAt`), "
    . "KEY `husHostOpen` (`husHostID`,`husEndedAt`), "
    . "KEY `husUserName` (`husUserName`), "
    . "KEY `husStartedAt` (`husStartedAt`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Whether to keep feeding the legacy table as well. On by default so a
    // fleet migrating to fog-agent sees no gap in the Activity page it
    // already uses; off for an estate that has fully migrated and does not
    // want the duplicate rows.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_USERTRACKING_COMPAT_WRITE','This setting defines if agent-reported "
    . "user sessions are also written to the legacy user tracking table, so "
    . "the Activity page keeps showing them while an estate migrates. Turn it "
    . "off once every client is a fog-agent. (Valid values: 0 or 1).','1','FOG Client')",
    // Sessions age out on their own clock: they are far coarser than the
    // event rows (one per logon, not two), so an estate may want to keep
    // them longer than the legacy table's.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_HOSTUSERSESSION_RETENTION_DAYS','This setting defines how many "
    . "days of agent-reported user sessions to keep. Zero keeps them "
    . "forever. (Valid values: a whole number of days).','365','FOG Audit')",
];

// 423
$this->schema[] = [
    // What directory each host is ACTUALLY a member of, and
    // where its computer object ACTUALLY sits (design 0009).
    //
    // The hosts table's hostADDomain and hostADOU are intent -- what an
    // admin typed into a form -- and nothing anywhere has ever recorded the
    // other half. So FOG cannot answer "which of my machines are not where
    // I think they are", and the legacy client made that worse by never
    // comparing the OU at all: it short-circuits on "already joined to the
    // target domain" and reads the OU only as lpAccountOU at the initial
    // join, so editing a host's OU does nothing, forever, silently.
    //
    // One row per host, not a history: this is current state, so it is
    // replaced in place and needs no retention entry. hdComputerDN is the
    // load-bearing column -- a server-side LDAP Modify DN needs the exact
    // object, and having the machine report its own DN means the server
    // never has to search by name and guess between duplicates.
    //
    // hdObservedAt is when this membership was last REPORTED, not when it
    // was last confirmed true. The agent hash-gates the block, so an
    // unchanged membership is never sent and this column would otherwise
    // claim a freshness nobody checked. "Is this still true" is answered by
    // the host's own hostAgentCheckin, which the report shows beside it.
    "CREATE TABLE IF NOT EXISTS `hostDirectory` ( "
    . "`hdID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hdHostID` int(11) NOT NULL, "
    . "`hdJoined` tinyint(1) NOT NULL DEFAULT 0, "
    . "`hdKind` varchar(32) NOT NULL DEFAULT '', "
    . "`hdDomain` varchar(255) NOT NULL DEFAULT '', "
    . "`hdNetbios` varchar(64) NOT NULL DEFAULT '', "
    . "`hdComputerDN` varchar(1024) NOT NULL DEFAULT '', "
    . "`hdMachineAccount` varchar(255) NOT NULL DEFAULT '', "
    . "`hdSite` varchar(255) NOT NULL DEFAULT '', "
    . "`hdObservedAt` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hdID`), "
    . "UNIQUE KEY `hdHostID` (`hdHostID`), "
    . "KEY `hdDomain` (`hdDomain`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];

// 424
$this->schema[] = [
    // Design 0009 section 5: FOG moves a computer object between OUs itself,
    // with one LDAP Modify DN, instead of asking the machine to leave the
    // domain and rejoin -- which is what an admin is reduced to today, and
    // which costs the object its password and, if it is recreated, its SID.
    //
    // Proven against a real DC before these columns existed: a service
    // account delegated create/delete-child of computer objects on ONE
    // subtree moved an object between two OUs under it, and was refused
    // ("Insufficient access") when it tried to move the same object out of
    // that subtree. That refusal is the whole security argument for giving
    // FOG an account of its own rather than a domain admin.
    //
    // Named for the ATTEMPT, not the move. This stamp is written whenever
    // placement consults the directory about a host -- which is also how a
    // host that cannot report its own DN is kept off an every-poll LDAP
    // search -- so a name like hdMovedAt would say a move happened on every
    // occasion nothing did.
    "ALTER TABLE `hostDirectory` "
    . "ADD COLUMN `hdPlacementAt` datetime DEFAULT NULL, "
    . "ADD COLUMN `hdPlacementError` varchar(255) NOT NULL DEFAULT ''",
    // Off until it is configured. Placement WRITES to the directory, so it
    // must never begin working because someone upgraded.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_PLACEMENT_ENABLED','This setting defines if FOG moves "
    . "a host\\'s computer object into the OU set on the host, using the "
    . "directory account below. Off by default: this writes to your "
    . "directory. (Valid values: 0 or 1).','0','FOG Directory')",
    // ldaps:// or an ldap:// that will be promoted with StartTLS. A bind
    // carries the credential, and Active Directory refuses a simple bind on
    // a cleartext connection anyway ("Strong(er) authentication required").
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_LDAP_URI','This setting defines the directory server "
    . "FOG connects to when placing computer objects, as a URI. Use "
    . "ldaps://dc.example.com -- a host name, not an address, or the "
    . "server\\'s certificate cannot be verified. (Valid values: an LDAP "
    . "URI).','','FOG Directory')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_BIND_DN','This setting defines the account FOG binds "
    . "as to move computer objects, as a userPrincipalName or a full DN. It "
    . "needs create-child and delete-child of computer objects on the "
    . "subtree holding them, and nothing else. (Valid values: a UPN or "
    . "DN).','','FOG Directory')",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_BIND_PASSWORD','This setting defines the password for "
    . "the directory account above. Stored encrypted. (Valid values: a "
    . "password).','','FOG Directory')",
    // The search base for the fallback when a host could not report its own
    // DN -- no Linux join tool exposes one, so the server looks the object
    // up by its machine account name instead.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_BASE_DN','This setting defines where FOG searches for "
    . "a computer object when the host could not report its own "
    . "distinguished name, which is normal on Linux. (Valid values: a base "
    . "DN such as DC=example,DC=com).','','FOG Directory')",
    // A private CA is the norm for a directory, and refusing to verify the
    // certificate would make the TLS above decorative.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_DIRECTORY_CA_CERT','This setting defines the path to the CA "
    . "certificate that signed your directory server\\'s certificate. Leave "
    . "empty to use the system trust store. (Valid values: a file "
    . "path).','','FOG Directory')",
];

// 425
$this->schema[] = [
    // Step 424 described FOG_DIRECTORY_BIND_PASSWORD as "Stored encrypted".
    // It is not, and saying so is worse than saying nothing: an admin who
    // believes a secret is encrypted at rest will treat a database dump as
    // safe to hand over.
    //
    // FOG has no key store. aesencrypt() takes a key and PUTS IT IN THE
    // CIPHERTEXT (`iv|data|key`), and aesdecrypt() returns anything without a
    // `|` unchanged -- which is why the LDAP plugin's bind password has always
    // been stored exactly as typed. This setting behaves the same way, so the
    // description now says what is true and points at the mitigation that is
    // real: delegate the account narrowly, so what the row is worth to an
    // attacker is bounded by what the account can do.
    "UPDATE `globalSettings` SET `settingDesc` = 'This setting defines the "
    . "password for the directory account above. Stored in the database as "
    . "typed -- FOG has no key store, so treat a database dump as disclosing "
    . "it, and delegate the account to one subtree rather than granting it "
    . "domain rights. (Valid values: a password).' "
    . "WHERE `settingKey` = 'FOG_DIRECTORY_BIND_PASSWORD'",
];

// 426
$this->schema[] = [
    // Design 0010: FOG has never recorded what printers a machine actually
    // has. Both legacy platform managers had a GetPrinters() and neither
    // ever transmitted the result -- all three call sites are local
    // decisions inside PrinterManager.cs -- so "did the printer I assigned
    // actually install?" has had no answer since the feature shipped.
    //
    // Two tables rather than one. hostSpooler is the per-host anchor: which
    // print subsystem the machine runs, and when it last said so. It exists
    // separately from hostPrinter because a machine with CUPS and no queues
    // has REPORTED, and a report that could only see hostPrinter rows would
    // show that host as never having answered -- which is precisely the
    // invisible-absence failure design 0010 section 6 is built to avoid.
    //
    // hostFactState already records the same "when did this host last
    // report kind X", but that table is the poll's hash cache. A report
    // built on it would couple an admin-facing page to the protocol's
    // internal bookkeeping, and would break the next time the gate changes.
    "CREATE TABLE IF NOT EXISTS `hostSpooler` ( "
    . "`hspID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hspHostID` int(11) NOT NULL, "
    . "`hspSubsystem` varchar(16) NOT NULL DEFAULT '', "
    . "`hspObservedAt` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hspID`), "
    . "UNIQUE KEY `hspHostID` (`hspHostID`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // One row per queue observed on a host.
    //
    // hpURI is the load-bearing column and the whole of design 0010 section
    // 2: both spoolers already describe a printer as a device URI plus a
    // driver, so recording the URI is what lets a Windows row and a CUPS row
    // for the same physical device be recognized as the same device. FOG's
    // pConfig could never do that -- it named a code path, not a printer.
    //
    // hpDriver empty is a real value meaning driverless (IPP Everywhere),
    // which FOG's existing model has no way to express at all.
    "CREATE TABLE IF NOT EXISTS `hostPrinter` ( "
    . "`hpID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hpHostID` int(11) NOT NULL, "
    . "`hpName` varchar(255) NOT NULL DEFAULT '', "
    . "`hpURI` varchar(1024) NOT NULL DEFAULT '', "
    . "`hpDriver` varchar(255) NOT NULL DEFAULT '', "
    . "`hpDefault` tinyint(1) NOT NULL DEFAULT 0, "
    . "`hpShared` tinyint(1) NOT NULL DEFAULT 0, "
    . "`hpObservedAt` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hpID`), "
    . "UNIQUE KEY `hpHostName` (`hpHostID`,`hpName`), "
    . "KEY `hpName` (`hpName`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Where a failed install gets to live. Today a printer that will not
    // install produces nothing an admin can see: the client retries the same
    // thing every poll, forever, silently. paAppliedAt is named for the
    // ATTEMPT, not the success -- the hdPlacementAt lesson from step 424.
    "ALTER TABLE `printerAssoc` "
    . "ADD COLUMN `paAppliedAt` datetime DEFAULT NULL, "
    . "ADD COLUMN `paError` varchar(255) NOT NULL DEFAULT ''",
    // paIsDefault is a varchar(2) holding a boolean; groupPrinterAssoc's
    // gpaIsDefault is a tinyint(1) holding the same idea. Same concept, two
    // types, because they were added years apart.
    //
    // The UPDATE has to come first. The column holds '' on every row nobody
    // ever set, and MariaDB in strict mode refuses to convert '' to an
    // integer -- so a bare MODIFY fails the upgrade on essentially every
    // existing install rather than on none of them.
    "UPDATE `printerAssoc` SET `paIsDefault`='0' "
    . "WHERE `paIsDefault` NOT IN ('0','1')",
    "ALTER TABLE `printerAssoc` "
    . "MODIFY COLUMN `paIsDefault` tinyint(1) NOT NULL DEFAULT 0",
    // The pre-allocated spare columns. `plugins` had the same pAnon1-pAnon5
    // and they were renamed into real columns (pIcon, pRunfile, pLocation)
    // through schema-expected.php's `renames` block; the printer ones were
    // never claimed by anything, in ten years. Verified across the whole
    // tree before dropping: the only readers were the two Items field maps
    // and four hidden DataTables export columns, all updated in this change.
    "ALTER TABLE `printerAssoc` "
    . "DROP COLUMN `paAnon1`, DROP COLUMN `paAnon2`, DROP COLUMN `paAnon3`, "
    . "DROP COLUMN `paAnon4`, DROP COLUMN `paAnon5`",
    "ALTER TABLE `printers` "
    . "DROP COLUMN `pAnon2`, DROP COLUMN `pAnon3`, DROP COLUMN `pAnon4`, "
    . "DROP COLUMN `pAnon5`",
];

// 427
$this->schema[] = [
    // Design 0010 section 2: a printer is a device URI and a driver, which
    // is how both spoolers already describe one. This is the column that
    // makes a printer row portable -- the same physical device is a TCP/IP
    // port on Windows and a socket:// device URI on CUPS, and until they are
    // written the same way nothing can tell they are the same printer.
    //
    // It is also the only way to express a DRIVERLESS printer (IPP
    // Everywhere), where the device describes its own capabilities and no
    // driver file exists. FOG's model has pDefFile and pModel and no way to
    // say "neither", which for a lot of estates is now the common case.
    //
    // DELIBERATELY NOT BACKFILLED, and this is a change from what design
    // 0010 section 7 first proposed. Deriving pConfig/pIP/pPort into a
    // stored URI once, on upgrade, bakes the derivation in: pPort is a
    // longtext that has held whatever an admin typed for a decade, so some
    // rows will derive wrong, and a stored wrong answer has to be found and
    // corrected by hand on every install. Items\Printer::uri() derives on
    // read instead, so this column holds only what an admin explicitly set,
    // an empty one keeps following the type-specific fields, and fixing the
    // derivation fixes every printer at once.
    "ALTER TABLE `printers` "
    . "ADD COLUMN `pURI` varchar(1024) NOT NULL DEFAULT ''",
];

// 428
$this->schema[] = [
    // Design 0009 section 6: the agent joins a machine to the domain the
    // host record asks for, and what happened is recorded here.
    //
    // These two columns are the whole reason the join is safe to automate.
    // A join that fails on a bad password is a FAILED AUTHENTICATION
    // against somebody's domain controller, and without a stamp to hold a
    // cooldown against it is one per host per poll -- which is how a
    // service account with a lockout policy gets locked out, taking every
    // other host's join with it. `hdJoinAt` is what
    // Agent\DirectoryJoin::RETRY_AFTER reads.
    //
    // Named for the ATTEMPT, like hdPlacementAt beside it: this is stamped
    // whenever the agent acted, so a name like hdJoinedAt would claim a
    // join happened on every occasion one did not.
    //
    // Deliberately separate from hdPlacementAt/hdPlacementError rather than
    // reusing them. They are different operations by different actors --
    // the machine joins, the server moves -- and a report that showed one
    // error against both would be a report that lies about which half is
    // broken.
    "ALTER TABLE `hostDirectory` "
    . "ADD COLUMN `hdJoinAt` datetime DEFAULT NULL, "
    . "ADD COLUMN `hdJoinError` varchar(255) NOT NULL DEFAULT ''",
];

// 429
$this->schema[] = [
    // Design 0011 section 3: which links a host is actually on.
    //
    // FOG has never recorded a host's interfaces. `hosts.hostIP` is
    // whatever the host last resolved to -- one address, no prefix, no
    // notion of which of several interfaces it came from -- so "which
    // machines share a link with host 41" has not been a question this
    // server could answer, and that question is the entire basis of the
    // wake relay: a magic packet is a link-layer broadcast, and FOG can
    // only send one from a machine it owns.
    //
    // hnNetwork is the address masked to hnPrefix, stored rather than
    // computed. Two hosts are on the same link when both columns match,
    // which is an index lookup; the honest alternative,
    // `INET_ATON(hnIPv4) & mask`, is a full scan on every wake.
    //
    // One row per host per interface ADDRESS, not per interface: an
    // interface with two addresses is on two links and can broadcast on
    // both. Replaced in place, not a history -- this is current state.
    //
    // hnObservedAt is when the interfaces were last REPORTED, not when
    // they were last confirmed. The agent hash-gates the block, so an
    // unchanged set is never sent; "is this still true" is answered by
    // the host's own hostAgentCheckin, which is also what says whether
    // the machine is awake enough to relay anything.
    "CREATE TABLE IF NOT EXISTS `hostNetwork` ( "
    . "`hnID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`hnHostID` int(11) NOT NULL, "
    . "`hnName` varchar(255) NOT NULL DEFAULT '', "
    . "`hnMAC` varchar(17) NOT NULL DEFAULT '', "
    . "`hnIPv4` varchar(15) NOT NULL DEFAULT '', "
    . "`hnPrefix` tinyint(3) unsigned NOT NULL DEFAULT 0, "
    . "`hnNetwork` varchar(15) NOT NULL DEFAULT '', "
    . "`hnBroadcast` varchar(15) NOT NULL DEFAULT '', "
    . "`hnUp` tinyint(1) NOT NULL DEFAULT 0, "
    . "`hnWireless` tinyint(1) NOT NULL DEFAULT 0, "
    . "`hnObservedAt` datetime DEFAULT NULL, "
    . "PRIMARY KEY (`hnID`), "
    . "UNIQUE KEY `hnHostAddress` (`hnHostID`,`hnName`,`hnIPv4`), "
    . "KEY `hnLink` (`hnNetwork`,`hnPrefix`), "
    . "KEY `hnMAC` (`hnMAC`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
];

// 430
$this->schema[] = [
    // Design 0011: one row per (machine to wake, machine asked to send it).
    //
    // A wake ordered now cannot be relayed now -- the neighboring agent
    // finds out when it next polls -- so the request has to be written
    // down somewhere, and FOG has nowhere. That is what this table is.
    //
    // Fanning out to SEVERAL senders is deliberate: a magic packet is one
    // UDP datagram, sending three costs nothing, and the alternative is a
    // wake that silently does nothing because the single chosen sender
    // went to sleep between the poll and the send.
    //
    // It is also the first time FOG can say anything at all about whether
    // a wake happened. The existing path is fire and forget: a machine
    // that stays asleep is indistinguishable from a packet that never
    // left the building. Here "three machines were asked and all three
    // said they sent it" is a row an admin can read.
    //
    // awExpiresAt is what keeps a wake from being a standing instruction.
    // A machine that comes back a week later must not be told to broadcast
    // for a wake somebody ordered last Tuesday.
    "CREATE TABLE IF NOT EXISTS `agentWake` ( "
    . "`awID` int(11) NOT NULL AUTO_INCREMENT, "
    . "`awTargetID` int(11) NOT NULL, "
    . "`awSenderID` int(11) NOT NULL, "
    . "`awRequestedAt` datetime DEFAULT NULL, "
    . "`awExpiresAt` datetime DEFAULT NULL, "
    . "`awStatus` varchar(16) NOT NULL DEFAULT 'pending', "
    . "`awPackets` int(11) NOT NULL DEFAULT 0, "
    . "`awDetail` varchar(255) NOT NULL DEFAULT '', "
    . "`awReportedAt` datetime DEFAULT NULL, "
    . "`awRequestedBy` varchar(255) NOT NULL DEFAULT '', "
    . "PRIMARY KEY (`awID`), "
    . "KEY `awSenderStatus` (`awSenderID`,`awStatus`), "
    . "KEY `awTargetID` (`awTargetID`), "
    . "KEY `awExpiresAt` (`awExpiresAt`) "
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC",
    // Off by default. This asks one customer machine to put traffic on the
    // network on behalf of another, which is a thing an estate owner opts
    // into rather than discovers after an upgrade.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_AGENT_WAKE_RELAY_ENABLED','This setting defines if FOG may ask "
    . "an enrolled agent to send a Wake-on-LAN packet for another FOG host "
    . "on the same subnet. This reaches subnets that have no FOG server or "
    . "storage node on them, which cannot be woken otherwise. Off by "
    . "default. (Valid values: 0 or 1).','0','FOG Agent')",
];

// 431
$this->schema[] = [
    // Display Manager goes, and its table with it.
    //
    // The module reset a client's screen resolution to a fixed size at
    // logoff and at startup. That was a reasonable thing for a lab in 2010
    // and it is the wrong layer now: Windows has honored the per-monitor
    // EDID-preferred mode by itself for a decade, a fixed resolution pushed
    // over the top of it is wrong on every machine whose panel is not the
    // size the setting names, and a laptop that docks changes its answer
    // twice a day. Nothing in the rebuilt agent implements it and nothing
    // will; the answer to "my screens are wrong" is not a FOG setting.
    //
    // This is the greenfog removal (step 375) repeated with one difference:
    // Display Manager owns a table, and the table goes too.
    //
    // That is a deliberate, irreversible loss of the per-host `hssWidth`,
    // `hssHeight` and `hssRefresh` an admin configured. Keeping the rows
    // was considered and rejected. Every consumer is removed in this same
    // commit -- the client endpoint, the host card, the mass-edit field,
    // Host::getDispVals()/setDisp(), Group::setDisp() -- so keeping the
    // table would mean keeping `HostScreenSetting`, its manager, its
    // `hostscreensetting` REST route, its Authorization mapping and its
    // foreign key alive to serve data that nothing writes and nothing
    // honors. Step 375 named that failure exactly: a setting that lies is
    // worse than no setting, and an API route reporting a resolution the
    // fleet does not apply is a setting that lies.
    //
    // Ordered so nothing ever references something already gone: the
    // per-host module answers first, then the module row, then the four
    // globalSettings rows, then the table. msModuleID is a VARCHAR and step
    // 34 seeded these with the short name before a later step rewrote them
    // to the numeric id, so a server upgraded across that boundary can hold
    // either spelling and both are matched -- the same care step 375 took.
    //
    // The seed steps that created all of this are deliberately NOT edited.
    // schema.php is a replay log; step 326 set that precedent and step 375
    // followed it. A fresh install creates these and removes them one step
    // later, which costs nothing and keeps the history readable.
    "DELETE FROM `moduleStatusByHost` "
    . "WHERE `msModuleID` IN ('3', 'displaymanager')",
    "DELETE FROM `modules` WHERE `short_name` = 'displaymanager'",
    "DELETE FROM `globalSettings` WHERE `settingKey` IN ("
    . "'FOG_CLIENT_DISPLAYMANAGER_ENABLED',"
    . "'FOG_CLIENT_DISPLAYMANAGER_X',"
    . "'FOG_CLIENT_DISPLAYMANAGER_Y',"
    . "'FOG_CLIENT_DISPLAYMANAGER_R')",
    Schema::dropTable('hostScreenSettings'),
];

// 432
$this->schema[] = [
    // Auto Log Out gets a configurable warning, and loses a setting that
    // has never done anything.
    //
    // FOG_CLIENT_AUTOLOGOFF_WARN is how long the user is told before they
    // are logged off. The legacy .NET client baked that countdown in; the
    // rebuilt agent (design 0014) takes it from here, and 0 means log the
    // user off with no warning at all -- legal, and what a kiosk wants.
    // Sixty seconds is the default because it is long enough to notice and
    // short enough that the machine is actually freed.
    //
    // FOG_CLIENT_AUTOLOGOFF_BGIMAGE goes. It named the 300x300 background
    // of the .NET client's countdown window, and there is no countdown
    // window any more: the agent is a service in session 0, which has had
    // no visible desktop since Vista, so it warns through WTSSendMessage --
    // rendered by winlogon inside the user's own session, and not something
    // an image can be attached to. Nothing has read this setting since the
    // legacy client stopped shipping, and the FOG Configuration page never
    // rendered it, so it is a row that promises a thing FOG cannot do. That
    // is the same defect step 375 removed FOG_CLIENT_GREENFOG_ENABLED for
    // and step 326 removed FOG_PLUGINSYS_DIR for: a setting that lies is
    // worse than no setting.
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_CLIENT_AUTOLOGOFF_WARN','This setting defines how many seconds "
    . "before an automatic log out the user is warned. 0 logs the user out "
    . "with no warning. The warning is a message box shown in the users own "
    . "session; moving the mouse or pressing a key cancels the log out.',"
    . "'60','FOG Client - Auto Log Off')",
    "DELETE FROM `globalSettings` "
    . "WHERE `settingKey` = 'FOG_CLIENT_AUTOLOGOFF_BGIMAGE'",
];
