<?php
/** Class Name: AccesscontrolManager
	Just helps more with the example.
*/
class AccesscontrolManager extends FOGManagerController
{
	/**	install($name)
		Method that installs the relevant plugin.

		$name just sends the plugin name.  Useful
		for schema adding.
	*/
	public function install($name)
    {   
        $sql = "CREATE TABLE accessControls
        (acID INTEGER NOT NULL AUTO_INCREMENT,
        acName VARCHAR(250) NOT NULL,
		acDesc longtext NOT NULL,
        acOther VARCHAR(250) NOT NULL,
		acUserID INTEGER NOT NULL,
        acGroupID INTEGER NOT NULL,
        PRIMARY KEY(acID),
        INDEX new_index (acUserID),
        INDEX new_index2 (acGroupID))
        ENGINE = MyISAM";
        if (!$this->DB->query($sql))
			return false;
		return true;
    } 
	public function uninstall()
	{
		if (!$this->DB->query("DROP TABLE accessControls"))
			return false;
		return true;
	}
}
