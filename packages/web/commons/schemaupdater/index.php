<?php
// Require FOG Base
require_once('../config.php');
require_once(BASEPATH . '/commons/init.php');
require_once(BASEPATH . '/commons/init.database.php');
require_once(BASEPATH . '/commons/text.php');
// 0
$databaseSchema[] = array(
	"CREATE DATABASE " . DATABASE_NAME ,
	"CREATE TABLE  `" . DATABASE_NAME . "`.`groupMembers` (
	  `gmID` int(11) NOT NULL auto_increment,
	  `gmHostID` int(11) NOT NULL,
	  `gmGroupID` int(11) NOT NULL,
	  PRIMARY KEY  (`gmID`),
	  KEY `new_index` (`gmHostID`),
	  KEY `new_index1` (`gmGroupID`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`groups` (
	  `groupID` int(11) NOT NULL auto_increment,
	  `groupName` varchar(50) NOT NULL,
	  `groupDesc` longtext NOT NULL,
	  `groupDateTime` datetime NOT NULL,
	  `groupCreateBy` varchar(50) NOT NULL,
	  `groupBuilding` int(11) NOT NULL,
	  PRIMARY KEY  (`groupID`),
	  KEY `new_index` (`groupName`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`history` (
	  `hID` int(11) NOT NULL auto_increment,
	  `hText` longtext NOT NULL,
	  `hUser` varchar(200) NOT NULL,
	  `hTime` datetime NOT NULL,
	  `hIP` varchar(50) NOT NULL,
	  PRIMARY KEY  (`hID`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`hosts` (
	  `hostID` int(11) NOT NULL auto_increment,
	  `hostName` varchar(16) NOT NULL,
	  `hostDesc` longtext NOT NULL,
	  `hostIP` varchar(25) NOT NULL,
	  `hostImage` int(11) NOT NULL,
	  `hostBuilding` int(11) NOT NULL,
	  `hostCreateDate` datetime NOT NULL,
	  `hostCreateBy` varchar(50) NOT NULL,
	  `hostMAC` varchar(20) NOT NULL,
	  `hostOS` int(10) unsigned NOT NULL,
	  PRIMARY KEY  (`hostID`),
	  KEY `new_index` (`hostName`),
	  KEY `new_index1` (`hostIP`),
	  KEY `new_index2` (`hostMAC`),
	  KEY `new_index3` (`hostOS`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`images` (
	  `imageID` int(11) NOT NULL auto_increment,
	  `imageName` varchar(40) NOT NULL,
	  `imageDesc` longtext NOT NULL,
	  `imagePath` longtext NOT NULL,
	  `imageDateTime` datetime NOT NULL,
	  `imageCreateBy` varchar(50) NOT NULL,
	  `imageBuilding` int(11) NOT NULL,
	  `imageSize` varchar(200) NOT NULL,
	  PRIMARY KEY  (`imageID`),
	  KEY `new_index` (`imageName`),
	  KEY `new_index1` (`imageBuilding`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`schemaVersion` (
	  `vID` int(11) NOT NULL auto_increment,
	  `vValue` int(11) NOT NULL,
	  PRIMARY KEY  (`vID`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`supportedOS` (
	  `osID` int(10) unsigned NOT NULL auto_increment,
	  `osName` varchar(150) NOT NULL,
	  `osValue` int(10) unsigned NOT NULL,
	  PRIMARY KEY  (`osID`),
	  KEY `new_index` (`osValue`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`tasks` (
	  `taskID` int(11) NOT NULL auto_increment,
	  `taskName` varchar(250) NOT NULL,
	  `taskCreateTime` datetime NOT NULL,
	  `taskCheckIn` datetime NOT NULL,
	  `taskHostID` int(11) NOT NULL,
	  `taskState` int(11) NOT NULL,
	  `taskCreateBy` varchar(200) NOT NULL,
	  `taskForce` varchar(1) NOT NULL,
	  `taskScheduledStartTime` datetime NOT NULL,
	  `taskType` varchar(1) NOT NULL,
	  `taskPCT` int(10) unsigned zerofill NOT NULL,
	  PRIMARY KEY  (`taskID`),
	  KEY `new_index` (`taskHostID`),
	  KEY `new_index1` (`taskCheckIn`),
	  KEY `new_index2` (`taskState`),
	  KEY `new_index3` (`taskForce`),
	  KEY `new_index4` (`taskType`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`users` (
	  `uId` int(11) NOT NULL auto_increment,
	  `uName` varchar(40) NOT NULL,
	  `uPass` varchar(50) NOT NULL,
	  `uCreateDate` datetime NOT NULL,
	  `uCreateBy` varchar(40) NOT NULL,
	  PRIMARY KEY  (`uId`),
	  KEY `new_index` (`uName`),
	  KEY `new_index1` (`uPass`)
	) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"INSERT INTO `" . DATABASE_NAME . "`.`users` VALUES  ('','fog', MD5('password'),'0000-00-00 00:00:00','')",
	"INSERT INTO `" . DATABASE_NAME . "`.`supportedOS` VALUES  ('','"._("Windows XP")."', '1')",
	"INSERT INTO `" . DATABASE_NAME . "`.`schemaVersion` VALUES  ('','1')"
);
// 2
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.`supportedOS` VALUES  ('','"._("Windows Vista")."', '2')",

	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '2'",
);
// 3
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts`
			 ADD COLUMN `hostUseAD` char  NOT NULL AFTER `hostOS`,
			 ADD COLUMN `hostADDomain` VARCHAR(250)  NOT NULL AFTER `hostUseAD`,
			 ADD COLUMN `hostADOU` longtext  NOT NULL AFTER `hostADDomain`,
			 ADD COLUMN `hostADUser` VARCHAR(250)  NOT NULL AFTER `hostADOU`,
			 ADD COLUMN `hostADPass` VARCHAR(250)  NOT NULL AFTER `hostADUser`,
			 ADD COLUMN `hostAnon1` VARCHAR(250)  NOT NULL AFTER `hostADPass`,
			 ADD COLUMN `hostAnon2` VARCHAR(250)  NOT NULL AFTER `hostAnon1`,
			 ADD COLUMN `hostAnon3` VARCHAR(250)  NOT NULL AFTER `hostAnon2`,
			 ADD COLUMN `hostAnon4` VARCHAR(250)  NOT NULL AFTER `hostAnon3`,
			 ADD INDEX `new_index4`(`hostUseAD`)",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`snapinAssoc` (
			  `saID` int(11) NOT NULL auto_increment,
			  `saHostID` int(11) NOT NULL,
			  `saSnapinID` int(11) NOT NULL,
			  PRIMARY KEY  (`saID`),
			  KEY `new_index` (`saHostID`),
			  KEY `new_index1` (`saSnapinID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`snapinJobs` (
			  `sjID` int(11) NOT NULL auto_increment,
			  `sjHostID` int(11) NOT NULL,
			  `sjCreateTime` datetime NOT NULL,
			  PRIMARY KEY  (`sjID`),
			  KEY `new_index` (`sjHostID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`snapinTasks` (
			  `stID` int(11) NOT NULL auto_increment,
			  `stJobID` int(11) NOT NULL,
			  `stState` int(11) NOT NULL,
			  `stCheckinDate` datetime NOT NULL,
			  `stCompleteDate` datetime NOT NULL,
			  `stSnapinID` int(11) NOT NULL,
			  PRIMARY KEY  (`stID`),
			  KEY `new_index` (`stJobID`),
			  KEY `new_index1` (`stState`),
			  KEY `new_index2` (`stSnapinID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`snapins` (
			  `sID` int(11) NOT NULL auto_increment,
			  `sName` varchar(200) NOT NULL,
			  `sDesc` longtext NOT NULL,
			  `sFilePath` longtext NOT NULL,
			  `sArgs` longtext NOT NULL,
			  `sCreateDate` datetime NOT NULL,
			  `sCreator` varchar(200) NOT NULL,
			  `sReboot` varchar(1) NOT NULL,
			  `sAnon1` varchar(45) NOT NULL,
			  `sAnon2` varchar(45) NOT NULL,
			  `sAnon3` varchar(45) NOT NULL,
			  PRIMARY KEY  (`sID`),
			  KEY `new_index` (`sName`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '3'",
);
// 4
$databaseSchema[] = array(
	"CREATE TABLE  `" . DATABASE_NAME . "`.`multicastSessions` (
			  `msID` int(11) NOT NULL auto_increment,
			  `msName` varchar(250) NOT NULL,
			  `msBasePort` int(11) NOT NULL,
			  `msLogPath` longtext NOT NULL,
			  `msImage` longtext NOT NULL,
			  `msClients` int(11) NOT NULL,
			  `msInterface` varchar(250) NOT NULL,
			  `msStartDateTime` datetime NOT NULL,
			  `msPercent` int(11) NOT NULL,
			  `msState` int(11) NOT NULL,
			  `msCompleteDateTime` datetime NOT NULL,
			  `msAnon1` varchar(250) NOT NULL,
			  `msAnon2` varchar(250) NOT NULL,
			  `msAnon3` varchar(250) NOT NULL,
			  `msAnon4` varchar(250) NOT NULL,
			  `msAnon5` varchar(250) NOT NULL,
			  PRIMARY KEY  (`msID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`multicastSessionsAssoc` (
			  `msaID` int(11) NOT NULL auto_increment,
			  `msID` int(11) NOT NULL,
			  `tID` int(11) NOT NULL,
			  PRIMARY KEY  (`msaID`),
			  KEY `new_index` (`msID`),
			  KEY `new_index1` (`tID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '4'",
);
// 5
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`images`
			 ADD COLUMN `imageDD` VARCHAR(1)  NOT NULL AFTER `imageSize`,
			 ADD INDEX `new_index2`(`imageDD`)",
	"UPDATE `" . DATABASE_NAME . "`.`supportedOS` set osName = 'Windows 2000/XP' where osValue = '1'",
	"INSERT INTO `" . DATABASE_NAME . "`.`supportedOS` VALUES  ('','Other', '99')",
	"ALTER TABLE `" . DATABASE_NAME . "`.`multicastSessions` CHANGE COLUMN `msAnon1` `msIsDD` VARCHAR(1)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '5'",
);
// 7
$databaseSchema[] = array(
	"CREATE TABLE `" . DATABASE_NAME . "`.`virus` (
			  `vID` integer  NOT NULL AUTO_INCREMENT,
			  `vName` varchar(250)  NOT NULL,
			  `vHostMAC` varchar(50)  NOT NULL,
			  `vOrigFile` longtext  NOT NULL,
			  `vDateTime` datetime  NOT NULL,
			  `vMode` varchar(5)  NOT NULL,
			  `vAnon2` varchar(50)  NOT NULL,
			  PRIMARY KEY (`vID`),
			  INDEX `new_index`(`vHostMAC`),
			  INDEX `new_index2`(`vDateTime`)
			)
			ENGINE = MyISAM",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '6'",
);
// 8
$databaseSchema[] = array(
	"CREATE TABLE `" . DATABASE_NAME . "`.`userTracking` (
			  `utID` integer  NOT NULL AUTO_INCREMENT,
			  `utHostID` integer  NOT NULL,
			  `utUserName` varchar(50)  NOT NULL,
			  `utAction` varchar(2)  NOT NULL,
			  `utDateTime` datetime  NOT NULL,
			  `utDesc` varchar(250)  NOT NULL,
			  `utDate` date  NOT NULL,
			  `utAnon3` varchar(2)  NOT NULL,
			  PRIMARY KEY (`utID`),
			  INDEX `new_index`(`utHostID`),
			  INDEX `new_index1`(`utUserName`),
			  INDEX `new_index2`(`utAction`),
			  INDEX `new_index3`(`utDateTime`)
			)
			ENGINE = MyISAM",
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` CHANGE COLUMN `hostAnon1` `hostPrinterLevel` VARCHAR(2)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"CREATE TABLE `" . DATABASE_NAME . "`.`printers` (
			  `pID` integer  NOT NULL AUTO_INCREMENT,
			  `pPort` longtext  NOT NULL,
			  `pDefFile` longtext  NOT NULL,
			  `pModel` varchar(250)  NOT NULL,
			  `pAlias` varchar(250)  NOT NULL,
			  `pConfig` varchar(10)  NOT NULL,
			  `pIP` varchar(20)  NOT NULL,
			  `pAnon2` varchar(10)  NOT NULL,
			  `pAnon3` varchar(10)  NOT NULL,
			  `pAnon4` varchar(10)  NOT NULL,
			  `pAnon5` varchar(10)  NOT NULL,
			  PRIMARY KEY (`pID`),
			  INDEX `new_index1`(`pModel`),
			  INDEX `new_index2`(`pAlias`)
			)
			ENGINE = MyISAM",
	"CREATE TABLE `" . DATABASE_NAME . "`.`printerAssoc` (
			  `paID` integer  NOT NULL AUTO_INCREMENT,
			  `paHostID` integer  NOT NULL,
			  `paPrinterID` integer  NOT NULL,
			  `paIsDefault` varchar(2)  NOT NULL,
			  `paAnon1` varchar(2)  NOT NULL,
			  `paAnon2` varchar(2)  NOT NULL,
			  `paAnon3` varchar(2)  NOT NULL,
			  `paAnon4` varchar(2)  NOT NULL,
			  `paAnon5` varchar(2)  NOT NULL,
			  PRIMARY KEY (`paID`),
			  INDEX `new_index1`(`paHostID`),
			  INDEX `new_index2`(`paPrinterID`)
			)
			ENGINE = MyISAM",
	"CREATE TABLE  `" . DATABASE_NAME . "`.`inventory` (
			  `iID` int(11) NOT NULL auto_increment,
			  `iHostID` int(11) NOT NULL,
			  `iPrimaryUser` varchar(50) NOT NULL,
			  `iOtherTag` varchar(50) NOT NULL,
			  `iOtherTag1` varchar(50) NOT NULL,
			  `iCreateDate` datetime NOT NULL,
			  `iSysman` varchar(250) NOT NULL,
			  `iSysproduct` varchar(250) NOT NULL,
			  `iSysversion` varchar(250) NOT NULL,
			  `iSysserial` varchar(250) NOT NULL,
			  `iSystype` varchar(250) NOT NULL,
			  `iBiosversion` varchar(250) NOT NULL,
			  `iBiosvendor` varchar(250) NOT NULL,
			  `iBiosdate` varchar(250) NOT NULL,
			  `iMbman` varchar(250) NOT NULL,
			  `iMbproductname` varchar(250) NOT NULL,
			  `iMbversion` varchar(250) NOT NULL,
			  `iMbserial` varchar(250) NOT NULL,
			  `iMbasset` varchar(250) NOT NULL,
			  `iCpuman` varchar(250) NOT NULL,
			  `iCpuversion` varchar(250) NOT NULL,
			  `iCpucurrent` varchar(250) NOT NULL,
			  `iCpumax` varchar(250) NOT NULL,
			  `iMem` varchar(250) NOT NULL,
			  `iHdmodel` varchar(250) NOT NULL,
			  `iHdfirmware` varchar(250) NOT NULL,
			  `iHdserial` varchar(250) NOT NULL,
			  `iCaseman` varchar(250) NOT NULL,
			  `iCasever` varchar(250) NOT NULL,
			  `iCaseserial` varchar(250) NOT NULL,
			  `iCaseasset` varchar(250) NOT NULL,
			  PRIMARY KEY  (`iID`)
			) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1",
	"CREATE TABLE `" . DATABASE_NAME . "`.`clientUpdates` (
			  `cuID` integer  NOT NULL AUTO_INCREMENT,
			  `cuName` varchar(200)  NOT NULL,
			  `cuMD5` varchar(100)  NOT NULL,
			  `cuType` varchar(3)  NOT NULL,
			  `cuFile` LONGBLOB  NOT NULL,
			  PRIMARY KEY (`cuID`),
			  INDEX `new_index`(`cuName`),
			  INDEX `new_index1`(`cuType`)
			)
			ENGINE = MyISAM",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '7'",
);
// 8
$databaseSchema[] = array(
	"INSERT INTO " . DATABASE_NAME . ".supportedOS(osName, osValue) values( '"._("Windows 98")."', '3' )",
	"INSERT INTO " . DATABASE_NAME . ".supportedOS(osName, osValue) values( '"._("Windows (other)")."', '4' )",
	"INSERT INTO " . DATABASE_NAME . ".supportedOS(osName, osValue) values( '"._("Linux")."', '50' )",
	"ALTER TABLE `" . DATABASE_NAME . "`.`multicastSessions` MODIFY COLUMN `msIsDD` integer  NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '8'",
);
// 9
$databaseSchema[] = array(
	"CREATE TABLE `" . DATABASE_NAME . "`.`globalSettings` (
			  `settingID` INTEGER  NOT NULL AUTO_INCREMENT,
			  `settingKey` VARCHAR(254)  NOT NULL,
			  `settingDesc` longtext  NOT NULL,
			  `settingValue` varchar(254)  NOT NULL,
			  `settingCategory` varchar(254)  NOT NULL,
			  PRIMARY KEY (`settingID`),
			  INDEX `new_index`(`settingKey`)
			)
			ENGINE = MyISAM;",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_HOST', '"._("Hostname or IP address of the TFTP Server.")."', '" . TFTP_HOST . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_FTP_USERNAME', '"._("Username used to access the tftp server via ftp.")."', '" . TFTP_FTP_USERNAME . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_FTP_PASSWORD', '"._("Password used to access the tftp server via ftp.")."', '" . TFTP_FTP_PASSWORD . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_PXE_CONFIG_DIR', '"._("Location of pxe boot files on the PXE server.")."', '" . TFTP_PXE_CONFIG_DIR . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_PXE_KERNEL_DIR', '"._("Location of kernel files on the PXE server.")."', '" . TFTP_PXE_KERNEL_DIR . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_TFTP_PXE_KERNEL', '"._("Location of kernel file on the PXE server, this should point to the kernel itself.")."', '" . PXE_KERNEL . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_KERNEL_RAMDISK_SIZE', '"._("This setting defines the amount of physical memory (in KB) you want to use for the boot image.  This setting needs to be larger than the boot image and smaller that the total physical memory on the client.")."', '" . PXE_KERNEL_RAMDISK . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_USE_SLOPPY_NAME_LOOKUPS', '"._("The settings was added to workaround a partial implementation of DHCP in the boot image.  The boot image is unable to obtain a DNS server address from the DHCP server, so what this setting will do is resolve any hostnames to IP address on the FOG server before writing the config files.")."', '" . USE_SLOPPY_NAME_LOOKUPS . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_MEMTEST_KERNEL', '"._("The settings defines where the memtest boot image/kernel is located.")."', '" . MEMTEST_KERNEL . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PXE_BOOT_IMAGE', '"._("The settings defines where the fog boot file system image is located.")."', '" . PXE_IMAGE . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PXE_IMAGE_DNSADDRESS', '"._("Since the fog boot image has an incomplete dhcp implementation, you can specify a dns address to be used with the boot image.  If you are going to use this settings, you should turn <b>FOG_USE_SLOPPY_NAME_LOOKUPS</b> off.")."', '" . PXE_IMAGE_DNSADDRESS . "', 'TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_HOST', '"._("This setting defines the hostname or ip address of the NFS server used with FOG.")."', '" . STORAGE_HOST . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_FTP_USERNAME', '"._("This setting defines the username used to access files on the nfs server used with FOG.")."', '" . STORAGE_FTP_USERNAME . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_FTP_PASSWORD', '"._("This setting defines the password used to access flies on the nfs server used with FOG.")."', '" . STORAGE_FTP_PASSWORD . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_DATADIR', '"._("This setting defines the directory on the NFS server where images are stored.  ")."', '" . STORAGE_DATADIR . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_DATADIR_UPLOAD', '"._("This setting defines the directory on the NFS server where images are uploaded too.")."', '" . STORAGE_DATADIR_UPLOAD . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_BANDWIDTHPATH', '"._("This setting defines the web page used to acquire the bandwidth used by the nfs server.")."', '" . STORAGE_BANDWIDTHPATH . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UPLOADRESIZEPCT', '"._("This setting defines the amount of padding applied to a partition before attempting resize the ntfs volume and upload it.")."', '" . UPLOADRESIZEPCT . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_WEB_HOST', '"._("This setting defines the hostname or ip address of the web server used with fog.")."', '" . WEB_HOST . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_WEB_ROOT', '"._("This setting defines the path to the fog webserver\'s root directory.")."', '" . WEB_ROOT . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_WOL_HOST', '"._("This setting defines the ip address of hostname for the server hosting the Wake-on-lan service.")."', '" . WOL_HOST . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_WOL_PATH', '"._("This setting defines the path to the files performing the WOL tasks.")."', '" . WOL_PATH . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_WOL_INTERFACE', '"._("This setting defines the network interface used in the WOL process.")."', '" . WOL_INTERFACE . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SNAPINDIR', '"._("This setting defines the location of the snapin files.  These files must be hosted on the web server.")."', '" . SNAPINDIR . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_QUEUESIZE', '"._("This setting defines how many unicast tasks to allow to be active at one time.")."', '" . QUEUESIZE . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_CHECKIN_TIMEOUT', '"._("This setting defines the amount of time between client checks to determine if they are active clients.")."', '" . CHECKIN_TIMEOUT . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_USER_MINPASSLENGTH', '"._("This setting defines the minimum number of characters in a user\'s password.")."', '" . USER_MINPASSLENGTH . "', 'User Management')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_USER_VALIDPASSCHARS', '"._("This setting defines the valid characters used in a password.")."', '" . USER_VALIDPASSCHARS . "', 'User Management')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_NFS_ETH_MONITOR', '"._("This setting defines which interface is monitored for traffic summaries.")."', '" . NFS_ETH_MONITOR . "', 'NFS Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UDPCAST_INTERFACE', '"._("This setting defines the interface used in multicast communications.")."', '" . UDPCAST_INTERFACE . "', 'Multicast Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UDPCAST_STARTINGPORT', '"._("This setting defines the starting port number used in multicast communications.  This starting port number must be an even number.")."', '" . UDPCAST_STARTINGPORT . "', 'Multicast Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_MULTICAST_MAX_SESSIONS', '"._("This setting defines the maximum number of multicast sessions that can be running at one time.")."', '" . FOG_MULTICAST_MAX_SESSIONS . "', 'Multicast Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_JPGRAPH_VERSION', '"._("This setting defines ")."', '" . FOG_JPGRAPH_VERSION . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_REPORT_DIR', '"._("This setting defines the location on the web server of the FOG reports.")."', '" . FOG_REPORT_DIR . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_THEME', '"._("This setting defines what css style sheet and theme to use for FOG.")."', '" . FOG_THEME . "', 'Web Server')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UPLOADIGNOREPAGEHIBER', '"._("This setting defines if you would like to remove hibernate and swap files before uploading a Windows image.  ")."', '" . FOG_UPLOADIGNOREPAGEHIBER . "', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_DIRECTORYCLEANER_ENABLED', '"._("This setting defines if the Windows Service module directory cleaner should be enabled on client computers. This service is clean out the contents of a directory on when a user logs out of the workstation. (Valid values: 0 or 1).")."', '1', 'FOG Service - Directory Cleaner')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`moduleStatusByHost` (
				  `msID` integer  NOT NULL AUTO_INCREMENT,
				  `msHostID` integer  NOT NULL,
				  `msModuleID` varchar(50)  NOT NULL,
				  `msState` varchar(1)  NOT NULL,
				  PRIMARY KEY (`msID`),
				  INDEX `new_index`(`msHostID`),
				  INDEX `new_index2`(`msModuleID`)
				)
				ENGINE = MyISAM;",
	"CREATE TABLE `" . DATABASE_NAME . "`.`dirCleaner` (
				  `dcID` integer  NOT NULL AUTO_INCREMENT,
				  `dcPath` longtext  NOT NULL,
				  PRIMARY KEY (`dcID`)
				)
				ENGINE = MyISAM;",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_USE_ANIMATION_EFFECTS', '"._("This setting defines if the FOG management portal uses animation effects on it.  Valid values are 0 or 1")."', '1', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_USERCLEANUP_ENABLED', '"._("This setting defines if user cleanup should be enabled.  The User Cleanup module will remove all local windows users from the workstation on log off accept for users that are whitelisted.  (Valid values are 0 or 1)")."', '0', 'FOG Service - User Cleanup')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`userCleanup` (
				  `ucID` integer  NOT NULL AUTO_INCREMENT,
				  `ucName` varchar(254)  NOT NULL,
				  PRIMARY KEY (`ucID`)
				)
				ENGINE = MyISAM",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'administrator' )",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'admin' )",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'guest' )",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'HelpAssistant' )",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_GREENFOG_ENABLED', '"._("This setting defines if the green fog module should be enabled.  The green fog module will shutdown or restart a computer at a set time.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Green Fog')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_AUTOLOGOFF_ENABLED', '"._("This setting defines if the auto log off module should be enabled.  This module will log off any active user after X minutes of inactivity.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Auto Log Off')",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'ASPNET' )",
	"INSERT INTO `" . DATABASE_NAME . "`.userCleanup( ucName ) values( 'SUPPORT_' )",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_DISPLAYMANAGER_ENABLED', '"._("This setting defines if the fog display manager should be active.  The fog display manager will reset the clients screen resolution to a fixed size on log off and on computer start up.  (Valid values are 0 or 1)")."', '0', 'FOG Service - Display Manager')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_DISPLAYMANAGER_X', '"._("This setting defines the default width in pixels to reset the computer display to with the fog display manager service.")."', '1024', 'FOG Service - Display Manager')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_DISPLAYMANAGER_Y', '"._("This setting defines the default height in pixels to reset the computer display to with the fog display manager service.")."', '768', 'FOG Service - Display Manager')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_DISPLAYMANAGER_R', '"._("This setting defines the default refresh rate to reset the computer display to with the fog display manager service.")."', '60', 'FOG Service - Display Manager')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`hostScreenSettings` (
				  `hssID` integer  NOT NULL AUTO_INCREMENT,
				  `hssHostID` integer  NOT NULL,
				  `hssWidth` integer  NOT NULL,
				  `hssHeight` integer  NOT NULL,
				  `hssRefresh` integer  NOT NULL,
				  `hssOrientation` integer  NOT NULL,
				  `hssOther1` integer  NOT NULL,
				  `hssOther2` integer  NOT NULL,
				  PRIMARY KEY (`hssID`),
				  INDEX `new_index`(`hssHostID`)
				)
				ENGINE = MyISAM",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_AUTOLOGOFF_MIN', '"._("This setting defines the number of minutes to wait before logging a user off of a PC. (Value of 0 will disable this module.)")."', '0', 'FOG Service - Auto Log Off')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`hostAutoLogOut` (
				  `haloID` integer  NOT NULL AUTO_INCREMENT,
				  `haloHostID` integer  NOT NULL,
				  `haloTime` varchar(10) NOT NULL,
				  PRIMARY KEY (`haloID`),
				  INDEX `new_index`(`haloHostID`)
				)
				ENGINE = MyISAM",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_AUTOLOGOFF_BGIMAGE', '"._("This setting defines the location of the background image used in the auto log off module.  The image should be 300px x 300px.  This image can be located locally (such as c:\\\\images\\\\myimage.jpg) or on a web server (such as http://freeghost.sf.net/images/image.jpg)")."', 'c:\\\\program files\\\\fog\\\\images\\\\alo-bg.jpg', 'FOG Service - Auto Log Off')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_KEYMAP', '"._("This setting defines the keymap used on the client boot image.")."', '', 'General Settings')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`greenFog` (
				  `gfID` integer  NOT NULL AUTO_INCREMENT,
				  `gfHostID` integer  NOT NULL,
				  `gfHour` integer  NOT NULL,
				  `gfMin` integer  NOT NULL,
				  `gfAction` varchar(2)  NOT NULL,
				  `gfDays` varchar(25)  NOT NULL,
				  PRIMARY KEY (`gfID`),
				  INDEX `new_index`(`gfHostID`)
				)
				ENGINE = MyISAM",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_HOSTNAMECHANGER_ENABLED', '"._("This setting defines if the fog hostname changer should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Hostname Changer')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`aloLog` (
				  `alID` integer  NOT NULL AUTO_INCREMENT,
				  `alUserName` varchar(254)  NOT NULL,
				  `alHostID` integer  NOT NULL,
				  `alDateTime` datetime  NOT NULL,
				  `alAnon1` varchar(254)  NOT NULL,
				  `alAnon2` varchar(254)  NOT NULL,
				  `alAnon3` varchar(254)  NOT NULL,
				  PRIMARY KEY (`alID`),
				  INDEX `new_index`(`alUserName`),
				  INDEX `new_index2`(`alHostID`),
				  INDEX `new_index3`(`alDateTime`)
				)
				ENGINE = MyISAM",

	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '9'",
);
// 10
$databaseSchema[] = array(
	"CREATE TABLE `" . DATABASE_NAME . "`.`imagingLog` (
				  `ilID` integer  NOT NULL AUTO_INCREMENT,
				  `ilHostID` integer  NOT NULL,
				  `ilStartTime` datetime  NOT NULL,
				  `ilFinishTime` datetime  NOT NULL,
				  `ilImageName` varchar(64)  NOT NULL,
				  PRIMARY KEY (`ilID`),
				  INDEX `new_index`(`ilHostID`)
				)
				ENGINE = MyISAM",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_SNAPIN_ENABLED', '"._("This setting defines if the fog snapin installer should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Snapins')",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapins` CHANGE COLUMN `sAnon1` `sRunWith` VARCHAR(245) NOT NULL",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapinTasks` ADD COLUMN `stReturnCode` integer  NOT NULL AFTER `stSnapinID`,
				 ADD COLUMN `stReturnDetails` varchar(250)  NOT NULL AFTER `stReturnCode`",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapins` CHANGE COLUMN `sAnon2` `sRunWithArgs` VARCHAR(200)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '10'",
);
// 11
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` CHANGE COLUMN `hostAnon2` `hostKernelArgs` VARCHAR(250)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_KERNEL_ARGS', '"._("This setting allows you to add additional kernel arguments to the client boot image.  This setting is global for all hosts.")."', '', 'General Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '11'",
);
// 12
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_CLIENTUPDATER_ENABLED', '"._("This setting defines if the fog client updater should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Client Updater')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_HOSTREGISTER_ENABLED', '"._("This setting defines if the fog host register should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Host Register')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_PRINTERMANAGER_ENABLED', '"._("This setting defines if the fog printer maanger should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Printer Manager')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_TASKREBOOT_ENABLED', '"._("This setting defines if the fog task reboot should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - Task Reboot')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SERVICE_USERTRACKER_ENABLED', '"._("This setting defines if the fog user tracker should be globally active.  (Valid values are 0 or 1)")."', '1', 'FOG Service - User Tracker')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '12'",
);
// 13
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_AD_DEFAULT_DOMAINNAME', '"._("This setting defines the default value to populate the host\'s Active Directory domain name value.")."', '', 'Active Directory Defaults')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_AD_DEFAULT_OU', '"._("This setting defines the default value to populate the host\'s Active Directory OU value.")."', '', 'Active Directory Defaults')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_AD_DEFAULT_USER', '"._("This setting defines the default value to populate the host\'s Active Directory user name value.'").", '', 'Active Directory Defaults')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_AD_DEFAULT_PASSWORD', '"._("This setting defines the default value to populate the host\'s Active Directory password value.  This settings must be encrypted.")."', '', 'Active Directory Defaults')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '13'",
);
// 14
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UTIL_DIR', '"._("This setting defines the location of the fog utility directory.")."', '/opt/fog/utils', 'FOG Utils')",
	"ALTER TABLE `" . DATABASE_NAME . "`.`users` ADD COLUMN `uType` varchar(2)  NOT NULL AFTER `uCreateBy`",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '14'",
);
// 15
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PLUGINSYS_ENABLED', '"._("This setting defines if the fog plugin system should be enabled.")."', '0', 'Plugin System')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PLUGINSYS_DIR', '"._("This setting defines the base location of fog plugins.")."', './plugins', 'Plugin System')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`plugins` (
				  `pID` INTEGER  NOT NULL AUTO_INCREMENT,
				  `pName` VARCHAR(100)  NOT NULL,
				  `pState` CHAR  NOT NULL,
				  `pInstalled` CHAR  NOT NULL,
				  `pVersion` VARCHAR(100)  NOT NULL,
				  `pAnon1` VARCHAR(100)  NOT NULL,
				  `pAnon2` VARCHAR(100)  NOT NULL,
				  `pAnon3` VARCHAR(100)  NOT NULL,
				  `pAnon4` VARCHAR(100)  NOT NULL,
				  `pAnon5` VARCHAR(100)  NOT NULL,
				  PRIMARY KEY (`pID`),
				  INDEX `new_index`(`pName`),
				  INDEX `new_index1`(`pState`),
				  INDEX `new_index2`(`pInstalled`),
				  INDEX `new_index3`(`pVersion`)
				)
				ENGINE = MyISAM",
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` CHANGE COLUMN `hostAnon3` `hostKernel` VARCHAR(250)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
				 CHANGE COLUMN `hostAnon4` `hostDevice` VARCHAR(250)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '15'",
);
// 16
$fogstoragenodeuser = "fogstorage";
$fogstoragenodepass = "fs" . rand(1000, 100000000000 );
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks` ADD COLUMN `taskBPM` varchar(250)  NOT NULL AFTER `taskPCT`,
				 ADD COLUMN `taskTimeElapsed` varchar(250)  NOT NULL AFTER `taskBPM`,
				 ADD COLUMN `taskTimeRemaining` varchar(250)  NOT NULL AFTER `taskTimeElapsed`,
				 ADD COLUMN `taskDataCopied` varchar(250)  NOT NULL AFTER `taskTimeRemaining`,
				 ADD COLUMN `taskPercentText` varchar(250)  NOT NULL AFTER `taskDataCopied`,
				 ADD COLUMN `taskDataTotal` VARCHAR(250)  NOT NULL AFTER `taskPercentText`",
	"CREATE TABLE `" . DATABASE_NAME . "`.`nfsGroups` (
				  `ngID` integer  NOT NULL AUTO_INCREMENT,
				  `ngName` varchar(250)  NOT NULL,
				  `ngDesc` longtext  NOT NULL,
				  PRIMARY KEY (`ngID`)
				)
				ENGINE = MyISAM",
	"CREATE TABLE `" . DATABASE_NAME . "`.`nfsGroupMembers` (
			  `ngmID` integer  NOT NULL AUTO_INCREMENT,
			  `ngmMemberName` varchar(250)  NOT NULL,
			  `ngmMemberDescription` longtext  NOT NULL,
			  `ngmIsMasterNode` char  NOT NULL,
			  `ngmGroupID` integer  NOT NULL,
			  `ngmRootPath` longtext  NOT NULL,
			  `ngmIsEnabled` char  NOT NULL,
			  `ngmHostname` varchar(250)  NOT NULL,
			  `ngmMaxClients` integer  NOT NULL,
			  `ngmUser` varchar(250)  NOT NULL,
			  `ngmPass` varchar(250)  NOT NULL,
			  `ngmKey` varchar(250)  NOT NULL,
			  PRIMARY KEY (`ngmID`),
			  INDEX `new_index`(`ngmMemberName`),
			  INDEX `new_index2`(`ngmIsMasterNode`),
			  INDEX `new_index3`(`ngmGroupID`),
			  INDEX `new_index4`(`ngmIsEnabled`)
			)
			ENGINE = MyISAM",
	"ALTER TABLE `" . DATABASE_NAME . "`.`images` ADD COLUMN `imageNFSGroupID` integer  NOT NULL AFTER `imageDD`,
			ADD INDEX `new_index3`(`imageNFSGroupID`)",
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks` ADD COLUMN `taskNFSGroupID` integer  NOT NULL AFTER `taskDataTotal`,
			 ADD COLUMN `taskNFSMemberID` integer  NOT NULL AFTER `taskNFSGroupID`,
			 ADD COLUMN `taskNFSFailures` char  NOT NULL AFTER `taskNFSMemberID`,
			 ADD COLUMN `taskLastMemberID` integer  NOT NULL AFTER `taskNFSFailures`,
			 ADD INDEX `new_index5`(`taskNFSGroupID`),
			 ADD INDEX `new_index6`(`taskNFSMemberID`),
			 ADD INDEX `new_index7`(`taskNFSFailures`),
			 ADD INDEX `new_index8`(`taskLastMemberID`)",
	"CREATE TABLE `" . DATABASE_NAME . "`.`nfsFailures` (
			  `nfID` integer  NOT NULL AUTO_INCREMENT,
			  `nfNodeID` integer  NOT NULL,
			  `nfTaskID` integer  NOT NULL,
			  `nfHostID` integer  NOT NULL,
			  `nfGroupID` integer  NOT NULL,
			  `nfDateTime` integer  NOT NULL,
			  PRIMARY KEY (`nfID`),
			  INDEX `new_index`(`nfNodeID`),
			  INDEX `new_index1`(`nfTaskID`),
			  INDEX `new_index2`(`nfHostID`),
			  INDEX `new_index3`(`nfGroupID`)
			)
			ENGINE = MyISAM",
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsFailures` MODIFY COLUMN `nfDateTime` datetime  NOT NULL,
			 ADD INDEX `new_index4`(`nfDateTime`)",
	"ALTER TABLE `" . DATABASE_NAME . "`.`multicastSessions` CHANGE COLUMN `msAnon2` `msNFSGroupID` integer  NOT NULL,
			 ADD INDEX `new_index`(`msNFSGroupID`)",
	"INSERT INTO `" . DATABASE_NAME . "`.nfsGroups (ngName, ngDesc) values ('default', '"._("Auto generated fog nfs group")."' );",
	 "INSERT INTO 
				`" . DATABASE_NAME . "`.nfsGroupMembers
				(ngmMemberName, ngmMemberDescription, ngmIsMasterNode, ngmGroupID, ngmRootPath, ngmIsEnabled, ngmHostname, ngmMaxClients, ngmUser, ngmPass ) 
				VALUES
				('DefaultMember', '"._("Auto generated fog nfs group member")."', '1', '1', '/images/', '1', '" . STORAGE_HOST . "', '10', '" . STORAGE_FTP_USERNAME . "', '" . STORAGE_FTP_PASSWORD . "' )",
	"UPDATE `" . DATABASE_NAME . "`.images set imageNFSGroupID = '1'",
	"DELETE FROM `" . DATABASE_NAME . "`.`globalSettings` WHERE settingKey = 'FOG_NFS_HOST'",
	"DELETE FROM `" . DATABASE_NAME . "`.`globalSettings` WHERE settingKey = 'FOG_NFS_FTP_USERNAME'",
	"DELETE FROM `" . DATABASE_NAME . "`.`globalSettings` WHERE settingKey = 'FOG_NFS_FTP_PASSWORD'",
	"DELETE FROM `" . DATABASE_NAME . "`.`globalSettings` WHERE settingKey = 'FOG_NFS_DATADIR'",
	"DELETE FROM `" . DATABASE_NAME . "`.`globalSettings` WHERE settingKey = 'FOG_NFS_DATADIR_UPLOAD'",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_STORAGENODE_MYSQLUSER', '"._("This setting defines the username the storage nodes should use to connect to the fog server.")."', '$fogstoragenodeuser', 'FOG Storage Nodes')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_STORAGENODE_MYSQLPASS', '"._("This setting defines the password the storage nodes should use to connect to the fog server.")."', '$fogstoragenodepass', 'FOG Storage Nodes')",
	// Blackout
	// Assumes we are a user with GRANT prilidges
	// Will need -> GRANT USAGE ON * . * TO 'fog'@'localhost' WITH GRANT OPTION MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0 ,
	"GRANT ALL ON `" . DATABASE_NAME . "`.* TO '$fogstoragenodeuser'@'%' IDENTIFIED BY '$fogstoragenodepass'",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '16'",
);
// 17
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SSH_USERNAME', '"._("This setting defines the username used for the ssh client.")."', 'root', 'SSH Client')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_SSH_PORT', '"._("This setting defines the port to use for the ssh client.")."', '22', 'SSH Client')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_VIEW_DEFAULT_SCREEN', '"._("This setting defines which page is displayed in each section, valid settings includes <b>LIST</b> and <b>SEARCH</b>.")."', 'SEARCH', 'FOG View Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '17'",
);
// 18
$databaseSchema[] = array(
	"INSERT INTO " . DATABASE_NAME . ".supportedOS(osName, osValue) values( '"._("Windows 7")."', '5' )",
	"INSERT INTO " . DATABASE_NAME . ".supportedOS(osName, osValue) values( '"._("Windows 8")."', '6' )",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '18'",
);
// 19
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PXE_MENU_TIMEOUT', '"._("This setting defines the default value for the pxe menu timeout.")."', '3', 'FOG PXE Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PROXY_IP', '"._("This setting defines the proxy ip address to use.")."', '', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PROXY_PORT', '"._("This setting defines the proxy port address to use.")."', '', 'General Settings')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`scheduledTasks` (
				  `stID` integer  NOT NULL AUTO_INCREMENT,
				  `stName` varchar(240)  NOT NULL,
				  `stDesc` longtext  NOT NULL,
				  `stType` varchar(24)  NOT NULL,
				  `stTaskType` varchar(24)  NOT NULL,
				  `stMinute` varchar(240)  NOT NULL,
				  `stHour` varchar(240)  NOT NULL,
				  `stDOM` varchar(240)  NOT NULL,
				  `stMonth` varchar(240)  NOT NULL,
				  `stDOW` varchar(240)  NOT NULL,
				  `stIsGroup` varchar(2)  NOT NULL,
				  `stGroupHostID` integer  NOT NULL,
				  `stShutDown` varchar(2)  NOT NULL,
				  `stOther1` varchar(240)  NOT NULL,
				  `stOther2` varchar(240)  NOT NULL,
				  `stOther3` varchar(240)  NOT NULL,
				  `stOther4` varchar(240)  NOT NULL,
				  `stOther5` varchar(240)  NOT NULL,
				  `stDateTime` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				  `stActive` varchar(2)  NOT NULL DEFAULT 1,
				  PRIMARY KEY (`stID`)
				)
				ENGINE = MyISAM;",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_UTIL_BASE', '"._("This setting defines the location of util base, which is typically /opt/fog/")."', '/opt/fog/', 'FOG Utils')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '19'",
);
// 20
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PXE_MENU_HIDDEN', '"._("This setting defines if you would like the FOG pxe menu hidden or displayed")."', '0', 'FOG PXE Settings')",
	"ALTER TABLE `" . DATABASE_NAME . "`.`globalSettings` MODIFY COLUMN `settingValue` LONGTEXT  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_PXE_ADVANCED', '"._("This setting defines if you would like to append any settings to the end of your PXE default file.")."', '', 'FOG PXE Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
				     values('FOG_USE_LEGACY_TASKLIST', '"._("This setting defines if you would like to use the legacy active tasks window.  Note:  The legacy screen will no longer be updated.")."', '0', 'General Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '20'",
);
// 21
$databaseSchema[] = array(
	"CREATE TABLE `" . DATABASE_NAME . "`.`hostMAC` (
					  `hmID` integer  NOT NULL AUTO_INCREMENT,
					  `hmHostID` integer  NOT NULL,
					  `hmMAC` varchar(18)  NOT NULL,
					  `hmDesc` longtext  NOT NULL,
					  PRIMARY KEY (`hmID`),
					  INDEX `idxHostID`(`hmHostID`),
					  INDEX `idxMac`(`hmMAC`)
					)
					ENGINE = MyISAM",

	"CREATE TABLE `" . DATABASE_NAME . "`.`oui` (
					  `ouiID` int(11) NOT NULL AUTO_INCREMENT,
					  `ouiMACPrefix` varchar(8) NOT NULL,
					  `ouiMan` varchar(254) NOT NULL,
					  PRIMARY KEY (`ouiID`),
					  KEY `idxMac` (`ouiMACPrefix`)
					) ENGINE=MyISAM",

	"INSERT INTO `" . DATABASE_NAME . "`.`globalSettings` (`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) VALUES
				('FOG_QUICKREG_AUTOPOP', 'Enable FOG Quick Registration auto population feature (0 = disabled, 1=enabled).  If this feature is enabled, FOG will auto populate the host settings and automatically image the computer without any user intervention.', '0', 'FOG Quick Registration'),
				('FOG_QUICKREG_IMG_ID', 'FOG Quick Registration Image ID.', '-1', 'FOG Quick Registration'),
				('FOG_QUICKREG_OS_ID', 'FOG Quick Registration OS ID.', '-1', 'FOG Quick Registration'),
				('FOG_QUICKREG_SYS_NAME', 'FOG Quick Registration system name template.  Use * for the autonumber feature.', 'PC-*', 'FOG Quick Registration'),
				('FOG_QUICKREG_SYS_NUMBER', 'FOG Quick Registration system name auto number.', '1', 'FOG Quick Registration'),
				('FOG_DEFAULT_LOCALE', 'Default language code to use for FOG.', 'en_US.UTF-8', 'General Settings'),
				('FOG_HOST_LOCKUP', 'Should FOG attempt to see if a host is active and display it as part of the UI?', '1', 'General Settings'),
				('FOG_UUID', 'This is a unique ID that is used to identify your installation.  In most cases you do not want to change this value.', '" . uniqid("", true) . "', 'General Settings')",
	"CREATE TABLE `" . DATABASE_NAME . "`.`pendingMACS` (
					  `pmID` INTEGER  NOT NULL AUTO_INCREMENT,
					  `pmAddress` varchar(18)  NOT NULL,
					  `pmHostID` INTEGER  NOT NULL,
					  PRIMARY KEY (`pmID`),
					  INDEX `idx_mc`(`pmAddress`),
					  INDEX `idx_host`(`pmHostID`)
					)
					ENGINE = MyISAM;",
	"INSERT INTO `" . DATABASE_NAME . "`.`globalSettings` (`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) VALUES
				('FOG_QUICKREG_MAX_PENDING_MACS', 'This setting defines how many mac addresses will be stored in the pending mac address table for each host.', '4', 'FOG Service - Host Register'), 
				('FOG_QUICKREG_PENDING_MAC_FILTER', 'This is a list of MAC address fragments that is used to filter out pending mac address requests.  For example, if you don\'t want to see pending mac address requests for VMWare NICs then you could filter by 00:05:69.  This filter is comma seperated, and is used like a *starts with* filter.', '', 'FOG Service - Host Register')",
	"UPDATE `" . DATABASE_NAME . "`.`globalSettings` SET settingValue = '3.0.7' WHERE settingKey = 'FOG_JPGRAPH_VERSION'",
	"INSERT INTO `" . DATABASE_NAME . "`.`globalSettings` (`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) VALUES
				('FOG_ADVANCED_STATISTICS', 'Enable the collection and display of advanced statistics.  This information WILL be sent to a remote server!  This information is used by the FOG team to see how FOG is being used.  The information that will be sent includes the server\'s UUID value, the number of hosts present in FOG, and number of images on your FOG server and well as total image space used. (0 = disabled, 1 = enabled).', '0', 'General Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '21'",
);
// 22
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`inventory` ADD INDEX ( `iHostID` )",
	"UPDATE `" . DATABASE_NAME . "`.`globalSettings` set settingKey = 'FOG_HOST_LOOKUP' WHERE settingKey = 'FOG_HOST_LOCKUP'",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '22'",
);
// 23
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.`globalSettings` (`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) VALUES
				('FOG_DISABLE_CHKDSK', 'This is an experimental feature that will can be used to not set the dirty flag on a NTFS partition after resizing it.  It is recommended to you run chkdsk. (0 = runs chkdsk, 1 = disables chkdsk).', '1', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.`globalSettings` (`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) VALUES
				('FOG_CHANGE_HOSTNAME_EARLY', 'This is an experimental feature that will can be used to change the computers hostname right after imaging the box, without the need for the FOG service.  (1 = enabled, 0 = disabled).', '1', 'General Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '23'",
);
// 24
$databaseSchema[] = array(
	// 24 - Blackout
	// Add Kernel, KernelArgs and PrimaryDisk to Group
	"ALTER TABLE `" . DATABASE_NAME . "`.`groups` ADD `groupKernel` VARCHAR( 255 ) NOT NULL",
	"ALTER TABLE `" . DATABASE_NAME . "`.`groups` ADD `groupKernelArgs` VARCHAR( 255 ) NOT NULL",
	"ALTER TABLE `" . DATABASE_NAME . "`.`groups` ADD `groupPrimaryDisk` VARCHAR( 255 ) NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '24'",
);
// 25
$databaseSchema[] = array(
	// 25 - Blackout - 8:39 AM 25/09/2011
	// Add 'os' Table & Data
	// Add 'imageOSID' field to 'images' table
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`os` (
	  `osID` mediumint(9) NOT NULL AUTO_INCREMENT,
	  `osName` varchar(30) NOT NULL,
	  `osDescription` text NOT NULL,
	  PRIMARY KEY (`osID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;",
	"INSERT INTO `" . DATABASE_NAME . "`.`os` (`osID`, `osName`, `osDescription`) VALUES
	(1, 'Windows 2000/XP', ''),
	(3, 'Windows 98', ''),
	(2, 'Windows Vista', ''),
	(4, 'Windows Other', ''),
	(5, 'Windows 7', ''),
	(50, 'Linux', ''),
	(99, 'Other', '');",
	"ALTER TABLE `" . DATABASE_NAME . "`.`images` ADD `imageOSID` MEDIUMINT NOT NULL ",
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` ADD UNIQUE (`hostMAC`)",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '25'",
);
// 26
$databaseSchema[] = array(
	// 26 - Blackout - 10:05 AM 30/09/2011
	// Change `images`.`imageSize` from VARCHAR(200) -> MEDIUMINT(9)
	"ALTER TABLE `" . DATABASE_NAME . "`.`images` CHANGE `imageSize` `imageSize` MEDIUMINT NOT NULL",
	// Add 'ngmInterface' to Storage Node table
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsGroupMembers` ADD `ngmInterface` VARCHAR( 10 ) NOT NULL DEFAULT 'eth0'",
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsGroupMembers` ADD `ngmGraphEnabled` ENUM( '0', '1' ) NOT NULL DEFAULT '0'",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '26'",
);
// 27
$databaseSchema[] = array(
	// 27 - Blackout - 2:16 PM 3/10/2011
	// Convert DATETIME 'createdTime' fields to TIMESTAMP with DEFAULT of CURRENT_TIMESTAMP
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks` CHANGE `taskCreateTime` `taskCreateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`groups` CHANGE `groupDateTime` `groupDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` CHANGE `hostCreateDate` `hostCreateDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`history` CHANGE `hTime` `hTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`aloLog` CHANGE `alDateTime` `alDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`images` CHANGE `imageDateTime` `imageDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`inventory` CHANGE `iCreateDate` `iCreateDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsFailures` CHANGE `nfDateTime` `nfDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapinJobs` CHANGE `sjCreateTime` `sjCreateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapins` CHANGE `sCreateDate` `sCreateDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapinTasks` CHANGE `stCheckinDate` `stCheckinDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`users` CHANGE `uCreateDate` `uCreateDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`userTracking` CHANGE `utDateTime` `utDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"ALTER TABLE `" . DATABASE_NAME . "`.`virus` CHANGE `vDateTime` `vDateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '27'",
);
// 28
$databaseSchema[] = array(
	// 28 - Blackout - 1:48 PM 1/12/2011
	// Add 'imageTypes' table and data
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`imageTypes` (
	  `imageTypeID` mediumint(9) NOT NULL auto_increment,
	  `imageTypeName` varchar(100) NOT NULL,
	  PRIMARY KEY  (`imageTypeID`)
	) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;",
	"INSERT INTO `" . DATABASE_NAME . "`.`imageTypes` (`imageTypeID`, `imageTypeName`) VALUES
	(1, 'Single Partition (NTFS Only, Resizable)'),
	(2, 'Multiple Partition Image - Single Disk (Not Resizable)'),
	(3, 'Multiple Partition Image - All Disks  (Not Resizable)'),
	(4, 'Raw Image (Sector By Sector, DD, Slow)');",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '28'",
);
// 29
$databaseSchema[] = array(
	// 29 - Blackout - 9:08 PM 20/12/2011
	// Update `images`.`imageOSID`
	function()
	{
		// Variables
		$DB = $GLOBALS['DatabaseManager']->connect()->DB;
		$FOGCore = $GLOBALS['FOGCore'];
		// Get all imageID's from Hosts -> Push imageID and osID into an array
		$DB->query("SELECT DISTINCT hostImage, hostOS FROM `%s`.`hosts` WHERE hostImage > 0", array(DATABASE_NAME));
		while ($host = $DB->fetch()->get())
		{
			$allImageID[$host['hostImage']] = $host['hostOS'];
		}
		// Iterate imageID's -> Update Image setting new osID -> Save
		foreach ((array)$allImageID AS $imageID => $osID)
		{
			$Image = new Image($imageID);
			if (!$Image->set('osID', $osID)->save())
				$errors[] = sprintf('<div>Failed updating the osID of imageID: imageID: %s, osID: %s</div>', $imageID, $osID);
		}
		// Return true on succes, (string) on error
		return (count($errors) ? implode('', $errors) : true);
	},
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '29'",
);
// 30
$databaseSchema[] = array(
	// 30 - Blackout - 10:54 AM 21/12/2011
	// Add 'imageTypeValue' field to `imageTypes`
	// Add default data for `imageTypeValue`
	// Update 'imageDD' (imageType) values to 'imageDD'+1 for new imageType IDs
	"ALTER TABLE `" . DATABASE_NAME . "`.`imageTypes` ADD `imageTypeValue` VARCHAR( 10 ) NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`imageTypes` SET `imageTypeValue` = 'n' WHERE `imageTypes`.`imageTypeID` = 1",
	"UPDATE `" . DATABASE_NAME . "`.`imageTypes` SET `imageTypeValue` = 'mps' WHERE `imageTypes`.`imageTypeID` = 2",
	"UPDATE `" . DATABASE_NAME . "`.`imageTypes` SET `imageTypeValue` = 'mpa' WHERE `imageTypes`.`imageTypeID` = 3",
	"UPDATE `" . DATABASE_NAME . "`.`imageTypes` SET `imageTypeValue` = 'dd' WHERE `imageTypes`.`imageTypeID` = 4",
	"UPDATE `" . DATABASE_NAME . "`.`images` SET `imageDD` = '4' WHERE `imageDD` = '3'",
	"UPDATE `" . DATABASE_NAME . "`.`images` SET `imageDD` = '3' WHERE `imageDD` = '2'",
	"UPDATE `" . DATABASE_NAME . "`.`images` SET `imageDD` = '2' WHERE `imageDD` = '1'",
	"UPDATE `" . DATABASE_NAME . "`.`images` SET `imageDD` = '1' WHERE `imageDD` = '0'",
	"ALTER TABLE `" . DATABASE_NAME . "`.`images` CHANGE `imageDD` `imageTypeID` MEDIUMINT NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '30'",
);
// 31
$databaseSchema[] = array(
	// 31 - Blackout - 10:56 AM 28/12/2011
	// scheduledTasks.stIsGroup now has a default of 0
	"ALTER TABLE `" . DATABASE_NAME . "`.`scheduledTasks` CHANGE `stIsGroup` `stIsGroup` VARCHAR( 2 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '0'",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '31'",
);
// 32
$databaseSchema[] = array(
	// 32 - Blackout - 11:26 AM 8/01/2012
	// Add 'taskStates' table and data
	// Update taskType values -> rename taskType to taskTypeID
	// Add 'taskTypes' table and data
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`taskStates` (
	  `tsID` int(11) NOT NULL,
	  `tsName` varchar(30) NOT NULL,
	  `tsDescription` text NOT NULL,
	  `tsOrder` tinyint(4) NOT NULL DEFAULT '0',
	  PRIMARY KEY (`tsID`)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;",
	"INSERT INTO `" . DATABASE_NAME . "`.`taskStates` (`tsID`, `tsName`, `tsDescription`, `tsOrder`) VALUES
	(1, 'Queued', 'Task has been created and FOG is waiting for the Host to check-in.', '1'),
	(2, 'In-Progress', 'Host is currently Imaging.', '2'),
	(3, 'Complete', 'Imaging has been completed.', '3');",
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks` CHANGE `taskState` `taskStateID` INT( 11 ) NOT NULL",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '1' WHERE `taskType`='d'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '2' WHERE `taskType`='u'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '3' WHERE `taskType`='x'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '4' WHERE `taskType`='w'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '5' WHERE `taskType`='m'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '6' WHERE `taskType`='t'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '7' WHERE `taskType`='r'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '8' WHERE `taskType`='c'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '9' WHERE `taskType`='v'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '10' WHERE `taskType`='i'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '11' WHERE `taskType`='j'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '12' WHERE `taskType`='s'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '13' WHERE `taskType`='l'",
	"UPDATE `" . DATABASE_NAME . "`.`tasks` SET `taskType` = '14' WHERE `taskType`='o'",
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks` CHANGE `taskType` `taskTypeID` MEDIUMINT NOT NULL ",
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`taskTypes` (
	  `ttID` mediumint(9) NOT NULL AUTO_INCREMENT,
	  `ttName` varchar(30) NOT NULL,
	  `ttDescription` text NOT NULL,
	  `ttIcon` varchar(30) NOT NULL,
	  `ttKernelTemplate` text NOT NULL,
	  `ttType` enum('fog','user') NOT NULL DEFAULT 'user',
	  `ttIsAdvanced` enum('0','1') NOT NULL DEFAULT '0',
	  `ttIsAccess` enum('both','host','group') NOT NULL DEFAULT 'both',
	  PRIMARY KEY (`ttID`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=20 ;",
	"INSERT INTO `" . DATABASE_NAME . "`.`taskTypes` (`ttID`, `ttName`, `ttDescription`, `ttIcon`, `ttKernelTemplate`, `ttType`, `ttIsAdvanced`, `ttIsAccess`) VALUES
	(1, 'Download', 'Deploy action will send an image saved on the FOG server to the client computer with all included snapins.', 'senddebug.png', 'type=down', 'fog', '0', 'both'),
	(2, 'Upload', 'Upload will pull an image from a client computer that will be saved on the server.', 'restoredebug.png', 'type=up', 'fog', '0', 'host'),
	(3, 'Debug', 'Debug mode will load the boot image and load a prompt so you can run any commands you wish. When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.', 'debug.png', 'type=down mode=debug', 'fog', '1', 'host'),
	(5, 'Memtest86+', 'Memtest86+ loads Memtest86+ on the client computer and will have it continue to run until stopped. When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.', 'memtest.png', '', 'fog', '1', 'both'),
	(6, 'Disk Surface Test', 'Disk Surface Test checks the hard drive''s surface sector by sector for any errors and reports back if errors are present.', 'surfacetest.png', '', 'fog', '1', 'both'),
	(7, 'Recover', 'Recover loads the photorec utility that can be used to recover lost files from a hard disk. When recovering files, make sure you save them to your NFS volume (ie: /images).', 'recover.png', '', 'fog', '1', 'both'),
	(8, 'Multi-Cast', 'Deploy action will send an image saved on the FOG server to the client computer with all included snapins.', 'senddebug.png', '', 'fog', '0', 'group'),
	(9, 'Virus Scan', 'Anti-Virus loads Clam AV on the client boot image, updates the scanner and then scans the Windows partition.', 'clam.png', '', 'fog', '1', 'both'),
	(10, 'Hardware Inventory', 'The hardware inventory task will boot the client computer and pull basic hardware information from it and report it back to the FOG server.', 'inventory.png', '', 'fog', '1', 'both'),
	(11, 'Password Reset', 'Password reset will blank out a Windows user password that may have been lost or forgotten.', 'winpass.png', '', 'fog', '1', 'both'),
	(12, 'All Snapins', 'This option allows you to send all the snapins to host without imaging the computer. (Requires FOG Service to be installed on client)', 'snap.png', '', 'fog', '1', 'both'),
	(13, 'Single Snapin', 'This option allows you to send a single snapin to a host. (Requires FOG Service to be installed on client)', 'snap.png', '', 'fog', '1', 'both'),
	(14, 'Wake-Up', 'Wake Up will attempt to send the Wake-On-LAN packet to the computer to turn the computer on. In switched environments, you typically need to configure your hardware to allow for this (iphelper).', 'wake.png', '', 'fog', '1', 'both'),
	(15, 'Download - Debug', 'Download - Debug mode allows FOG to setup the environment to allow you send a specific image to a computer, but instead of sending the image, FOG will leave you at a prompt right before sending. If you actually wish to send the image all you need to do is type \"fog\" and hit enter.', 'senddebug.png', 'type=down mode=debug', 'fog', '1', 'host'),
	(16, 'Upload - Debug', 'mode allows FOG to setup the environment to allow you Upload a specific image to a computer, but instead of Upload the image, FOG will leave you at a prompt right before restoring. If you actually wish to Upload the image all you need to do is type \"fog\" and hit enter.', 'restoredebug.png', 'type=up mode=debug', 'fog', '1', 'host'),
	(17, 'Download without Snapins', 'Deploy without snapins allows FOG to image the workstation, but after the task is complete any snapins linked to the host or group will NOT be sent.', 'sendnosnapin.png', '', 'fog', '1', 'both'),
	(18, 'Fast Wipe', 'Full Wipe will boot the client computer and perform a full disk wipe. This method writes a few passes of random data to the hard disk.', 'veryfastwipe.png', '', 'fog', '1', 'both'),
	(19, 'Normal Wipe', 'Normal Wipe will boot the client computer and perform a simple disk wipe. This method writes one pass of zero''s to the hard disk.', 'quickwipe.png', '', 'fog', '1', 'both'),
	(20, 'Full Wipe', 'Full Wipe will boot the client computer and perform a full disk wipe. This method writes a few passes of random data to the hard disk.', 'fullwipe.png', '', 'fog', '1', 'both');",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '1' WHERE `stTaskType`='d'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '2' WHERE `stTaskType`='u'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '3' WHERE `stTaskType`='x'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '4' WHERE `stTaskType`='w'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '5' WHERE `stTaskType`='m'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '6' WHERE `stTaskType`='t'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '7' WHERE `stTaskType`='r'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '8' WHERE `stTaskType`='c'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '9' WHERE `stTaskType`='v'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '10' WHERE `stTaskType`='i'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '11' WHERE `stTaskType`='j'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '12' WHERE `stTaskType`='s'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '13' WHERE `stTaskType`='l'",
	"UPDATE `" . DATABASE_NAME . "`.`scheduledTasks` SET `stTaskType` = '14' WHERE `stTaskType`='o'",
	"ALTER TABLE `" . DATABASE_NAME . "`.`scheduledTasks` CHANGE `stTaskType` `stTaskTypeID` MEDIUMINT NOT NULL ",
	"UPDATE `" . DATABASE_NAME . "`.`schemaVersion` set vValue = '32'",
);
// 33
$databaseSchema[] = array(
	// 33 - Blackout - 2:31 PM 12/01/2012
	// More TaskType conversion
	"ALTER TABLE `" . DATABASE_NAME . "`.`taskTypes` CHANGE `ttKernelTemplate` `ttKernelArgs` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL ",
	"ALTER TABLE `" . DATABASE_NAME . "`.`taskTypes` ADD `ttKernel` VARCHAR( 100 ) NOT NULL AFTER `ttIcon`",
	"TRUNCATE `" . DATABASE_NAME . "`.`taskTypes`",
	"INSERT INTO `" . DATABASE_NAME . "`.`taskTypes` (`ttID`, `ttName`, `ttDescription`, `ttIcon`, `ttKernel`, `ttKernelArgs`, `ttType`, `ttIsAdvanced`, `ttIsAccess`) VALUES
		(1, 'Download', 'Deploy action will send an image saved on the FOG server to the client computer with all included snapins.', 'senddebug.png', '', 'type=down', 'fog', '0', 'both'),
		(2, 'Upload', 'Upload will pull an image from a client computer that will be saved on the server.', 'restoredebug.png', '', 'type=up', 'fog', '0', 'host'),
		(3, 'Debug', 'Debug mode will load the boot image and load a prompt so you can run any commands you wish. When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.', 'debug.png', '', 'mode=onlydebug', 'fog', '1', 'host'),
		(4, 'Memtest86+', 'Memtest86+ loads Memtest86+ on the client computer and will have it continue to run until stopped. When you are done, you must remember to remove the PXE file, by clicking on \"Active Tasks\" and clicking on the \"Kill Task\" button.', 'memtest.png', 'fog/memtest/memtest', '', 'fog', '1', 'both'),
		(5, 'Test Disk', 'Test Disk loads the testdisk utility that can be used to check a hard disk and recover lost partitions.', 'testdisk.png', '', 'mode=checkdisk', 'fog', '1', 'both'),
		(6, 'Disk Surface Test', 'Disk Surface Test checks the hard drive''s surface sector by sector for any errors and reports back if errors are present.', 'surfacetest.png', '', 'mode=badblocks', 'fog', '1', 'both'),
		(7, 'Recover', 'Recover loads the photorec utility that can be used to recover lost files from a hard disk. When recovering files, make sure you save them to your NFS volume (ie: /images).', 'recover.png', '', 'mode=photorec', 'fog', '1', 'both'),
		(8, 'Multi-Cast', 'Deploy action will send an image saved on the FOG server to the client computer with all included snapins.', 'senddebug.png', '', 'type=down mc=yes', 'fog', '0', 'group'),
		(10, 'Hardware Inventory', 'The hardware inventory task will boot the client computer and pull basic hardware information from it and report it back to the FOG server.', 'inventory.png', '', 'mac_deployed=${HOST_MAC} mode=autoreg deployed=1', 'fog', '1', 'both'),
		(11, 'Password Reset', 'Password reset will blank out a Windows user password that may have been lost or forgotten.', 'winpass.png', '', 'mode=winpassreset', 'fog', '1', 'both'),
		(12, 'All Snapins', 'This option allows you to send all the snapins to host without imaging the computer. (Requires FOG Service to be installed on client)', 'snap.png', '', '', 'fog', '1', 'both'),
		(13, 'Single Snapin', 'This option allows you to send a single snapin to a host. (Requires FOG Service to be installed on client)', 'snap.png', '', '', 'fog', '1', 'both'),
		(14, 'Wake-Up', 'Wake Up will attempt to send the Wake-On-LAN packet to the computer to turn the computer on. In switched environments, you typically need to configure your hardware to allow for this (iphelper).', 'wake.png', '', '', 'fog', '1', 'both'),
		(15, 'Download - Debug', 'Download - Debug mode allows FOG to setup the environment to allow you send a specific image to a computer, but instead of sending the image, FOG will leave you at a prompt right before sending. If you actually wish to send the image all you need to do is type \"fog\" and hit enter.', 'senddebug.png', '', 'type=down mode=debug', 'fog', '1', 'host'),
		(16, 'Upload - Debug', 'mode allows FOG to setup the environment to allow you Upload a specific image to a computer, but instead of Upload the image, FOG will leave you at a prompt right before restoring. If you actually wish to Upload the image all you need to do is type \"fog\" and hit enter.', 'restoredebug.png', '', 'type=up mode=debug', 'fog', '1', 'host'),
		(17, 'Download without Snapins', 'Deploy without snapins allows FOG to image the workstation, but after the task is complete any snapins linked to the host or group will NOT be sent.', 'sendnosnapin.png', '', '', 'fog', '1', 'both'),
		(18, 'Fast Wipe', 'Full Wipe will boot the client computer and perform a full disk wipe. This method writes a few passes of random data to the hard disk.', 'veryfastwipe.png', '', 'mode=wipe wipemode=fast', 'fog', '1', 'both'),
		(19, 'Normal Wipe', 'Normal Wipe will boot the client computer and perform a simple disk wipe. This method writes one pass of zero''s to the hard disk.', 'quickwipe.png', '', 'mode=wipe wipemode=normal', 'fog', '1', 'both'),
		(20, 'Full Wipe', 'Full Wipe will boot the client computer and perform a full disk wipe. This method writes a few passes of random data to the hard disk.', 'fullwipe.png', '', 'mode=wipe wipemode=full', 'fog', '1', 'both'),
		(21, 'Virus Scan', 'Anti-Virus loads Clam AV on the client boot image, updates the scanner and then scans the Windows partition.', 'clam.png', '', 'mode=clamav avmode=s', 'fog', '1', 'both'),
		(22, 'Virus Scan - Quarantine', 'Anti-Virus loads Clam AV on the client boot image, updates the scanner and then scans the Windows partition.', 'clam.png', '', 'mode=clamav avmode=q', 'fog', '1', 'both');"
);
// 34
// Blackout - 9:00 AM 13/03/2012
$databaseSchema[] = array(
	// modules: Create module table -> Insert module information
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`modules` ( `id` mediumint(9) NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, `short_name` varchar(30) NOT NULL, `description` text NOT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=13",
	"INSERT INTO `" . DATABASE_NAME . "`.`modules` (`id`, `name`, `short_name`, `description`) VALUES
		(1, 'Directory Cleaner', 'dircleanup', 'This setting will enable or disable the directory cleaner service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(2, 'User Cleanup', 'usercleanup', 'This setting will enable or disable the user cleaner service module on this specific host.  If the module is globally disabled, this setting is ignored.  The user clean up service will remove all stale users on the local machine, accept for user accounts that are whitelisted.  This is typically used when dynamic local users is implemented on the workstation.'),
		(3, 'Display Manager', 'displaymanager', 'This setting will enable or disable the display manager service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(4, 'Auto Log Out', 'autologout', 'This setting will enable or disable the auto log out service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(5, 'Green FOG', 'greenfog', 'This setting will enable or disable the green fog service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(6, 'Snapins', 'snapin', 'This setting will enable or disable the snapin service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(7, 'Client Updater', 'clientupdater', 'This setting will enable or disable the client updater service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(8, 'Host Registration', 'hostregister', 'This setting will enable or disable the host register service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(9, 'Hostname Changer', 'hostnamechanger', 'This setting will enable or disable the hostname changer module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(10, 'Printer Manager', 'printermanager', 'This setting will enable or disable the printer manager service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(11, 'Task Reboot', 'taskreboot', 'This setting will enable or disable the task reboot service module on this specific host.  If the module is globally disabled, this setting is ignored.'),
		(12, 'User Tracker', 'usertracker', 'This setting will enable or disable the user tracker service module on this specific host.  If the module is globally disabled, this setting is ignored.');",
	// modulestatusbyhost: Create unique with Host ID and Module ID - this prevents duplicate modules per host
	// TODO: Create function to first remove dups, if not done this query will fail!!
	"ALTER TABLE `" . DATABASE_NAME . "`.`moduleStatusByHost` ADD UNIQUE ( `msHostID` , `msModuleID`)",
	// snapinassoc: Create unique with Host ID and Snapin ID - this prevents duplicate snapins per host
	// TODO: Create function to first remove dups, if not done this query will fail!!
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapinAssoc` ADD UNIQUE ( `saHostID` , `saSnapinID`)",
);
// 35
// Blackout - 12:35 PM 26/04/2012
$databaseSchema[] = array(
	// New Task States
	"TRUNCATE `" . DATABASE_NAME . "`.`taskStates`",
	"INSERT INTO `" . DATABASE_NAME . "`.`taskStates` (`tsID`, `tsName`, `tsDescription`, `tsOrder`) VALUES
	(1, 'Queued', 'Task has been created and FOG is waiting for the Host to check-in.', '1'),
	(2, 'Checked In', 'PC has checked in and is in queue for imaging', '2'),
	(3, 'In-Progress', 'Host is currently Imaging.', '3'),
	(4, 'Complete', 'Imaging has been completed.', '4'),
	(5, 'Cancelled', 'Task was aborted by user', '5');"
);
// 36
// Blackout - 12:18 PM 4/05/2012
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`groups` ADD UNIQUE ( `groupName` )",
	"ALTER TABLE `" . DATABASE_NAME . "`.`groupMembers` ADD UNIQUE ( `gmHostID`, `gmGroupID` )"
);
// 37
// Blackout - 6:12 PM 5/05/2012
$databaseSchema[] = array(
	"CREATE TABLE IF NOT EXISTS `" . DATABASE_NAME . "`.`taskLog` (
	`id` mediumint(9) NOT NULL AUTO_INCREMENT,
	`taskID` mediumtext NOT NULL,
	`taskStateID` mediumint(9) NOT NULL,
	`ip` varchar(15) NOT NULL,
	`createTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`createdBy` VARCHAR( 30 ) NOT NULL,
	PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;"
);
// 38
// Blackout - 12:52 PM 6/05/2012
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsGroupMembers` ADD UNIQUE ( `ngmMemberName` )",
	"ALTER TABLE `" . DATABASE_NAME . "`.`nfsGroups` ADD UNIQUE ( `ngName` )"
);
// 39
// Blackout - 8:09 AM 26/08/2012
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.`os` (`osID`, `osName`, `osDescription`) VALUES ('6', 'Windows 8', '')",
	"ALTER TABLE `" . DATABASE_NAME . "`.`hosts` drop column `hostOS`"
);
// 40
$databaseSchema[] = array(
    "INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_PIGZ_COMP', '"._("PIGZ Compression Rating")."', '9', 'FOG PXE Settings')",
);
// 41
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME ."`.`imagingLog` ADD `ilType` VARCHAR(64) NOT NULL"
);
// 42
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME ."`.`images` CHANGE `imageSize` `imageSize` BIGINT NOT NULL"
);
// 43
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME."`.globalSettings(settingKey,settingDesc, settingValue, settingCategory)
		values('FOG_KEY_SEQUENCE', '"._("Key Sequence for boot prompt.")."', '0', 'FOG Boot Setting')"
);
// 44
$databaseSchema[] = array(
	"CREATE TABLE ".DATABASE_NAME.".keySequence(
	ksID INTEGER NOT NULL AUTO_INCREMENT,
	ksValue varchar(25) NOT NULL,
	ksAscii varchar(25) NOT NULL,
	PRIMARY KEY (ksID))
	ENGINE = MyISAM"
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
// 45 - 79
foreach ($keySequences AS $value => $ascii)
{
	$databaseSchema[] = array(
		"INSERT INTO `" . DATABASE_NAME."`.`keySequence` (`ksValue`,`ksAscii`)
			VALUES ( '".$value."','".$ascii."' )"
	);
}
// 80
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`tasks`
		ADD COLUMN `taskShutdown` char  NOT NULL AFTER `taskLastMemberID`",
);
// 81
$databaseSchema[] = array(
        "ALTER TABLE `" . DATABASE_NAME . "`.`images`
                ADD COLUMN `imageLegacy` char NOT NULL AFTER `imageOSID`",
	"UPDATE `" . DATABASE_NAME . "`.`images` set imageLegacy = '1' where 1 = 1",
);
// 82
$databaseSchema[] = array(
    "INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
	                     values('FOG_LEGACY_FLAG_IN_GUI', '"._("This setting allows you to set whether or not an image is legacy.  Valid values are 0 or 1")."', '0', 'General Settings')"
);
// 83
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_PROXY_USERNAME', '"._("This setting defines the proxy username to use.")."', '', 'General Settings')",
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_PROXY_PASSWORD', '"._("This setting defines the proxy password to use.")."', '', 'General Settings')",
	"UPDATE `" . DATABASE_NAME . "`.`globalSettings` SET `settingCategory` = 'Proxy Settings' WHERE `globalSettings`.`settingKey` LIKE 'FOG_PROXY%'",
);
// 84
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME . "`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_NO_MENU','"._("This setting sets the system to no menu, if there is no task set, it boots to first device.")."','','FOG Boot Settings')",
);
// 85
$databaseSchema[] = array(
	"UPDATE `" . DATABASE_NAME ."`.`globalSettings` SET `settingCategory` = 'FOG Boot Settings' WHERE `settingCategory` = 'FOG PXE Settings' OR `settingCategory` = 'FOG Boot Setting'",
);
// 86
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME ."`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_TFTP_PXE_KERNEL_32','Location of the 32 bit kernel file on the PXE server, this should point to the kernel itself.','bzImage32','TFTP Server')",
	"INSERT INTO `" . DATABASE_NAME ."`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_PXE_BOOT_IMAGE_32','The settings defines where the 32 bit fog boot file system image is located.','init_32.xz','TFTP Server')",
);
// 87
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME ."`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_MINING_ENABLE','This setting defines whether to have the imaging client give up a resources for mining cryptocurrency.  This is a means to donate to the FOG project without any real money.','" . FOG_DONATE_MINING ."','General Settings')",
);
// 88
$databaseSchema[] = array(
        "ALTER TABLE `" . DATABASE_NAME . "`.`images`
                ADD COLUMN `imageLastDeploy` DATETIME NOT NULL AFTER `imageLegacy`",
);
// 89
$databaseSchema[] = array(
        "ALTER TABLE `" . DATABASE_NAME . "`.`hosts`
                ADD COLUMN `hostLastDeploy` DATETIME NOT NULL AFTER `hostCreateDate`",
);
// 90
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME ."`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_BOOT_EXIT_TYPE','The method of booting to the hard drive.  Most will accept sanboot, but some require exit.','','FOG Boot Settings')",
);
// 91
$databaseSchema[] = array(
	"INSERT INTO `" . DATABASE_NAME ."`.globalSettings(settingKey, settingDesc, settingValue, settingCategory)
		values('FOG_MINING_MAX_CORES','This setting defines the maximum number of CPU cores you are willing to dedicate to mining cryptocurrency.','1','General Settings')",
);
// 92
$databaseSchema[] = array(
    "ALTER TABLE `" . DATABASE_NAME . "`.`snapinJobs`
		ADD COLUMN `sjStateID` INT(11) NOT NULL AFTER `sjHostID`",
);
// 93
$databaseSchema[] = array(
	"ALTER TABLE `" . DATABASE_NAME . "`.`snapinJobs` CHANGE `sjStateID` `sjStateID` INT(11) NOT NULL",
);
print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
print "\n".'<html xmlns="http://www.w3.org/1999/xhtml">';
print "\n\t<head>";
print "\n\t\t<title>"._('FOG').' '.FOG_VERSION.' '._('Database Schema Installer / Updater').'</title>';
print "\n\t\t".'<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
print "\n\t\t".'<meta http-equiv="x-ua-compatible" content="IE=8">';
print "\n\t\t".'<link rel="stylesheet" type="text/css" href="../../management/css/fog.css" />';
print "\n\t</head>";
print "\n<body>";
print "\n\t".'<div id="wrapper">';
print "\n\t<!-- Header -->";
print "\n\t".'<div id="header">';
print "\n\t\t".'<div id="logo">';
print "\n\t\t\t".'<h1><a href="'.$_SERVER['PHP_SELF'].'"><img src="../../management/images/fog-logo.png" title="'.$foglang['Home'].'" /><sup>'.FOG_VERSION.'</sup></a></h1>';
print "\n\t\t\t".'<h2>'.$foglang['Slogan'].'</h2>';
print "\n\t\t</div>";
print "\n\t</div>";
print "\n\t<!-- Content -->";
print "\n\t".'<div id="content" class="dashboard">';
print "\n\t\t<h1>"._('Database Schema Installer / Updater').'</h1>';
print "\n\t".'<div id="content-inner">';
$FOG_SCHEMA = count($databaseSchema);
if ( $_POST["confirm"] == "yes" )
{
	if ($DatabaseManager && $DB)
	{
		$currentSchema = $DatabaseManager->getVersion();
		if ( $FOG_SCHEMA != $currentSchema )
		{
			// Blackout - 1:05 PM 12/01/2012
			// Get applicable schema updates by slicing array at $currentSchema
			foreach (array_slice($databaseSchema, $currentSchema, null, true)  AS $version => $updates)
			{
				// Increment version by 1 so schema update version and database schema version align
				++$version;
				// Iterate updates
				foreach ($updates AS $i => $update)
				{
					// Update is Function
					if (is_callable($update))
					{
						$result = $update();
						if (is_string($result))
							$errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Function Error:</b> <pre>%s</pre></p><p><b>Function:</b> <pre>%s</pre></p>', "$version - $i", $result, print_r($update, 1));
					}
					// Update is SQL
					else if (! $DB->query($update)->queryResult())
						$errors[] = sprintf('<p><b>Update ID:</b> %s</p><p><b>Database Error:</b> <pre>%s</pre></p><p><b>Database SQL:</b> <pre>%s</pre></p>', "$version - $i", $DB->error(), $update);
				}
				// Update schema version
				$DB->query("UPDATE `%s`.`schemaVersion` set vValue = '%s'", array(DATABASE_NAME, $version));
			}
			// BUG: Must reconnect to get right version. BUG is in getVersion()
			if ($FOG_SCHEMA == $DatabaseManager->connect()->getVersion())
			{
				print "\n\t\t\t<p>"._('Update/Install Successful!').'</p>';
				print "\n\t\t\t<p>"._('Click').' <a href="../../management">'._('here').'</a> '._('to login.').'</p>';
			}
			else
				print "\n\t\t\t<p>"._('Update/Install Failed!').'</p>';
			if (count($errors))
			{
				printf('<h2>%s</h2>', _('The following errors occured'));
				print implode("\n<hr />\n", $errors);
			}
		}
		else
		{
			print "\n\t\t\t<p>"._('Update not required, your database schema is up to date!').'</p>';
			print "\n\t\t\t<p>"._('Click').' <a href="../../management">'._('here').'</a> '._('to login.').'</p>';
		}
	}
	else
		print "\n\t\t\t<p>"._('Unable to connect to Database').'</p><p>'._('Database Error').':<br /><pre class="shellcommand">'. mysql_error() . '</pre></p><p>'._('Make sure your database username and password are correct.').'</p>';
}
else
{
	print "\n\t\t\t".'<form method="post" action="index.php?redir=1">';
	print "\n\t\t\t\t<p>"._('Your FOG database schema is not up to date, either because you have updated FOG or this is a new FOG installation.  If this is a upgrade, we highly recommend that you backup your FOG database before updating the schema (this will allow you to return the previous installed version).').'</p>';
	print "\n\t\t\t\t<p>"._('If you would like to backup your FOG database you can do so my using MySql Administrator or by running the following command in a terminal window (Applications -> System Tools -> Terminal), this will save sqldump in your home directory.').'</p>';
	print "\n\t\t\t\t".'<div id="sidenotes">cd ~;mysqldump --allow-keywords -x -v fog > fogbackup.sql</div>';
	print "\n\t\t\t\t<p></p>";
	print "\n\t\t\t\t<p>"._('Are you sure you wish to install/update the FOG database?').'</p>';
	print "\n\t\t\t\t<br />".'<input type="hidden" name="confirm" value="yes" /><input type="submit" value="'._('Install/Upgrade Now').'" />';
	print "\n\t\t\t</form>";
}
print "\n\t\t\t</div>";
print "\n\t\t</div>";
print "\n\t</div>";
print "\n\t<!-- Footer: Be nice, give us some credit -->";
print "\n\t".'<div id="footer">FOG Project: Chuck Syperski, Jian Zhang, Peter Gilchrist &amp; Tom Elliott FOG Client/Prep link: <a href="http://'.$FOGCore->getSetting('FOG_WEB_HOST').$FOGCore->getSetting('FOG_WEB_ROOT').'client">FOG Client/FOG Prep</a></div>';
print "\n</body>";
print "\n</html>";
