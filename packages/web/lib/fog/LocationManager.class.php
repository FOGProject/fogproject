<?php

class LocationManager extends FOGManagerController
{
	public function addSchema($name)
    {   
        $sql = "CREATE TABLE fog.location
        (lID INTEGER NOT NULL AUTO_INCREMENT,
        lName VARCHAR(250) NOT NULL,
		lDesc longtext NOT NULL,
		lStorageGroupID INTEGER NOT NULL,
		lStorageNodeID INTEGER NOT NULL,
		lCreatedBy VARCHAR(30) NOT NULL,
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
}
