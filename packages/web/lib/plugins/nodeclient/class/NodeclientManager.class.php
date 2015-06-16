<?php
class NodeclientManager extends FOGManagerController {
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	public function install($name) {
		$sql = "CREATE TABLE IF NOT EXISTS hostFingerprintAssoc (
		`fpHostID` mediumint(9) NOT NULL,
		`fingerprint` LONGTEXT NULL,
		PRIMARY KEY  (`fpHostID`)
		) ENGINE=MyISAM;";
		if (!$this->DB->query($sql)) return false;
		$sql = "CREATE TABLE IF NOT EXISTS queueAssoc (
		`qaID` mediumint(9) NOT NULL auto_increment,
		`qaHostID` mediumint(9) NOT NULL,
		`qaStateID` mediumint(9) NOT NULL,
		`qaModuleID` mediumint(9) NOT NULL,
		`qaTaskInfo` LONGTEXT NULL,
		`qaCreatedTime` datetime NOT NULL,
		PRIMARY KEY  (`qaID`)
		) ENGINE=MyISAM;";
		if (!$this->DB->query($sql)) return false;
		$sql = "CREATE TABLE IF NOT EXISTS nodeJSconfig (
		`nodeID` mediumint(9) NOT NULL auto_increment,
		`nodePort` mediumint(9) NOT NULL,
		`nodeAES` LONGTEXT NOT NULL,
		`nodeIP` LONGTEXT NOT NULL,
		`nodeName` LONGTEXT,
		PRIMARY KEY  (`nodeID`)
		) ENGINE=MyISAM;";
		if (!$this->DB->query($sql)) return false;
		$sql = "ALTER IGNORE TABLE `".DATABASE_NAME."`.`hostFingerprintAssoc` ADD UNIQUE (`fpHostID`)";
		if (!$this->DB->query($sql)) return false;
		$sql = "ALTER IGNORE TABLE `".DATABASE_NAME."`.`nodeJSconfig` ADD UNIQUE (`nodeID`)";
		if (!$this->DB->query($sql)) return false;
		return true;
    } 
	public function uninstall() {
		if (!$this->DB->query("DROP TABLE hostFingerprintAssoc,queueAssoc,nodeJSconfig"))
			return false;
		return true;
	}
}
