<?php
/*
 *   FOG - Free, Open-Source Ghost is a computer imaging solution.
 *   Copyright (C) 2007  Chuck Syperski & Jian Zhang
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */
require_once( "../config.php" );
require_once( "../functions.include.php" );

$installPath = array();
$installPath[0] = array( 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ); 
$installPath[1] = array( 13, 14 ); 
$installPath[2] = array( 15, 16, 17, 18, 19, 20 ); 
$installPath[3] = array( 21, 22, 23 ); 
$installPath[4] = array( 24, 25, 26, 27, 28 ); 
$installPath[5] = array( 29, 30 ); 
$installPath[6] = array( 31, 32, 33, 34, 35, 36, 37 ); 

$dbschema[0] = "CREATE DATABASE " . MYSQL_DATABASE ;

$dbschema[1] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`groupMembers` (
  `gmID` int(11) NOT NULL auto_increment,
  `gmHostID` int(11) NOT NULL,
  `gmGroupID` int(11) NOT NULL,
  PRIMARY KEY  (`gmID`),
  KEY `new_index` (`gmHostID`),
  KEY `new_index1` (`gmGroupID`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC";

$dbschema[2] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`groups` (
  `groupID` int(11) NOT NULL auto_increment,
  `groupName` varchar(50) NOT NULL,
  `groupDesc` longtext NOT NULL,
  `groupDateTime` datetime NOT NULL,
  `groupCreateBy` varchar(50) NOT NULL,
  `groupBuilding` int(11) NOT NULL,
  PRIMARY KEY  (`groupID`),
  KEY `new_index` (`groupName`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[3] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`history` (
  `hID` int(11) NOT NULL auto_increment,
  `hText` longtext NOT NULL,
  `hUser` varchar(200) NOT NULL,
  `hTime` datetime NOT NULL,
  `hIP` varchar(50) NOT NULL,
  PRIMARY KEY  (`hID`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[4] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`hosts` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[5] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`images` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[6] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`schemaVersion` (
  `vID` int(11) NOT NULL auto_increment,
  `vValue` int(11) NOT NULL,
  PRIMARY KEY  (`vID`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC";

$dbschema[7] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`supportedOS` (
  `osID` int(10) unsigned NOT NULL auto_increment,
  `osName` varchar(150) NOT NULL,
  `osValue` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`osID`),
  KEY `new_index` (`osValue`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[8] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`tasks` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[9] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`users` (
  `uId` int(11) NOT NULL auto_increment,
  `uName` varchar(40) NOT NULL,
  `uPass` varchar(50) NOT NULL,
  `uCreateDate` datetime NOT NULL,
  `uCreateBy` varchar(40) NOT NULL,
  PRIMARY KEY  (`uId`),
  KEY `new_index` (`uName`),
  KEY `new_index1` (`uPass`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[10] = "INSERT INTO `" . MYSQL_DATABASE . "`.`users` VALUES  ('','fog', MD5('password'),'0000-00-00 00:00:00','')";

$dbschema[11] = "INSERT INTO `" . MYSQL_DATABASE . "`.`supportedOS` VALUES  ('','Windows XP', '1')";

$dbschema[12] = "INSERT INTO `" . MYSQL_DATABASE . "`.`schemaVersion` VALUES  ('','1')";

// Schema version 2

$dbschema[13] = "INSERT INTO `" . MYSQL_DATABASE . "`.`supportedOS` VALUES  ('','Windows Vista', '2')";

$dbschema[14] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '2'";

// Schema Version 3

$dbschema[15] = "ALTER TABLE `" . MYSQL_DATABASE . "`.`hosts` 
		 ADD COLUMN `hostUseAD` char  NOT NULL AFTER `hostOS`,
		 ADD COLUMN `hostADDomain` VARCHAR(250)  NOT NULL AFTER `hostUseAD`,
		 ADD COLUMN `hostADOU` longtext  NOT NULL AFTER `hostADDomain`,
		 ADD COLUMN `hostADUser` VARCHAR(250)  NOT NULL AFTER `hostADOU`,
		 ADD COLUMN `hostADPass` VARCHAR(250)  NOT NULL AFTER `hostADUser`,
		 ADD COLUMN `hostAnon1` VARCHAR(250)  NOT NULL AFTER `hostADPass`,
		 ADD COLUMN `hostAnon2` VARCHAR(250)  NOT NULL AFTER `hostAnon1`,
		 ADD COLUMN `hostAnon3` VARCHAR(250)  NOT NULL AFTER `hostAnon2`,
		 ADD COLUMN `hostAnon4` VARCHAR(250)  NOT NULL AFTER `hostAnon3`,
		 ADD INDEX `new_index4`(`hostUseAD`)";
		 
$dbschema[16] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`snapinAssoc` (
		  `saID` int(11) NOT NULL auto_increment,
		  `saHostID` int(11) NOT NULL,
		  `saSnapinID` int(11) NOT NULL,
		  PRIMARY KEY  (`saID`),
		  KEY `new_index` (`saHostID`),
		  KEY `new_index1` (`saSnapinID`)
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";
		
$dbschema[17] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`snapinJobs` (
		  `sjID` int(11) NOT NULL auto_increment,
		  `sjHostID` int(11) NOT NULL,
		  `sjCreateTime` datetime NOT NULL,
		  PRIMARY KEY  (`sjID`),
		  KEY `new_index` (`sjHostID`)
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";		

$dbschema[18] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`snapinTasks` (
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
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";
		
$dbschema[19] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`snapins` (
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
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";	
		
$dbschema[20] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '3'";	

$dbschema[21] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`multicastSessions` (
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
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[22] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`multicastSessionsAssoc` (
		  `msaID` int(11) NOT NULL auto_increment,
		  `msID` int(11) NOT NULL,
		  `tID` int(11) NOT NULL,
		  PRIMARY KEY  (`msaID`),
		  KEY `new_index` (`msID`),
		  KEY `new_index1` (`tID`)
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";
	
$dbschema[23] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '4'";

$dbschema[24] = "ALTER TABLE `" . MYSQL_DATABASE . "`.`images` 
		 ADD COLUMN `imageDD` VARCHAR(1)  NOT NULL AFTER `imageSize`,
		 ADD INDEX `new_index2`(`imageDD`)";
		 
$dbschema[25] = "UPDATE `" . MYSQL_DATABASE . "`.`supportedOS` set osName = 'Windows 2000/XP' where osValue = '1'";		 

$dbschema[26] = "INSERT INTO `" . MYSQL_DATABASE . "`.`supportedOS` VALUES  ('','Other', '99')";
		 
$dbschema[27] = "ALTER TABLE `" . MYSQL_DATABASE . "`.`multicastSessions` CHANGE COLUMN `msAnon1` `msIsDD` VARCHAR(1)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL";
		 
$dbschema[28] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '5'";	

$dbschema[29] = "CREATE TABLE `" . MYSQL_DATABASE . "`.`virus` (
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
		ENGINE = MyISAM";
$dbschema[30] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '6'";	

$dbschema[31] = "CREATE TABLE `" . MYSQL_DATABASE . "`.`userTracking` (
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
		ENGINE = MyISAM";

$dbschema[32] = "ALTER TABLE `" . MYSQL_DATABASE . "`.`hosts` CHANGE COLUMN `hostAnon1` `hostPrinterLevel` VARCHAR(2)  CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL";

$dbschema[33] = "CREATE TABLE `" . MYSQL_DATABASE . "`.`printers` (
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
		ENGINE = MyISAM";


$dbschema[34] = "CREATE TABLE `" . MYSQL_DATABASE . "`.`printerAssoc` (
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
		ENGINE = MyISAM";

$dbschema[35] = "CREATE TABLE  `" . MYSQL_DATABASE . "`.`inventory` (
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
		) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1";

$dbschema[36] = "CREATE TABLE `" . MYSQL_DATABASE . "`.`clientUpdates` (
		  `cuID` integer  NOT NULL AUTO_INCREMENT,
		  `cuName` varchar(200)  NOT NULL,
		  `cuMD5` varchar(100)  NOT NULL,
		  `cuType` varchar(3)  NOT NULL,
		  `cuFile` LONGBLOB  NOT NULL,
		  PRIMARY KEY (`cuID`),
		  INDEX `new_index`(`cuName`),
		  INDEX `new_index1`(`cuType`)
		)
		ENGINE = MyISAM";

$dbschema[37] = "UPDATE `" . MYSQL_DATABASE . "`.`schemaVersion` set vValue = '7'";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link rel="stylesheet" type="text/css" href="../../management/css/<?php echo FOG_THEME;?>" />

<title>
FOG <?php echo FOG_VERSION; ?> database schema installer/updater
</title>
</head>
<body>
	<center>
		<div class="mainContainer">
			<div class="header">
				<?php echo ("<div class=\"version\">Version: " . FOG_VERSION . "</div>"); ?>
				</font>
			</div>
		<?php
		if ( $_POST["confirm"] == "yes" )
		{
			$conn = mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);
			if ( $conn )
			{
				@mysql_select_db( MYSQL_DATABASE, $conn );
				$currentSchema = getCurrentDBVersion($conn);
				if ( FOG_SCHEMA != $currentSchema )
				{
					while( $currentSchema != FOG_SCHEMA )
					{
						$queryArray = $installPath[$currentSchema];
						for( $i = 0; $i < count( $queryArray ); $i++ )
						{
							$sql = $dbschema[$queryArray[$i]];
							
							if ( ! mysql_query( $sql ) )
							{
									echo ( "<p class=\"installConfirm\">Database error: (ID# ".	 $currentSchema . "-" . $i . ")</p><p>Database Error: <br /><pre class=\"shellcommand\">"  . mysql_error() . "</pre></p>" );
									exit;							
							}
							$conn = @mysql_connect( MYSQL_HOST, MYSQL_USERNAME, MYSQL_PASSWORD);																		
						}
						$currentSchema++;
					}
					
					if ( FOG_SCHEMA == getCurrentDBVersion($conn) )
					{
						echo "<p class=\"installConfirm\">Update/Install Successful!</p>";
						echo ( "<p>Click <a href=\"../../management\">here</a> to login.</p>" );
					}
					else
						echo(  "<p class=\"installConfirm\">Update/Install Failed!</p>" );					
				}
				else
				{
					echo ( "<p class=\"installConfirm\">Update not required, your database schema is up to date!</p>" );
					echo ( "<p>Click <a href=\"../../management\">here</a> to login.</p>" );
				}				
			}
			else
			{
				echo( "<p class=\"installConfirm\">Unable to connect to Database</p><p>Database Error:<br /><pre class=\"shellcommand\">" . mysql_error() . "</pre></p><p>Make sure your database username and password are correct.</p>" );
			}
		}
		else
		{
			echo ( "<form method=\"POST\" action=\"index.php\">\n" );
				echo ( "<p>Your FOG database schema is not up to date, either because you have updated FOG or this is a new FOG installation.  If this is a upgrade, we highly recommend that you backup your FOG database before updating the schema (this will allow you to return the previous installed version).</p>\n" );
				
				echo ( "<p>If you would like to backup your FOG database you can do so my using MySql Administrator or by running the following command in a terminal window (Applications -> System Tools -> Terminal), this will save sqldump in your home directory.</p>\n" );
				
				echo ( "<p><pre class=\"shellcommand\">cd ~;mysqldump --allow-keywords -x -v fog > fogbackup.sql</pre></p>" );
				
				echo ( "<p></p>" );
				
				echo ( "<p class=\"installConfirm\">Are you sure you wish to install/update the FOG database?</p>\n" );
				echo ( "<br /><input type=\"hidden\" name=\"confirm\" value=\"yes\" /><input type=\"submit\" value=\"Install/Upgrade Now\" />\n" );
			echo ( "</form>\n" );		
		}
		?>
		</div>
		<div class="footer"><h1>Created By Chuck Syperski & Jian Zhang | GNU General Public License Version 3</h1></div>
	</center>
</body>
</html>
