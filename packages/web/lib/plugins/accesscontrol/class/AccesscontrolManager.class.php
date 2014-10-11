<<<<<<< HEAD
<?php
/** Class Name: ExampleManager
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
		/** Add the information into the database.
			This is commented out so we don't actually
			create anything.
        $sql = "CREATE TABLE fog.example
        (eID INTEGER NOT NULL AUTO_INCREMENT,
        eName VARCHAR(250) NOT NULL,
		eOther VARCHAR(250) NOT NULL,
		eHostID INTEGER NOT NULL,
        PRIMARY KEY(eID),
        INDEX new_index (eHostID),
        ENGINE = MyISAM";
		*** ACTUALLY CREATES THE DATABASE TABLE FROM ABOVE ***
        if ($this->DB->query($sql))
        {   
			*** IF NEEDED CREATE GLOBAL ENTRIES ***
            $Example1 = new Service(array(
                'name' => 'FOG_EXAMPLE_ONE',
                'description' => 'Example one global entry.',
                'value' => '', 
                'category' => 'Plugin: '.$name,
            ));
			** SAVE THE NEW ENTRY **
            $Example1->save();
            return true;
        }
        return false;
		*/
		return true;
    } 
	public function uninstall()
	{
		return true;
	}
}
=======
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
>>>>>>> dev-branch
