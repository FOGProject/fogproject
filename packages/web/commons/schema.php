<?php
/**
 * Schema layout for creating the database.
 *
 * PHP version 5
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Schema layout for creating the database.
 *
 * @category Redirect
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
$tmpSchema = self::getClass('Schema');
self::$DB->query(Schema::useDatabaseQuery());
// 0
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `groups` ('
    . '`groupID` INT(11) NOT NULL auto_increment,'
    . '`groupName` VARCHAR(50) NOT NULL,'
    . '`groupDesc` LONGTEXT NOT NULL,'
    . '`groupDateTime` DATETIME NOT NULL,'
    . '`groupCreateBy` VARCHAR(50) NOT NULL,'
    . '`groupBuilding` INT(11) NOT NULL,'
    . 'PRIMARY KEY (`groupID`),'
    . 'KEY `new_index` (`groupName`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `history` ('
    . '`hID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`hText` LONGTEXT NOT NULL,'
    . '`hUser` VARCHAR(200) NOT NULL,'
    . '`hTime` DATETIME NOT NULL,'
    . '`hIP` VARCHAR(50) NOT NULL,'
    . 'PRIMARY KEY (`hID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `schemaVersion` ('
    . '`vID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`vValue` INT(11) NOT NULL,'
    . 'PRIMARY KEY  (`vID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `supportedOS` ('
    . '`osID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
    . '`osName` VARCHAR(150) NOT NULL,'
    . '`osValue` int(10) unsigned NOT NULL,'
    . 'PRIMARY KEY  (`osID`),'
    . 'KEY `new_index` (`osValue`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `users` ('
    . '`uId` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`uName` VARCHAR(40) NOT NULL,'
    . '`uPass` VARCHAR(50) NOT NULL,'
    . '`uCreateDate` DATETIME NOT NULL,'
    . '`uCreateBy` VARCHAR(40) NOT NULL,'
    . 'PRIMARY KEY (`uId`),'
    . 'KEY `new_index` (`uName`),'
    . 'KEY `new_index1` (`uPass`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `users` VALUES ('','fog', MD5('password'), NOW(), '')",
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Windows XP', '1')",
    "INSERT IGNORE INTO `schemaVersion` VALUES ('', '1')"
);
// 2
$this->schema[] = array(
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Windows Vista', '2')",
    "UPDATE `schemaVersion` SET vValue='2'",
);
// 3
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `snapinJobs` ('
    . '`sjID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`sjHostID` INT(11) NOT NULL,'
    . '`sjCreateTime` DATETIME NOT NULL,'
    . 'PRIMARY KEY (`sjID`),'
    . 'KEY `new_index` (`sjHostID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET vValue='3'",
);
// 4
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `multicastSessionsAssoc` ('
    . '`msaID` INT(11) NOT NULL AUTO_INCREMENT,'
    . '`msID` INT(11) NOT NULL,'
    . '`tID` INT(11) NOT NULL,'
    . 'PRIMARY KEY  (`msaID`),'
    . 'KEY `new_index` (`msID`),'
    . 'KEY `new_index1` (`tID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` set vValue='4'",
);
// 5
$this->schema[] = array(
    'ALTER TABLE `images`'
    . 'ADD COLUMN `imageDD` VARCHAR(1) NOT NULL AFTER `imageSize`,'
    . 'ADD INDEX `new_index2` (`imageDD`)',
    "UPDATE `supportedOS` SET `osName`='Windows 2000/XP' WHERE `osValue`='1'",
    "INSERT IGNORE INTO `supportedOS` VALUES ('', 'Other', '99')",
    'ALTER TABLE `multicastSessions`'
    . 'CHANGE `msAnon1` `msIsDD` VARCHAR(1) NOT NULL',
    "UPDATE `schemaVersion` SET vValue='5'",
);
// 7
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET `vValue`='6'",
);
// 8
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . '`pIP` VARCHAR(20) NOT NULL,'
    . '`pAnon2` VARCHAR(10) NOT NULL,'
    . '`pAnon3` VARCHAR(10) NOT NULL,'
    . '`pAnon4` VARCHAR(10) NOT NULL,'
    . '`pAnon5` VARCHAR(10) NOT NULL,'
    . 'PRIMARY KEY (`pID`),'
    . 'INDEX `new_index1`(`pModel`),'
    . 'INDEX `new_index2`(`pAlias`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `clientUpdates` ('
    . '`cuID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`cuName` VARCHAR(200) NOT NULL,'
    . '`cuMD5` VARCHAR(100) NOT NULL,'
    . '`cuType` VARCHAR(3) NOT NULL,'
    . '`cuFile` LONGBLOB NOT NULL,'
    . 'PRIMARY KEY (`cuID`),'
    . 'INDEX `new_index` (`cuName`),'
    . 'INDEX `new_index1`(`cuType`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` SET vValue='7'",
);
// 8
$this->schema[] = array(
    "INSERT IGNORE INTO `supportedOS` (`osName`, `osValue`) VALUES "
    . "('Windows 98','3'),"
    . "('Windows (other)','4'),"
    . "('Linux','50')",
    "ALTER TABLE `multicastSessions` MODIFY COLUMN `msIsDD` INTEGER NOT NULL",
    "UPDATE `schemaVersion` SET vValue='8'",
);
// 9
$this->schema[] = array(
    'CREATE TABLE `globalSettings` ('
    . '`settingID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`settingKey` VARCHAR(254) NOT NULL,'
    . '`settingDesc` LONGTEXT NOT NULL,'
    . '`settingValue` VARCHAR(254) NOT NULL,'
    . '`settingCategory` VARCHAR(254) NOT NULL,'
    . 'PRIMARY KEY (`settingID`),'
    . 'INDEX `new_index` (`settingKey`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . "('FOG_TFTP_PXE_CONFIG_DIR','Location of pxe boot files on the PXE server.','"
    . TFTP_PXE_CONFIG_DIR
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
    . "('FOG_PXE_IMAGE_DNSADDRESS','Since the fog boot image has an "
    . "incomplete dhcp implementation, you can specify a dns address "
    . "to be used with the boot image. If you are going to use this "
    . "settings, you should turn <b>FOG_USE_SLOPPY_NAME_LOOKUPS</b> off.','"
    . PXE_IMAGE_DNSADDRESS
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
    . '/fog/'
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
    . "('FOG_QUEUESIZE','This setting defines how many unicast "
    . "tasks to allow to be active at one time.','"
    . QUEUESIZE
    . "','General Settings'),"
    . "('FOG_CHECKIN_TIMEOUT','This setting defines the amount "
    . "of time between client checks to determine if they are "
    . "active clients.','"
    . CHECKIN_TIMEOUT
    . "','General Settings'),"
    . "('FOG_USER_MINPASSLENGTH','This setting defines the "
    . "minimum number of characters in a user\'s password.','"
    . USER_MINPASSLENGTH
    . "','User Management'),"
    . "('FOG_USER_VALIDPASSCHARS','This setting defines the "
    . "valid characters used in a password.','"
    . USER_VALIDPASSCHARS
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
    . FOG_THEME
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `dirCleaner` ('
    . '`dcID` INTEGER  NOT NULL AUTO_INCREMENT,'
    . '`dcPath` longtext  NOT NULL,'
    . 'PRIMARY KEY (`dcID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `hostAutoLogOut` ('
    . '`haloID` INTEGER  NOT NULL AUTO_INCREMENT,'
    . '`haloHostID` INTEGER  NOT NULL,'
    . '`haloTime` VARCHAR(10) NOT NULL,'
    . 'PRIMARY KEY (`haloID`),'
    . 'INDEX `new_index`(`haloHostID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    'CREATE TABLE `greenFog` ('
    . '`gfID` INTEGER NOT NULL AUTO_INCREMENT,'
    . '`gfHostID` INTEGER NOT NULL,'
    . '`gfHour` INTEGER NOT NULL,'
    . '`gfMin` INTEGER NOT NULL,'
    . '`gfAction` varchar(2) NOT NULL,'
    . '`gfDays` varchar(25) NOT NULL,'
    . 'PRIMARY KEY (`gfID`),'
    . 'INDEX `new_index`(`gfHostID`)'
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "UPDATE `schemaVersion` set vValue = '9'",
);
// 10
$this->schema[] = array(
    "CREATE TABLE `imagingLog` ("
    . "`ilID` INTEGER NOT NULL AUTO_INCREMENT,"
    . "`ilHostID` INTEGER NOT NULL,"
    . "`ilStartTime` DATETIME NOT NULL,"
    . "`ilFinishTime` DATETIME NOT NULL,"
    . "`ilImageName` VARCHAR(64) NOT NULL,"
    . "PRIMARY KEY (`ilID`),"
    . "INDEX `new_index`(`ilHostID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 11
$this->schema[] = array(
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
);
// 12
$this->schema[] = array(
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
);
// 13
$this->schema[] = array(
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
);
// 14
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) VALUES "
    . "('FOG_UTIL_DIR','This setting defines the location of the fog "
    . "utility directory.','/opt/fog/utils','FOG Utils')",
    "ALTER TABLE `users` ADD COLUMN `uType` VARCHAR(2) NOT NULL AFTER `uCreateBy`",
    "UPDATE `schemaVersion` set vValue = '14'",
);
// 15
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "ALTER TABLE `hosts` CHANGE `hostAnon3` `hostKernel` VARCHAR(250) "
    . "CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,"
    . "CHANGE `hostAnon4` `hostDevice` VARCHAR(250) CHARACTER "
    . "SET utf8 COLLATE utf8_general_ci NOT NULL",
    "UPDATE `schemaVersion` set vValue = '15'",
);
// 16
$fogstoragenodeuser = "fogstorage";
$fogstoragenodepass = "fs".rand(1000, 100000000000);
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . "'1','/images/','1','"
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
    . "'$fogstoragenodeuser','FOG Storage Nodes'),"
    . "('FOG_STORAGENODE_MYSQLPASS','This setting defines the password "
    . "the storage nodes should use to connect to the fog server.',"
    . "'$fogstoragenodepass','FOG Storage Nodes')",
    "GRANT ALL ON `"
    . DATABASE_NAME
    . "`.* TO '$fogstoragenodeuser'@'%' IDENTIFIED BY '$fogstoragenodepass'",
    "UPDATE `schemaVersion` set `vValue`='16'",
);
// 17
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_SSH_USERNAME','This setting defines the username used "
    . "for the ssh client.','root','SSH Client'),"
    . "('FOG_SSH_PORT','This setting defines the port to use for the ssh client.',"
    . "'22','SSH Client'),"
    . "('FOG_VIEW_DEFAULT_SCREEN','This setting defines which page is "
    . "displayed in each section, valid settings includes <b>LIST</b> "
    . "and <b>SEARCH</b>.','SEARCH','FOG View Settings')",
    "UPDATE `schemaVersion` set vValue = '17'",
);
// 18
$this->schema[] = array(
    "INSERT IGNORE INTO `supportedOS` "
    . "(`osName`,`osValue`) "
    . "VALUES "
    . "('Windows 7','5'),"
    . "('Windows 8','6')",
    "UPDATE `schemaVersion` set `vValue`='18'",
);
// 19
$this->schema[] = array(
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_UTIL_BASE','This setting defines the location of util base, "
    . "which is typically /opt/fog/','/opt/fog/','FOG Utils')",
    "UPDATE `schemaVersion` set vValue = '19'",
);
// 20
$this->schema[] = array(
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
);
// 21
$this->schema[] = array(
    "CREATE TABLE `hostMAC` ("
    . "`hmID` integer NOT NULL AUTO_INCREMENT,"
    . "`hmHostID` integer NOT NULL,"
    . "`hmMAC` varchar(18) NOT NULL,"
    . "`hmDesc` longtext NOT NULL,"
    . "PRIMARY KEY (`hmID`),"
    . "INDEX `idxHostID`(`hmHostID`),"
    . "INDEX `idxMac`(`hmMAC`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "CREATE TABLE `oui` ("
    . "`ouiID` int(11) NOT NULL AUTO_INCREMENT,"
    . "`ouiMACPrefix` varchar(8) NOT NULL,"
    . "`ouiMan` varchar(254) NOT NULL,"
    . "PRIMARY KEY (`ouiID`),"
    . "KEY `idxMac` (`ouiMACPrefix`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 22
$this->schema[] = array(
    "ALTER TABLE `inventory` ADD INDEX (`iHostID`)",
    "UPDATE `globalSettings` set `settingKey`='FOG_HOST_LOOKUP' "
    . "WHERE `settingKey`='FOG_HOST_LOCKUP'",
    "UPDATE `schemaVersion` set `vValue`='22'",
);
// 23
$this->schema[] = array(
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
);
// 24
$this->schema[] = array(
    "ALTER TABLE `groups` ADD `groupKernel` VARCHAR(255) NOT NULL",
    "ALTER TABLE `groups` ADD `groupKernelArgs` VARCHAR(255) NOT NULL",
    "ALTER TABLE `groups` ADD `groupPrimaryDisk` VARCHAR(255) NOT NULL",
    "UPDATE `schemaVersion` set `vValue`='24'",
);
// 25
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `os` ("
    . "`osID` mediumint(9) NOT NULL AUTO_INCREMENT,"
    . "`osName` varchar(30) NOT NULL,"
    . "`osDescription` text NOT NULL,"
    . "PRIMARY KEY (`osID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 26
$this->schema[] = array(
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` MEDIUMINT NOT NULL",
    "ALTER TABLE `nfsGroupMembers` ADD `ngmInterface` VARCHAR(25) NOT NULL DEFAULT '"
    . STORAGE_INTERFACE
    . "'",
    "ALTER TABLE `nfsGroupMembers` ADD `ngmGraphEnabled` "
    . "ENUM('0','1') NOT NULL DEFAULT '1'",
        "UPDATE `schemaVersion` set `vValue`='26'",
    );
// 27
$this->schema[] = array(
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
);
// 28
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `imageTypes` ("
    . "`imageTypeID` mediumint(9) NOT NULL auto_increment,"
    . "`imageTypeName` varchar(100) NOT NULL,"
    . "PRIMARY KEY  (`imageTypeID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `imageTypes` "
    . "(`imageTypeID`, `imageTypeName`) "
    . "VALUES "
    . "(1, 'Single Partition (NTFS Only, Resizable)'),"
    . "(2, 'Multiple Partition Image - Single Disk (Not Resizable)'),"
    . "(3, 'Multiple Partition Image - All Disks  (Not Resizable)'),"
    . "(4, 'Raw Image (Sector By Sector, DD, Slow)')",
    "UPDATE `schemaVersion` set `vValue`='28'",
);
// 29
if (FOG_SCHEMA < $tmpSchema->get('value')) {
    self::$DB->query(
        "SELECT DISTINCT `hostImage`,`hostOS` FROM `hosts` WHERE hostImage > 0"
    );
    while ($Host = self::$DB->fetch()->get()) {
        $allImageID[$Host['hostImage']] = $Host['hostOS'];
    }
    foreach ((array)$allImageID as $imageID => $osID) {
        $Image = self::getClass('Image', $imageID);
        if (!$Image->isValid()) {
            continue;
        }
        $OS = self::getClass('OS', $osID);
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
$this->schema[] = array(
    "UPDATE `schemaVersion` SET `vValue`=29",
);
// 30
$this->schema[] = array(
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
);
// 31
$this->schema[] = array(
    "ALTER TABLE `scheduledTasks` CHANGE `stIsGroup` `stIsGroup` VARCHAR(2) "
    . "CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0'",
    "UPDATE `schemaVersion` set `vValue`='31'",
);
// 32
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `taskStates` ("
    . "`tsID` int(11) NOT NULL,"
    . "`tsName` varchar(30) NOT NULL,"
    . "`tsDescription` text NOT NULL,"
    . "`tsOrder` tinyint(4) NOT NULL DEFAULT '0',"
    . "PRIMARY KEY (`tsID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 33
$this->schema[] = array(
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
);
// 34
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `modules` ("
    . "`id` mediumint(9) NOT NULL AUTO_INCREMENT, "
    . "`name` varchar(50) NOT NULL, `short_name` "
    . "varchar(30) NOT NULL, `description` text "
    . "NOT NULL, PRIMARY KEY (`id`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 35
$this->schema[] = array(
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
);
// 36
$this->schema[] = array(
    "ALTER TABLE `groups` ADD UNIQUE ( `groupName` )",
);
// 37
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `taskLog` ("
    . "`id` mediumint(9) NOT NULL AUTO_INCREMENT,"
    . "`taskID` mediumtext NOT NULL,"
    . "`taskStateID` mediumint(9) NOT NULL,"
    . "`ip` varchar(15) NOT NULL,"
    . "`createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,"
    . "`createdBy` VARCHAR(30) NOT NULL,"
    . "PRIMARY KEY (`id`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
);
// 38
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD UNIQUE (`ngmMemberName`)",
    "ALTER TABLE `nfsGroups` ADD UNIQUE (`ngName`)"
);
// 39
$this->schema[] = array(
    "INSERT IGNORE INTO `os` "
    . "(`osID`,`osName`,`osDescription`) "
    . "VALUES "
    . "('6','Windows 8','')",
    "ALTER TABLE `hosts` drop column `hostOS`"
);
// 40
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PIGZ_COMP','PIGZ Compression Rating','9','FOG PXE Settings')",
);
// 41
$this->schema[] = array(
    "ALTER TABLE `imagingLog` ADD `ilType` VARCHAR(64) NOT NULL"
);
// 42
$this->schema[] = array(
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` BIGINT NOT NULL"
);
// 43
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_KEY_SEQUENCE','Key Sequence for boot prompt.','0','FOG Boot Setting')"
);
// 44
$this->schema[] = array(
    "CREATE TABLE `keySequence` ("
    . "`ksID` INTEGER NOT NULL AUTO_INCREMENT,"
    . "`ksValue` varchar(25) NOT NULL,"
    . "`ksAscii` varchar(25) NOT NULL,"
    . "PRIMARY KEY (`ksID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
);
$keySequences = array(
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
);
// 45 - 79 setup
$keys = array();
foreach ($keySequences as $value => $ascii) {
    $this->schema[] = array();
    $keys[] = sprintf(
        "('%s','%s')",
        $value,
        $ascii
    );
}
// 79
$this->schema[count($this->schema) - 1] = array(
    "INSERT IGNORE INTO `keySequence` "
    . "(`ksValue`,`ksAscii`) "
    . "VALUES "
    . implode(',', $keys)
);
// 80
$this->schema[] = array(
    "ALTER TABLE `tasks` "
    . "ADD COLUMN `taskShutdown` char "
    . "NOT NULL AFTER `taskLastMemberID`",
);
// 81
$this->schema[] = array(
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageLegacy` char NOT NULL AFTER `imageOSID`",
    "UPDATE `images` set imageLegacy = '1' where 1 = 1",
);
// 82
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_LEGACY_FLAG_IN_GUI','This setting allows you to set "
    . "whether or not an image is legacy. "
    . "(Valid values are 0 or 1)','0','General Settings')"
);
// 83
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_PROXY_USERNAME','This setting defines the proxy username to use.',"
    . "'','General Settings'),"
    . "('FOG_PROXY_PASSWORD','This setting defines the proxy password to use.',"
    . "'','General Settings')",
    "UPDATE `globalSettings` SET `settingCategory`='Proxy Settings' "
    . "WHERE `globalSettings`.`settingKey` LIKE 'FOG_PROXY%'",
);
// 84
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_NO_MENU','This setting sets the system to no menu, if "
    . "there is no task set, it boots to first device.','','FOG Boot Settings')",
);
// 85
$this->schema[] = array(
    "UPDATE `globalSettings` SET `settingCategory`='FOG Boot Settings' "
    . "WHERE `settingCategory`='FOG PXE Settings' OR "
    . "`settingCategory`='FOG Boot Setting'",
);
// 86
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_TFTP_PXE_KERNEL_32','Location of the 32 bit kernel file on "
    . "the PXE server, this should point to the kernel itself.',"
    . "'bzImage32','TFTP Server'),"
    . "('FOG_PXE_BOOT_IMAGE_32','The settings defines where the 32 bit "
    . "fog boot file system image is located.','init_32.xz','TFTP Server')",
);
// 87
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`)"
    . "VALUES "
    . "('FOG_MINING_ENABLE','This setting defines whether to have the "
    . "imaging client give up a resources for mining cryptocurrency. "
    . "This is a means to donate to the FOG project without any real money.','"
    . FOG_DONATE_MINING
    . "','General Settings')",
);
// 88
$this->schema[] = array(
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageLastDeploy` DATETIME NOT NULL AFTER `imageLegacy`",
);
// 89
$this->schema[] = array(
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostLastDeploy` DATETIME NOT NULL AFTER `hostCreateDate`",
);
// 90
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_BOOT_EXIT_TYPE','The method of booting to the hard drive. "
    . "Most will accept sanboot, but some require exit.','','FOG Boot Settings')",
);
// 91
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_MINING_MAX_CORES','This setting defines the maximum number "
    . "of CPU cores you are willing to dedicate to mining "
    . "cryptocurrency.','1','General Settings')",
);
// 92
$this->schema[] = array(
    "ALTER TABLE `snapinJobs` "
    . "ADD COLUMN `sjStateID` INT(11) NOT NULL AFTER `sjHostID`",
    );
// 93
$this->schema[] = array(
    "ALTER TABLE `snapinJobs` CHANGE `sjStateID` `sjStateID` INT(11) NOT NULL",
);
// 94
$this->schema[] = array(
    "INSERT IGNORE INTO `taskTypes` "
    . "(`ttID`,`ttName`,`ttDescription`,`ttIcon`,`ttKernel`,"
    . "`ttKernelArgs`,`ttType`,`ttIsAdvanced`,`ttIsAccess`) "
    . "VALUES "
    . "(23,'Donate','This task will run a program to mine "
    . "cryptocurrency that will be donated to the FOG Project.',"
    . "'donate.png','','mode=donate.full','fog','1','both')",
);
// 95
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_MINING_FULL_RESTART_HOUR','This setting define the hour of "
    . "the day, in 24 hour format, for when you would like the donation "
    . "task to reboot.','6','General Settings'),"
    . "('FOG_MINING_FULL_RUN_ON_WEEKEND','If set to 1, then "
    . "FOG_MINING_FULL_RESTART_HOUR is ignored over weekends.',"
    . "'1','General Settings')",
);
// 96
$this->schema[] = array(
    "ALTER TABLE `tasks` ADD COLUMN `taskPassreset` "
    . "varchar(250)  NOT NULL AFTER `taskLastMemberID`",
    );
// 97
$this->schema[] = array(
    "truncate table `tasks`",
);
// 98
$this->schema[] = array(
    "DELETE FROM `globalSettings` where "
    . "`settingKey`='FOG_TFTP_PXE_CONFIG_DIR' limit 1",
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
);
// 99
$this->schema[] = array(
    "UPDATE `globalSettings` set `settingCategory`='Donations' "
    . "WHERE `settingKey`='FOG_MINING_ENABLE'",
    "UPDATE `globalSettings` set `settingCategory`='Donations' "
    . "WHERE `settingKey`='FOG_MINING_MAX_CORES'",
    "UPDATE `globalSettings` set `settingCategory`='Donations' "
    . "WHERE `settingKey`='FOG_MINING_FULL_RESTART_HOUR'",
    "UPDATE `globalSettings` set `settingCategory`='Donations' "
    . "WHERE `settingKey`='FOG_MINING_FULL_RUN_ON_WEEKEND'",
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_MINING_PACKAGE_PATH','Where should we pull the donation "
    . "script from?','http://fogproject.org/fogpackage.zip','Donations')",
);
// 100
$this->schema[] = array(
    "UPDATE `imageTypes` SET `imageTypeName`="
    . "'Single Disk (NTFS Only, Resizable)' "
    . "WHERE `imageTypes`.`imageTypeName`='Single Partition (NTFS Only, Resizable)'",
    );
// 101
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_DATA_RETURNED','This setting presents the search bar "
    . "if list has more returned than this number. "
    . "(A value of 0 disables it)','0','FOG View Settings')",
);
// 102
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_GROUP_ASSOC','Allows a group to be assigned "
    . "during quick registration. Default is no group "
    . "assigned.','0','FOG Quick Registration')",
);
// 103
$this->schema[] = array(
    "INSERT IGNORE INTO `os` "
    . "(`osID`,`osName`,`osDescription`) "
    . "VALUES "
    . "('7','Windows 8.1','')",
);
// 104
$this->schema[] = array(
    "ALTER TABLE `inventory` "
    . "ADD COLUMN `iDeleteDate` datetime NOT NULL AFTER `iCreateDate`",
);
// 105
$this->schema[] = array(
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
);
// 106
$this->schema[] = array(
    "ALTER TABLE `images` CHANGE `imageLegacy` `imageFormat` char",
    "UPDATE `globalSettings` SET `settingKey`='FOG_FORMAT_FLAG_IN_GUI' "
    . "WHERE `settingKey`='FOG_LEGACY_FLAG_IN_GUI'",
);
// 107
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingCategory`='SSH Client'",
    "UPDATE `globalSettings` SET "
    . "`settingCategory`='FOG Client - Snapins' WHERE "
    . "`settingKey`='FOG_SNAPINDIR'",
);
// 108
$this->schema[] = array(
    "UPDATE `globalSettings` SET `settingDesc`='This setting defines "
    . "if the fog printer manager should be globally active. "
    . "(Valid values are 0 or 1)' WHERE "
    . "`settingKey`='FOG_CLIENT_PRINTERMANAGER_ENABLED'",
);
// 109
$this->schema[] = array(
    "ALTER TABLE `images` "
    . "ADD COLUMN `imageMagnetUri` longtext  NOT NULL AFTER `imagePath`",
);
// 110
$this->schema[] = array(
    "UPDATE taskTypes SET ttKernelArgs='type=down' WHERE ttID='17'",
);
// 111
$this->schema[] = array(
    "UPDATE `imageTypes` SET `imageTypeName`='Single Disk - Resizable' "
    . "WHERE `imageTypes`.`imageTypeName`='Single Disk (NTFS Only, Resizable)'",
);
// 112
$this->schema[] = array(
    "ALTER TABLE `hosts` "
    . "ADD COLUMN `hostProductKey` varchar(50) NOT NULL AFTER `hostADPass`",
    );
// 113
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_ADVANCED_MENU_LOGIN','This setting enforces a login "
    . "parameter to get into the advanced menu.','0','FOG Boot Settings')",
);
// 114
$this->schema[] = array(
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES ('8', 'Apple Mac OS', '')",
);
// 115
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_TASK_FORCE_REBOOT','This setting enables or disables "
    . "the Force reboot of tasks. This only affects if users are "
    . "logged in. If users are logged in, the host will not "
    . "reboot if this is disabled.','0','FOG Client - Task Reboot')",
);
// 116
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_CLIENT_CHECKIN_TIME','This setting returns the client "
    . "service checkin times to the server.','60','FOG Client')",
    "UPDATE modules SET short_name='snapinclient' WHERE short_name='snapin'",
);
// 117
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_UDPCAST_MAXWAIT','This setting sets the max time to "
    . "wait for other clients before starting the session in "
    . "minutes.','10','Multicast Settings')",
);
// 118
$this->schema[] = array(
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
);
// 119
$column = array_filter((array)DatabaseManager::getColumns('default', 'modules'));
$this->schema[] = count($column) > 0 ? array() : array(
    "ALTER TABLE `modules` ADD COLUMN `default` INT "
    . "DEFAULT 1 NOT NULL AFTER `description`"
);
// 120
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `imagePartitionTypes` ("
    . "`imagePartitionTypeID` mediumint(9) NOT NULL auto_increment,"
    . "`imagePartitionTypeName` varchar(100) NOT NULL,"
    . "`imagePartitionTypeValue` varchar(10) NOT NULL,"
    . "PRIMARY KEY  (`imagePartitionTypeID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 121
$this->schema[] = array(
    "ALTER TABLE `images` ADD COLUMN `imagePartitionTypeID` "
    . "mediumint(9) NOT NULL AFTER `imageTypeID`",
        "UPDATE images SET imagePartitionTypeID='1'",
    );
// 122
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `pxeMenu` ("
    . "`pxeID` mediumint(9) NOT NULL auto_increment,"
    . "`pxeName` varchar(100) NOT NULL,"
    . "`pxeDesc` longtext  NOT NULL,"
    . "`pxeParams` longtext NOT NULL,"
    . "`pxeRegOnly` INT DEFAULT 0 NOT NULL,"
    . "`pxeArgs` varchar(250) NULL,"
    . "`pxeDefault` INT DEFAULT 0 NOT NULL,"
    . "PRIMARY KEY (`pxeID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
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
);
// 123
$this->schema[] = array();
// 124
$this->schema[] = array();
// 125
$this->schema[] = array(
    "UPDATE `taskTypes` SET ttKernelArgs='mc=bt type=down' WHERE ttID='24'",
);
// 126
$this->schema[] = array(
    "ALTER TABLE `tasks` ADD COLUMN `taskIsDebug` mediumint(9) "
    . "NOT NULL AFTER `taskStateID`",
);
// 127
$this->schema[] = array(
    "ALTER TABLE `images` ADD COLUMN `imageProtect` mediumint(9) "
    . "NOT NULL AFTER `imagePath`",
);
// 128
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostPending` mediumint(9) NULL",
);
// 129
$this->schema[] = array(
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
);
// 130
$this->schema[] = self::fastmerge(
    array(
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
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'hostMAC',
            array(
                'hmHostID',
                'hmMAC'
            )
        ),
        true
    )
);
// 131
$this->schema[] = array(
    "CREATE TABLE IF NOT EXISTS `ipxeTable` ("
    . "`ipxeID` mediumint(9) NOT NULL auto_increment,"
    . "`ipxeProduct` longtext NOT NULL,"
    . "`ipxeManufacturer` longtext NOT NULL,"
    . "`ipxeFilename` longtext NOT NULL,"
    . "`ipxeMAC` VARCHAR(17) NOT NULL,"
    . "`ipxeSuccess` VARCHAR(2) NOT NULL,"
    . "`ipxeFailure` VARCHAR(2) NOT NULL,"
    . "PRIMARY KEY (`ipxeID`)"
    . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_DHCP_BOOTFILENAME','This setting just sets what is "
    . "in use for the boot filename. It is up to the admin to "
    . "ensure this setting is correct for their database to be "
    . "accurate. Default setting is undionly.kpxe',"
    . "'undionly.kpxe','TFTP Server')",
);
// 132
$column = array_filter(
    (array)DatabaseManager::getColumns(
        'ipxeVersion',
        'ipxeTable'
    )
);
$this->schema[] = count($column) ? array() : array(
    "ALTER TABLE `ipxeTable` ADD COLUMN `ipxeVersion` LONGTEXT NOT NULL",
);
// 133
$snapindir = self::getSetting('FOG_SNAPINDIR');
if (!$snapindir) {
    $snapindir = '/opt/fog/snapins';
}
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmSnapinPath` "
    . "LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmSnapinPath`='"
    . $snapindir
    . "'",
);
// 134
$this->schema[] = array(
    "ALTER TABLE `snapins` ADD COLUMN `snapinNFSGroupID` INT(11) NOT NULL",
);
// 135
$this->schema[] = array(
    "ALTER TABLE `multicastSessions` ADD COLUMN `msSessClients` "
    . "INT(11) NOT NULL AFTER msClients",
);
// 136
$this->schema[] = self::fastmerge(
    array(
        "ALTER TABLE `tasks` ADD COLUMN `taskImageID` "
        . "INT(11) NOT NULL AFTER `taskHostID`",
        "CREATE TABLE IF NOT EXISTS `imageGroupAssoc` ("
        . "`igaID` mediumint(9) NOT NULL auto_increment,"
        . "`igaImageID` mediumint(9) NOT NULL,"
        . "`igaStorageGroupID` mediumint(9) NOT NULL,"
        . "`igaPrimary` ENUM('0','1') NOT NULL,"
        . "PRIMARY KEY (`igaID`)"
        . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
        "INSERT IGNORE INTO `imageGroupAssoc` "
        . "(`igaImageID`,`igaStorageGroupID`) "
        . "SELECT `imageID`,`imageNFSGroupID` FROM "
        . "`images` WHERE `imageNFSGroupID` IS NOT NULL",
        "UPDATE `imageGroupAssoc` SET `igaPrimary`='1'",
        "ALTER TABLE `images` DROP COLUMN `imageNFSGroupID`"
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'imageGroupAssoc',
            array(
                'igaImageID',
                'igaImageID'
            )
        ),
        true
    )
);
// 137
$this->schema[] = array(
    "ALTER TABLE `scheduledTasks` ADD COLUMN `stImageID` "
    . "INT(11) NOT NULL AFTER `stGroupHostID`",
);
// 138
$this->schema[] = array(
    "ALTER TABLE `imageGroupAssoc` DROP INDEX `igaImageID`",
);
// 139
$this->schema[] = array(
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
);
// 140
$this->schema[] = self::fastmerge(
    array(
        "CREATE TABLE IF NOT EXISTS `snapinGroupAssoc` ("
        . "`sgaID` mediumint(9) NOT NULL auto_increment,"
        . "`sgaSnapinID` mediumint(9) NOT NULL,"
        . "`sgaStorageGroupID` mediumint(9) NOT NULL,"
        . "`sgaPrimary` ENUM('0','1') NOT NULL,"
        . "PRIMARY KEY (`sgaID`)"
        . ') ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC',
        "INSERT IGNORE INTO `snapinGroupAssoc` "
        . "(`sgaSnapinID`,`sgaStorageGroupID`) "
        . "SELECT `sID`,`snapinNFSGroupID` FROM `snapins` "
        . "WHERE `snapinNFSGroupID` IS NOT NULL",
        "UPDATE `snapinGroupAssoc` SET `sgaPrimary`='1'",
        "ALTER TABLE `snapins` DROP COLUMN `snapinNFSGroupID`"
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'snapinGroupAssoc',
            array(
                'sgaSnapinID',
                'sgaSnapinID'
            ),
            'sgaSnapinID'
        ),
        true
    )
);
// 141
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_PXE_HIDDENMENU_TIMEOUT', 'This setting defines the default "
    . "value for the pxe hidden menu timeout.', '3', 'FOG Boot Settings')",
);
// 142
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_USED_TASKS', 'This setting defines tasks to consider "
    . "\'Used\' in the task count. Listing is comma separated, "
    . "using the ID\'s of the tasks.', '1,15,17', 'General Settings')",
);
// 143
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_GRACE_TIMEOUT', 'This setting defines the grace period "
    . "for the reboots and shutdowns. The value is specified in seconds.',"
    . "'60', 'FOG Client')",
);
// 144
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmBandwidthLimit` "
    . "INT(20) NOT NULL AFTER `ngmMaxClients`",
);
// 145
$this->schema[] = array(
    "UPDATE `pxeMenu` SET `pxeRegOnly`='2' WHERE pxeID='7'",
);
// 146
$this->schema[] = array(
    "UPDATE `pxeMenu` SET `pxeRegOnly`='2' WHERE pxeID='6'",
);
// 147
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostPubKey` LONGTEXT",
);
// 148
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_SNAPIN_LIMIT', 'This setting defines the maximum snapins "
    . "allowed to be assigned to a host. Value of 0 means unlimted.', "
    . "'0', 'General Settings')",
);
// 149
$this->schema[] = array(
    "ALTER TABLE `images` ADD COLUMN `imageCompress` INT(11)",
);
// 150
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_JPGRAPH_VERSION'",
);
// 151
$this->schema[] = array(
    "ALTER TABLE `taskTypes` ENGINE=MyISAM",
    "ALTER TABLE `taskStates` ENGINE=MyISAM",
    "ALTER TABLE `taskLog` ENGINE=MyISAM",
    "ALTER TABLE `os` ENGINE=MyISAM",
    "ALTER TABLE `modules` ENGINE=MyISAM",
);
// 152
$this->schema[] = array(
    "ALTER TABLE `imageGroupAssoc` ADD UNIQUE(`igaImageID`,`igaStorageGroupID`)",
    "ALTER TABLE `snapinGroupAssoc` ADD UNIQUE(`sgaSnapinID`,`sgaStorageGroupID`)",
);
// 153
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_FTP_IMAGE_SIZE', 'This setting defines the global enabling "
    . "of image on server size. Checkbox on or off is the enabling element. "
    . "Default is off.','0','General Settings')",
);
// 154
$this->schema[] = array(
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
);
// 155
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MULTICAST_DUPLEX','This setting defines the duplex value. "
    . "Default is FULL_DUPLEX.','--full-duplex','Multicast Settings')",
);
// 156
$this->schema[] = array(
    "UPDATE `globalSettings` SET `settingValue`='default/fog.css' "
    . "WHERE `settingKey`='FOG_THEME'",
);
// 157, doesn't do anything but ensure all currently create tables are MyISAM
$this->schema[] = array();
// 158
$this->schema[] = array();
// 159
$this->schema[] = array();
// 160
$this->schema[] = array();
// 161
$this->schema[] = self::fastmerge(
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'greenFog',
            array('gfHostID')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'groups',
            array('groupName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'hosts',
            array('hostName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'hostScreenSettings',
            array('hssHostID')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'imagePartitionTypes',
            array('imagePartitionTypeName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'imageTypes',
            array('imageTypeValue')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'images',
            array('imageName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'inventory',
            array('iHostID')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'modules',
            array('short_name')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'nfsGroups',
            array('ngName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'os',
            array('osName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'plugins',
            array('pName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'printers',
            array('pAlias')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'snapins',
            array('sName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'supportedOS',
            array('osName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'taskStates',
            array('tsName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'taskTypes',
            array('ttName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'groupMembers',
            array(
                'gmHostID',
                'gmGroupID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'hostAutoLogOut',
            array('haloHostID')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'hostMAC',
            array('hmMAC')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'imageGroupAssoc',
            array(
                'igaImageID',
                'igaStorageGroupID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'moduleStatusByHost',
            array(
                'msHostID',
                'msModuleID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'multicastSessionsAssoc',
            array(
                'msID',
                'tID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'nfsFailures',
            array(
                'nfNodeID',
                'nfHostID',
                'nfTaskID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'nfsGroupMembers',
            array('ngmMemberName')
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'oui',
            array(
                'ouiMACPrefix',
                'ouiMan'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'printerAssoc',
            array(
                'paHostID',
                'paPrinterID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'snapinAssoc',
            array(
                'saSnapinID',
                'saHostID'
            )
        )
    ),
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'snapinGroupAssoc',
            array(
                'sgaStorageGroupID',
                'sgaSnapinID'
            )
        )
    )
);
// 162
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    array(
        'snapinTasks',
        array(
            'stJobID',
            'stSnapinID'
        )
    )
);
// 163
$this->schema[] = array(
    "DROP TABLE IF EXISTS `hostFingerprintAssoc`,`queueAssoc`,`nodeJSconfig`",
);
// 164
$this->schema[] = array(
);
// 165
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_REGISTRATION_ENABLED','This setting enables the capabilities "
    . "to allow registration to occur or not. Default setting is enabled.',"
    . "'1','FOG Boot Settings')",
);
// 166
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_TZ_INFO','This setting allows the user to set the "
    . "system timezone. Default is UTC in the db, but will first "
    . "try the ini set if possible.','UTC','General Settings')",
);
// 167
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_AES_PASS_ENCRYPT_KEY'",
);
// 168
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_KERNEL_DEBUG','This setting allows the user to have the "
    . "kernel debug flag set. Default is off.','0','FOG Boot Settings')",
);
// 169
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_KERNEL_LOGLEVEL','This setting allows the user to specify "
    . "which loglevel the want. Default is 4.','4','FOG Boot Settings')",
);
// 170
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_FTP_PORT','This setting allows the user to specify the "
    . "ftp port to be used. Default Value is port 21.',"
    . "'21','General Settings'),"
    . "('FOG_FTP_TIMEOUT','This setting allows the user to specify "
    . "the FTP Timeout. This value is entered in seconds. "
    . "Default is 90.','90','General Settings')",
);
// 171
$this->schema[] = array(
);
// 172
$this->schema[] = array(
    "DELETE FROM globalSettings WHERE settingKey='FOG_AES_ADPASS_ENCRYPT_KEY'",
);
// 173
$this->schema[] = array(
    "ALTER TABLE `greenFog` DROP INDEX `gfHostID`",
);
// 174
$this->schema[] = array(
    "ALTER TABLE `users` DROP KEY new_index1",
    "ALTER TABLE `users` CHANGE `uPass` `uPass` LONGTEXT NOT NULL",
);
// 175
$this->schema[] = array(
    "ALTER TABLE `snapins`
    ADD COLUMN `snapinProtect` mediumint(9) NOT NULL",
    );
// 176
$this->schema[] = array(
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
);
// 177
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_NONREG_DEVICE','This setting defines a target disk to "
    . "apply an image to specifically for non-registered hosts. "
    . "If not set, a disk will be selected by the init.',"
    . "'','Non-Registered Host Image')",
);
// 178
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostSecToken` LONGTEXT",
);
// 179
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostSecTime` TIMESTAMP NOT NULL",
);
// 180
$this->schema[] = array(
    "UPDATE globalSettings SET settingValue=6 WHERE settingKey='FOG_PIGZ_COMP'",
);
// 181
$this->schema[] = array(
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "('9', 'Windows 10', '')",
);
// 182
$this->schema[] = array(
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
);
// 183
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` CHANGE `ngmInterface` "
    . "`ngmInterface` VARCHAR (25) CHARACTER SET utf8 "
    . "COLLATE utf8_general_ci NOT NULL DEFAULT '"
    . STORAGE_INTERFACE
    . "'",
);
// 184
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmFTPPath` LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmFTPPath`='"
    . STORAGE_DATADIR
    . "'",
);
// 185
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmMaxBitrate` VARCHAR (25) AFTER `ngmFTPPath`",
);
// 186
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_NEW_CLIENT'",
    "ALTER TABLE .`hosts` ADD COLUMN `hostADPassLegacy` LONGTEXT AFTER `hostADPass`",
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
);
// 187
$this->schema[] = array(
    "ALTER TABLE `printers` ADD COLUMN `pDesc` LONGTEXT",
);
// 188
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN `ngmWebroot` LONGTEXT NOT NULL",
    "UPDATE `nfsGroupMembers` SET `ngmWebroot`='/fog/'",
);
// 189
$this->schema[] = self::fastmerge(
    $tmpSchema->dropDuplicateData(
        DATABASE_NAME,
        array(
            'globalSettings',
            array(
                'settingKey',
                'settingKey'
            ),
            'settingKey'
        ),
        true
    ),
    array("DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_PATH'",
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_HOST'",
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_WOL_INTERFACE'")
);
// 190
$this->schema[] = array(
    "ALTER TABLE `hosts` MODIFY `hostADPassLegacy` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostPending` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostPubKey` LONGTEXT NOT NULL",
    "ALTER TABLE `hosts` MODIFY `hostSecToken` LONGTEXT NOT NULL",
);
// 191
$this->schema[] = array(
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
);
// 192
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_EFI_BOOT_EXIT_TYPE','The method (U)EFI uses to boot the "
    . "next boot entry/hard drive. Most will require exit. (Default REFIND)',"
    . "'refind_efi','FOG Boot Settings')",
);
// 193
$this->schema[] = array(
    "UPDATE `taskTypes` set `ttName`='Deploy' WHERE `ttID`=1",
    "UPDATE `taskTypes` set `ttName`='Capture' WHERE `ttID`=2",
    "UPDATE `taskTypes` set `ttName`='Deploy - Debug' WHERE `ttID`=15",
    "UPDATE `taskTypes` set `ttName`='Capture - Debug' WHERE `ttID`=16",
    "UPDATE `taskTypes` set `ttName`='Deploy - No Snapins' WHERE `ttID`=17",
);
// 194
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostPingCode` VARCHAR(20)",
);
// 195
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostExitBios` LONGTEXT",
    "ALTER TABLE `hosts` ADD COLUMN `hostExitEfi` LONGTEXT",
);
// 196 this will set all current snapin jobs and taskings to complete
$this->schema[] = array(
    "UPDATE `snapinTasks` SET `stState`=4",
    "UPDATE `snapinJobs` SET `sjStateID`=4",
);
// 197
$this->schema[] = array(
    "ALTER TABLE`hostMAC` MODIFY `hmMAC` VARCHAR(59) NOT NULL",
);
// 198
$this->schema[] = array(
);
// 199
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_AES_ENCRYPT'",
    "DELETE FROM `globalSettings` WHERE `settingKey`='FOG_DHCP_BOOTFILENAME'",
);
// 200
$this->schema[] = array(
    "ALTER TABLE `hosts` MODIFY `hostProductKey` LONGTEXT",
);
// 201
$this->schema[] = array(
    "ALTER TABLE `images` ADD `imageEnabled` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `snapins` ADD `sEnabled` ENUM('0','1') NOT NULL DEFAULT '1'",
);
// 202
$this->schema[] = array(
    "ALTER TABLE `images` ADD `imageReplicate` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `snapins` ADD `sReplicate` ENUM('0','1') NOT NULL DEFAULT '1'",
);
// 203
$this->schema[] = array(
    "ALTER TABLE `taskStates` ADD `tsIcon` varchar(255) NOT NULL",
    "UPDATE `taskStates` SET `tsIcon`='bookmark-o' WHERE `tsID`=1",
    "UPDATE `taskStates` SET `tsIcon`='pause' WHERE `tsID`=2",
    "UPDATE `taskStates` SET `tsIcon`='spinner fa-pulse fa-fw' WHERE `tsID`=3",
    "UPDATE `taskStates` SET `tsIcon`='check-circle' WHERE `tsID`=4",
    "UPDATE `taskStates` SET `tsIcon`='ban' WHERE `tsID`=5",
);
// 204
$this->schema[] = array(
    "ALTER TABLE `taskStates` MODIFY `tsID` INT(11) AUTO_INCREMENT",
);
// 205
$this->schema[] = array(
    "ALTER TABLE `imagingLog` ADD `ilCreatedBy` VARCHAR(255) NOT NULL"
);
// 206
$this->schema[] = array(
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
);
// 207
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_WIPE_TIMEOUT', 'This setting defines the number of "
    . "seconds to wait for wiping disks. (Default 60)',"
    . "'60', 'FOG Boot Settings')",
);
// 208
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_BANDWIDTH_TIME', 'This setting defines how often to "
    . "refresh the bandwidth chart. Values are in seconds',"
    . "'1','General Settings')",
);
// 209
$this->schema[] = array(
    "ALTER TABLE `printers` ADD `pConfigFile` VARCHAR(255) NOT NULL AFTER `pConfig`",
);
// 210
$this->schema[] = array(
    "UPDATE `taskTypes` SET `ttDescription`='Fast wipe will boot "
    . "the client computer and wipe the first few sectors of data "
    . "on the hard disk. Data will not be overwritten but the boot "
    . "up of the disk and partition layout will no longer exist.' "
    . "WHERE `ttID`=18",
);
// 211
$this->schema[] = array(
    "INSERT IGNORE INTO `os` "
    . "(`osID`, `osName`, `osDescription`) "
    . "VALUES "
    . "('51', 'Chromium OS', 'Chromium OS')",
);
// 212
$this->schema[] = array(
    "ALTER TABLE `nfsGroupMembers` ADD COLUMN "
    . "`ngmSSLPath` LONGTEXT NOT NULL AFTER `ngmRootPath`",
    "UPDATE `nfsGroupMembers` SET `ngmSSLPath`='/opt/fog/snapins/ssl'",
);
// 213
$this->schema[] = array(
    "DROP TABLE IF EXISTS `peer`",
    "DROP TABLE IF EXISTS `peer_torrent`",
    "DROP TABLE IF EXISTS `torrent`",
    "DELETE FROM `globalSettings` WHERE "
    . "`settingKey` IN ('FOG_TORRENT_INTERVAL',"
    . "'FOG_TORRENT_TIMEOUT','FOG_TORRENT_INTERVAL_MIN',"
    . "'FOG_TORRENT_PPR','FOG_TORRENTDIR')",
    "DELETE FROM `taskTypes` WHERE `ttID`=24",
);
// 214
$this->schema[] = array(
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
);
// 215
$this->schema[] = array(
    "UPDATE `taskTypes` SET `ttKernelArgs`='mode=inventory deployed=1' "
    . "WHERE `ttID`=10",
);
// 216
$this->schema[] = array(
    "ALTER TABLE `tasks` ADD COLUMN `taskWOL` ENUM('0','1') "
    . "NOT NULL AFTER `taskLastMemberID`",
);
// 217
$this->schema[] = array(
    "ALTER TABLE `clientUpdates` CHANGE `cuType` `cuType` VARCHAR(30) NOT NULL",
);
// 218
$this->schema[] = array(
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
);
// 219
$this->schema[] = array(
);
// 220
$this->schema[] = array(
    "DELETE FROM `globalSettings` WHERE `settingKey` "
    . "IN ('FOG_QUEUESIZE','FOG_PXE_IMAGE_DNSADDRESS')",
    "CREATE TABLE `groupMembers_new` ("
    . "`gmID` int(11) NOT NULL AUTO_INCREMENT,"
    . "`gmHostID` int(11) NOT NULL,"
    . "`gmGroupID` int(11) NOT NULL,"
    . "PRIMARY KEY(`gmID`),"
    . "UNIQUE KEY `gmHostID` (`gmHostID`,`gmGroupID`),"
    . "UNIQUE KEY `gmGroupID` (`gmHostID`,`gmGroupID`),"
    . "KEY `new_index` (`gmHostID`),"
    . "KEY `new_index1` (`gmGroupID`)"
    . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `groupMembers_new` SELECT * FROM `groupMembers`",
    "DROP TABLE `groupMembers`",
    "RENAME TABLE `groupMembers_new` TO `groupMembers`",
);
// 221
$this->schema[] = $this->schema[count($this->schema)-1];
// 222
$this->schema[] = array(
    "ALTER TABLE `hosts` ADD COLUMN `hostInit` LONGTEXT AFTER `hostDevice`",
);
// 223
$this->schema[] = array(
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
    . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
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
);
// 224
$this->schema[] = array(
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
);
// 225
$this->schema[] = array(
    "CREATE TABLE `globalSettings_new` (
        `settingID` INT NOT NULL AUTO_INCREMENT,
        `settingKey` VARCHAR(255) NOT NULL,
        `settingDesc` LONGTEXT NOT NULL,
        `settingValue` LONGTEXT NOT NULL,
        `settingCategory` LONGTEXT NOT NULL,
        PRIMARY KEY(`settingID`),
UNIQUE INDEX `settingKey` (`settingKey`)
    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `globalSettings_new` SELECT * FROM `globalSettings`",
    "DROP TABLE `globalSettings`",
    "RENAME TABLE `globalSettings_new` TO `globalSettings`",
);
// 226
$this->schema[] = array(
    "ALTER TABLE `snapins` ADD `sHideLog` ENUM('0','1') NOT NULL DEFAULT '0'",
    "ALTER TABLE `snapins` ADD `sTimeout` INTEGER NOT NULL DEFAULT 0",
);
// 227
$this->schema[] = array(
    "ALTER TABLE `hosts` CHANGE `hostPending` `hostPending` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmPrimary` `hmPrimary` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmPending` `hmPending` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmIgnoreClient` "
    . "`hmIgnoreClient` ENUM('0','1') NOT NULL",
    "ALTER TABLE `hostMAC` CHANGE `hmIgnoreImaging` "
    . "`hmIgnoreImaging` ENUM('0','1') NOT NULL",
);
// 228
$this->schema[] = array(
    "TRUNCATE TABLE `history`",
    "ALTER TABLE `history` CHANGE `hText` `hText` VARCHAR(255) NOT NULL",
    "ALTER TABLE `history` ADD UNIQUE INDEX `updateTime` (`hText`,`hTime`)",
);
// 229
$this->schema[] = array(
    "ALTER TABLE `images` CHANGE `imageSize` `imageSize` VARCHAR(255) NOT NULL",
);
// 230
$this->schema[] = array(
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
);
// 231
$this->schema[] = array(
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
);
// 232
$this->schema[] = array(
    "ALTER TABLE `snapins` ADD `sPackType` ENUM('0','1') NOT NULL DEFAULT '0'",
);
// 233
$this->schema[] = array(
    "UPDATE `globalSettings` SET "
    . "`settingKey`='FOG_CAPTUREIGNOREPAGEHIBER' "
    . "WHERE `settingKey`='FOG_UPLOADIGNOREPAGEHIBER'",
    "UPDATE `globalSettings` SET `settingKey`='FOG_CAPTURERESIZEPCT' "
    . "WHERE `settingKey`='FOG_UPLOADRESIZEPCT'",
);
// 234
$this->schema[] = array(
    "ALTER TABLE `snapins` ADD `sHash` VARCHAR(255) NOT NULL DEFAULT ''",
    "ALTER TABLE `snapins` ADD `sSize` BIGINT NOT NULL DEFAULT 0",
);
// 235
$this->schema[] = array(
    "CREATE TABLE `users_new` ("
    . "`uId` INT NOT NULL AUTO_INCREMENT,"
    . "`uName` VARCHAR(40) NOT NULL,"
    . "`uPass` LONGTEXT NOT NULL,"
    . "`uCreateDate` DATETIME NOT NULL,"
    . "`uCreateBy` VARCHAR(40) NOT NULL,"
    . "`uType` INT NOT NULL,"
    . "PRIMARY KEY(`uId`),"
    . "UNIQUE INDEX `name` (`uName`)"
    . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
    "INSERT IGNORE INTO `users_new` SELECT * FROM `users`",
    "DROP TABLE `users`",
    "RENAME TABLE `users_new` TO `users`",
);
// 236
$this->schema[] = array(
    DatabaseManager::getColumns('multicastSessions', 'msAnon1') > 0 ?
    'ALTER TABLE `multicastSessions`'
    . 'CHANGE `msAnon1` `msIsDD` INTEGER NOT NULL' :
    '',
    "ALTER TABLE `imageGroupAssoc` CHANGE `igaPrimary` `igaPrimary` "
    . "ENUM('0','1') NOT NULL",
    "ALTER TABLE `snapinGroupAssoc` CHANGE `sgaPrimary` `sgaPrimary` "
    . "ENUM('0','1') NOT NULL"
);
// 237
$this->schema[] = array(
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
);
// 238
$this->schema[] = array(
    Schema::dropTable('aloLog')
);
// 239
$this->schema[] = array(
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
);
// 240
$this->schema[] = array(
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
);
// 241
$this->schema[] = array(
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
);
// 242
$this->schema[] = array(
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
);
// 243
$this->schema[] = array(
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
);
// 244
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    array(
        'globalSettings',
        array(
            'settingKey'
        )
    ),
    true
);
// 245
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_LOGIN_INFO_DISPLAY', 'This setting defines if the login page"
    . " should or should not display fog version information. (Default is "
    . "on)','1','General Settings')"
);
// 246
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    array(
        'hostMAC',
        array('hmMAC')
    )
);
// 247
$this->schema[] = array(
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
);
// 248
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_MULTICAST_RENDEZVOUS', 'This setting defines a rendez-vous"
    . " for multicast tasks. (Default is empty)','','Multicast Settings')"
);
// 249
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) "
    . "VALUES "
    . "('FOG_QUICKREG_IMG_WHEN_REG','Image upon completion"
    . " of registration. Values are 0 or 1, default is 1."
    . " This will only image clients if the image value is"
    . " defined as well.','0', 'FOG Quick Registration')"
);
// 250
$this->schema[] = array(
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
);
// 251
$this->schema[] = $tmpSchema->dropDuplicateData(
    DATABASE_NAME,
    array(
        'globalSettings',
        array('settingKey')
    )
);
// 252
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_IMAGE_COMPRESSION_FORMAT_DEFAULT',"
    . "'Compression Format Setting (Default to PIGZ non-split)',"
    . "'1','General Settings'),"
    . "('FOG_TASKING_ADV_SHUTDOWN_ENABLED',"
    . "'Tasking shutdown element checked (Default is off)',"
    . "'0','General Settings'),"
    . "('FOG_TASKING_ADV_WOL_ENABLED',"
    . "'Tasking wake on lan element checked (Default is on)',"
    . "'1','General Settings'),"
    . "('FOG_TASKING_ADV_DEBUG_ENABLED',"
    . "'Tasking debug element checked (Default is off)',"
    . "'0','General Settings')"
);
// 253
$this->schema[] = array(
    "ALTER TABLE `users` ADD `uDisplay` VARCHAR(255) "
    . "NOT NULL AFTER `uType`"
);
// 254
$this->schema[] = array(
    "CREATE TABLE `hookEvents` ("
    . "`heID` INT NOT NULL AUTO_INCREMENT,"
    . "`heName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`heID`),"
    . "UNIQUE INDEX `name` (`heName`)"
    . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
    "CREATE TABLE `notifyEvents` ("
    . "`neID` INT NOT NULL AUTO_INCREMENT,"
    . "`neName` VARCHAR(255) NOT NULL,"
    . "PRIMARY KEY(`neID`),"
    . "UNIQUE INDEX `name` (`neName`)"
    . ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC",
);
// 255
$this->schema[] = array(
    "ALTER TABLE `pxeMenu` ADD `pxeHotKeyEnable` ENUM('0','1') NOT NULL",
    "ALTER TABLE `pxeMenu` ADD `pxeKeySequence` VARCHAR(255) NOT NULL"
);
// 256
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_API_ENABLED',"
    . "'Enables API Access (Defaults to off)',"
    . "'0','API System'),"
    . "('FOG_API_TOKEN',"
    . "'The API Token to use (Randomly generated at install)',"
    . "'"
    . self::createSecToken()
    . "','API System')"
);
// 257
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_IMAGE_LIST_MENU',"
    . "'Enables Image list on boot menu deploy image (Defaults to on)',"
    . "'1','FOG Boot Settings')"
);
// 258
$this->schema[] = array(
    "DELETE FROM `taskTypes` WHERE `ttID` IN (23, 24)",
    "DELETE FROM `globalSettings` WHERE `settingKey` LIKE 'FOG_MINING%'",
    "ALTER TABLE `taskTypes` auto_increment=1",
    "ALTER TABLE `globalSettings` auto_increment=1"
);
// 259
$this->schema[] = array(
    "ALTER TABLE `users` ADD `uAllowAPI` ENUM('0','1') NOT NULL DEFAULT '1'",
    "ALTER TABLE `users` ADD `uAPIToken` VARCHAR(255) NOT NULL"
);
// 260
$this->schema[] = array(
    "INSERT IGNORE INTO `globalSettings` "
    . "(`settingKey`,`settingDesc`,`settingValue`,`settingCategory`) "
    . "VALUES "
    . "('FOG_REAUTH_ON_DELETE',"
    . "'If deleteing an item, require authentication or not. (Defaults to on)',"
    . "'1','General Settings'),"
    . "('FOG_REAUTH_ON_EXPORT',"
    . "'If exporting, require authentication or not. (Defaults to on)',"
    . "'1','General Settings')"
);
