<?php

class LDAPManager extends FOGManagerController
{
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	
	public function install($name)
    {   
        $sql = "CREATE TABLE fog.LDAPServers
        (lsID INTEGER NOT NULL AUTO_INCREMENT,
        lsName VARCHAR(250) NOT NULL,
		lsDesc longtext NOT NULL,
		lsCreatedBy VARCHAR(30) NOT NULL,
        lsAddress VARCHAR(30) NOT NULL,
		lsCreatedTime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        lsDN VARCHAR(100) NOT NULL,
        lsPort INTEGER NOT NULL,
        PRIMARY KEY(lsID),
		KEY new_index (lsName))
        ENGINE = MyISAM";
        if ($this->DB->query($sql))
        {   
			return true;
        }   
        return false;
    }
	public function uninstall()
	{
		if (!$this->DB->query("DROP TABLE LDAPServers"))
			return false;
		return true;
	}
}

		