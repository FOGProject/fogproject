<?php

class LocationManager extends FOGManagerController
{
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	public function install($name)
    {   
        $sql = "CREATE TABLE fog.location
        (lID INTEGER NOT NULL AUTO_INCREMENT,
        lName VARCHAR(250) NOT NULL,
		lDesc longtext NOT NULL,
		lStorageGroupID INTEGER NOT NULL,
		lStorageNodeID INTEGER NOT NULL,
		lCreatedBy VARCHAR(30) NOT NULL,
		lTftpEnabled VARCHAR(1) NOT NULL,
		lCreatedTime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(lID),
		KEY new_index (lName),
		KEY new_index1 (lStorageGroupID))
        ENGINE = MyISAM";
        if ($this->DB->query($sql))
        {   
			$sql = "CREATE TABLE fog.locationAssoc
			(laID INTEGER NOT NULL AUTO_INCREMENT,
			laLocationID INTEGER NOT NULL,
			laHostID INTEGER NOT NULL,
			PRIMARY KEY (laID),
			KEY new_index (laHostID))
			ENGINE=MyISAM";
			if ($this->DB->query($sql))
            	return true;
        }   
        return false;
    }
	public function uninstall()
	{
		if (!$this->DB->query("DROP TABLE locationAssoc"))
			return false;
		else if (!$this->DB->query("DROP TABLE location"))
			return false;
		return true;
	}
}
